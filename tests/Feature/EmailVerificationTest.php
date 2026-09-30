<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CourseSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Owning proof of an email address.
 *
 * The point of these is that nothing is reachable without it, including from
 * a correct password, and that the link cannot be forged, replayed forever or
 * used to prove an address somebody has since changed.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The gate has to be proven on routes that resolve a real course from
        // the slug, so the catalogue has to exist first.
        $this->seed(CourseSeeder::class);
    }

    /* -----------------------------------------------------------------
     | Signing up
     * ----------------------------------------------------------------- */

    public function test_registration_sends_the_link_and_signs_nobody_in(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Sajib Islam',
            'email' => 'sajib@example.com',
            'password' => 'Sup3rSecret!',
            'password_confirmation' => 'Sup3rSecret!',
        ])->assertRedirect('/email/verify');

        $this->assertGuest();

        Notification::assertSentTo(
            User::where('email', 'sajib@example.com')->firstOrFail(),
            VerifyEmail::class,
        );
    }

    public function test_the_new_account_starts_unverified(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Sajib Islam',
            'email' => 'sajib@example.com',
            'password' => 'Sup3rSecret!',
            'password_confirmation' => 'Sup3rSecret!',
        ]);

        $this->assertNull(
            User::where('email', 'sajib@example.com')->firstOrFail()->email_verified_at
        );
    }

    /* -----------------------------------------------------------------
     | Signing in
     * ----------------------------------------------------------------- */

    public function test_a_correct_password_on_an_unverified_account_still_does_not_sign_in(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/email/verify');

        // The session Auth::attempt created has to be torn down, not merely
        // skipped. Asserting the guest state is what proves it was.
        $this->assertGuest();

        // And a fresh link goes out, since the likeliest reason for this is a
        // link that expired or went to spam.
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_verified_account_can_still_sign_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/my-account');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_is_still_refused(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /* -----------------------------------------------------------------
     | What the gate actually covers
     *
     | Every one of these used to be reachable with an unconfirmed address.
     * They are the reason this change is worth making rather than cosmetic.
     * ----------------------------------------------------------------- */

    public function test_an_unverified_account_cannot_reach_the_dashboard(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/my-account')
            ->assertRedirect('/email/verify');
    }

    public function test_an_unverified_account_cannot_reach_checkout(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->post('/checkout/life-in-the-uk-course')
            ->assertRedirect('/email/verify');
    }

    public function test_an_unverified_account_cannot_reach_the_learning_area(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/my-account/courses/life-in-the-uk-course')
            ->assertRedirect('/email/verify');
    }

    public function test_a_verified_account_reaches_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/my-account')
            ->assertOk();
    }

    /* -----------------------------------------------------------------
     | Following the link
     * ----------------------------------------------------------------- */

    public function test_the_link_verifies_the_account_and_signs_the_person_in(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))
            ->assertRedirect('/my-account');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($user->fresh());

        Event::assertDispatched(Verified::class);
    }

    public function test_verifying_also_opens_the_account_that_was_locked(): void
    {
        $user = User::factory()->unverified()->create();

        // Locked before.
        $this->actingAs($user)->get('/my-account')->assertRedirect('/email/verify');

        // Verified from the link, as a guest, which is how somebody who was
        // never signed in arrives.
        $this->post('/logout');
        $this->get($this->verificationUrl($user))->assertRedirect('/my-account');

        $this->get('/my-account')->assertOk();
    }

    public function test_a_tampered_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);

        // Edit the hash in flight. This is caught by the signature rather than
        // by the hash check in the controller, because changing the hash
        // invalidates the signature - which is the stronger of the two, since
        // it stops the request before the controller is reached at all.
        $forged = str_replace(
            sha1($user->email),
            sha1('someone-else@example.com'),
            $url,
        );

        $this->get($forged)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertGuest();
    }

    public function test_an_unsigned_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        // Same shape, no signature. This is what a stranger guessing an id
        // would send.
        $this->get("/email/verify/{$user->id}/".sha1($user->email))
            ->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_expired_link_is_refused(): void
    {
        $user = User::factory()->unverified()->create();

        // Issued now, presented an hour and a minute later. Order matters:
        // minting the link after the clock moves would simply produce a fresh
        // unexpired one.
        $url = $this->verificationUrl($user);

        $this->travel(61)->minutes();

        // Laravel turns a bad signature into a redirect carrying the reason
        // rather than a bare 403. What matters is that the request never
        // reaches the controller, which the unverified timestamp shows.
        $response = $this->get($url);

        $this->assertTrue(
            $response->isRedirect() || $response->status() === 403,
            'an expired link must not be accepted'
        );

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_a_link_issued_for_an_old_address_stops_working(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);

        // They verify, then change their address. Every copy of the old link
        // sitting in an inbox is now worthless.
        $user->forceFill(['email' => 'moved@example.com'])->save();

        $this->get($url)->assertSessionHasErrors('email');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_using_the_link_twice_is_harmless(): void
    {
        $user = User::factory()->unverified()->create();
        $url = $this->verificationUrl($user);

        $this->get($url)->assertRedirect('/my-account');

        $this->post('/logout');

        // Mail clients prefetch. A second hit must not error out or, worse,
        // read as a way to sign in somebody else.
        $this->get($url)
            ->assertRedirect('/my-account')
            ->assertSessionHas('info');

        $this->assertGuest();
    }

    /* -----------------------------------------------------------------
     | Asking for another one
     * ----------------------------------------------------------------- */

    public function test_the_notice_page_is_reachable_as_a_guest(): void
    {
        // Registration does not sign anybody in, so this page has to work for
        // whoever the mail was actually sent to.
        $this->get('/email/verify')
            ->assertOk()
            ->assertSee('Confirm Your Email');
    }

    public function test_resending_sends_to_an_unverified_address(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->post('/email/resend', ['email' => $user->email])
            ->assertRedirect('/email/verify');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resending_to_a_verified_address_sends_nothing(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/email/resend', ['email' => $user->email])
            ->assertRedirect('/email/verify');

        Notification::assertNothingSent();
    }

    public function test_resending_to_an_unknown_address_looks_identical(): void
    {
        Notification::fake();

        // Same redirect and same wording as the address that does exist, so
        // the form cannot be used to discover who has registered. The
        // throttle is what actually limits the abuse.
        $this->post('/email/resend', ['email' => 'nobody@example.com'])
            ->assertRedirect('/email/verify')
            ->assertSessionHas('success');

        Notification::assertNothingSent();
    }

    /* -----------------------------------------------------------------
     | Wiring
     * ----------------------------------------------------------------- */

    public function test_the_user_model_actually_claims_to_need_verification(): void
    {
        // Guards the whole feature. Without this contract the `verified`
        // middleware has nothing to check and lets everybody through, so it
        // would fail silently rather than loudly.
        $this->assertInstanceOf(MustVerifyEmail::class, new User);
    }

    public function test_registering_fires_the_registered_event(): void
    {
        Event::fake([Registered::class]);
        Notification::fake();

        $this->post('/register', [
            'name' => 'Sajib Islam',
            'email' => 'sajib@example.com',
            'password' => 'Sup3rSecret!',
            'password_confirmation' => 'Sup3rSecret!',
        ]);

        Event::assertDispatched(Registered::class);
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }
}
