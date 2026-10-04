<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Database\Seeders\CourseSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Owning proof of an email address, by code.
 *
 * The link this replaced could not be guessed at all, so its tests were about
 * forgery, tampering and expiry of a signed URL. A code can be guessed, so
 * most of what follows is about the ways guessing is made expensive: the hash
 * that is stored, the short life, the attempt counter and the lockout, and the
 * fact that a redeemed code cannot be replayed.
 */
class EmailVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The gate has to be proven on routes that resolve a real course from
        // the slug, so the catalogue has to exist first.
        $this->seed(CourseSeeder::class);

        /*
         * Both the redeem form and the resend form are throttled per IP, and
         * every request here comes from 127.0.0.1. The limiter is backed by the
         * array cache driver, which lives for the whole process rather than per
         * test, so without this the allowance is spent by an earlier test and
         * this one fails on a 429 instead of on the thing it is checking.
         */
        RateLimiter::clear('verify-code:127.0.0.1');
        RateLimiter::clear('resend-code:127.0.0.1');
    }

    /* -----------------------------------------------------------------
     | Signing up
     * ----------------------------------------------------------------- */

    public function test_registration_sends_the_code_and_signs_nobody_in(): void
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
            EmailVerificationCode::class,
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

        // And a fresh code goes out, since the likeliest reason for this is a
        // code that expired or was never opened.
        Notification::assertSentTo($user, EmailVerificationCode::class);
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
     |
     | Every one of these used to be reachable with an unconfirmed address.
     | They are the reason this change is worth making rather than cosmetic.
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
     | Entering the code
     * ----------------------------------------------------------------- */

    public function test_the_code_verifies_the_account_and_signs_the_person_in(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create();
        $code = $user->issueVerificationCode();

        $this->post('/email/verify', [
            'email' => $user->email,
            'code' => $code,
        ])->assertRedirect('/my-account');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertAuthenticatedAs($user->fresh());

        Event::assertDispatched(Verified::class);
    }

    public function test_a_code_with_the_wrong_digits_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $user->issueVerificationCode();

        $wrong = str_pad((string) ((int) $code === 0 ? 1 : ((int) $code + 1) % 1000000), 6, '0', STR_PAD_LEFT);

        $this->post('/email/verify', [
            'email' => $user->email,
            'code' => $wrong,
        ])->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertGuest();
    }

    public function test_a_code_is_required_to_be_six_digits(): void
    {
        $user = User::factory()->unverified()->create();
        $user->issueVerificationCode();

        $this->post('/email/verify', [
            'email' => $user->email,
            'code' => '123',
        ])->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_leading_zeroes_are_preserved(): void
    {
        // A code may legitimately be 000123. Storing it as an integer anywhere
        // would silently turn that into 123 and the user's entry would fail.
        $user = User::factory()->unverified()->create();

        $this->forceCode($user, '000123');

        $this->post('/email/verify', [
            'email' => $user->email,
            'code' => '000123',
        ])->assertRedirect('/my-account');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    /* -----------------------------------------------------------------
     | What stops it being guessed
     *
     | This is the part with no equivalent in the link version, and the reason
     | the code is not simply a shorter version of what came before.
     * ----------------------------------------------------------------- */

    public function test_only_a_hash_of_the_code_is_stored(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $user->issueVerificationCode();

        $this->assertNotSame($code, $user->fresh()->verification_code_hash);
        $this->assertSame($this->digest($user, $code), $user->fresh()->verification_code_hash);

        // And it must not be reachable through serialisation, which is how it
        // would end up in an API response or a queued payload.
        $this->assertArrayNotHasKey('verification_code_hash', $user->fresh()->toArray());
        $this->assertStringNotContainsString(
            $code,
            json_encode($user->fresh()->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    /**
     * The stored value is a keyed HMAC, not a bare hash of the code.
     *
     * A plain `hash('sha256', $code)` is not much of a secret for six digits.
     * There are a million possible codes, so anybody holding a copy of the
     * users table - a backup, a replica, an over-broad SQL grant, and none of
     * those need write access - can hash all a million and look for a match in
     * under a second. Keying it with the application key, which never leaves
     * the server, means the table on its own is worth nothing.
     *
     * This test is what makes the difference visible: it asserts the stored
     * value is *not* the bare hash, so a later "simplification" back to
     * hash('sha256', ...) fails here rather than in production.
     */
    public function test_the_stored_code_cannot_be_reversed_without_the_application_key(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $user->issueVerificationCode();

        $stored = $user->fresh()->verification_code_hash;

        $this->assertNotSame(hash('sha256', $code), $stored, 'A bare SHA-256 is brute-forceable.');
        $this->assertSame(
            hash_hmac('sha256', $user->id.'|'.$code, config('app.key')),
            $stored,
        );

        // The key is bound in twice: the account id is in the message, so two
        // accounts sent the same six digits do not produce the same row value
        // and one account's code cannot be validated against another's.
        $other = User::factory()->unverified()->create();

        $this->assertNotSame(
            $user->verificationCodeDigestForTest($code),
            $other->verificationCodeDigestForTest($code),
        );

        // And a different key gives a different digest, which is the whole
        // point of using one.
        $this->assertNotSame(
            $stored,
            hash_hmac('sha256', $user->id.'|'.$code, 'a-different-app-key'),
        );
    }

    /**
     * The HMAC change must not have broken the lockout, which is the other
     * thing standing between a code and a guessed address.
     */
    public function test_wrong_codes_are_still_counted_and_locked_out(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $code = $user->issueVerificationCode();

        $max = (int) config('auth.verification_code.max_attempts', 5);

        for ($i = 0; $i < $max; $i++) {
            $this->post('/email/verify', ['email' => $user->email, 'code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->assertTrue($user->fresh()->verificationCodeLocked());
        $this->assertNull($user->fresh()->email_verified_at);

        // A correct code is refused too, because the lockout is on the code
        // rather than on the count of wrong answers alone.
        $this->post('/email/verify', ['email' => $user->email, 'code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $user->issueVerificationCode();

        $this->travel(config('auth.verification_code.expire') + 1)->minutes();

        $this->post('/email/verify', [
            'email' => $user->email,
            'code' => $code,
        ])->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_wrong_attempts_are_counted_and_the_code_locks_out(): void
    {
        $user = User::factory()->unverified()->create();
        $code = $user->issueVerificationCode();

        $max = config('auth.verification_code.max_attempts');

        // One short of the allowance: still refused, still counted.
        for ($i = 1; $i < $max; $i++) {
            $this->post('/email/verify', ['email' => $user->email, 'code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->assertSame($max - 1, $user->fresh()->verification_code_attempts);

        // The last one trips the lockout...
        $this->post('/email/verify', ['email' => $user->email, 'code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertTrue($user->fresh()->verificationCodeLocked());

        // ...and the correct code is now refused too, which is the whole point.
        $this->post('/email/verify', ['email' => $user->email, 'code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertGuest();
    }

    public function test_a_new_code_clears_the_attempt_counter(): void
    {
        // Somebody who fumbled five guesses is not an attacker, and locking
        // them out of their own account is the wrong answer.
        $user = User::factory()->unverified()->create();
        $user->issueVerificationCode();

        $this->post('/email/verify', ['email' => $user->email, 'code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame(1, $user->fresh()->verification_code_attempts);

        $code = $user->issueVerificationCode();

        $this->assertSame(0, $user->fresh()->verification_code_attempts);

        $this->post('/email/verify', ['email' => $user->email, 'code' => $code])
            ->assertRedirect('/my-account');
    }

    public function test_the_lockout_expires(): void
    {
        $user = User::factory()->unverified()->create();
        $user->issueVerificationCode();

        $max = config('auth.verification_code.max_attempts');
        for ($i = 0; $i < $max; $i++) {
            $this->post('/email/verify', ['email' => $user->email, 'code' => '000000']);
        }

        $this->assertTrue($user->fresh()->verificationCodeLocked());

        $this->travel(config('auth.verification_code.lockout_minutes') + 1)->minutes();

        $this->assertFalse($user->fresh()->verificationCodeLocked());
    }

    public function test_a_redeemed_code_cannot_be_replayed(): void
    {
        // The old link could be prefetched by a mail client; a code copied into
        // a shared thread must be equally spent afterwards.
        $user = User::factory()->unverified()->create();
        $code = $user->issueVerificationCode();

        $this->post('/email/verify', ['email' => $user->email, 'code' => $code])
            ->assertRedirect('/my-account');

        $this->post('/logout');

        // Replay lands on the "already verified" path rather than an error,
        // which is the same harmless outcome the link had. What matters is that
        // it does not sign anybody in, and that the code is genuinely spent.
        $this->post('/email/verify', ['email' => $user->email, 'code' => $code])
            ->assertRedirect('/my-account')
            ->assertSessionHas('info');

        $this->assertGuest();

        // The hash is gone, not merely marked used.
        $this->assertNull($user->fresh()->verification_code_hash);

        // And so a replayed code cannot verify somebody else's fresh account
        // either, because there is no stored value left to compare against.
        $other = User::factory()->unverified()->create();

        $this->post('/logout');
        $this->post('/email/verify', ['email' => $other->email, 'code' => $code])
            ->assertSessionHasErrors('code');

        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_issuing_a_second_code_invalidates_the_first(): void
    {
        // Two live codes at once would leave the recipient holding one the
        // server rejects, with no way to tell that from a mistype.
        $user = User::factory()->unverified()->create();

        $first = $user->issueVerificationCode();
        $second = $user->issueVerificationCode();

        $this->post('/email/verify', ['email' => $user->email, 'code' => $first])
            ->assertSessionHasErrors('code');

        $this->post('/email/verify', ['email' => $user->email, 'code' => $second])
            ->assertRedirect('/my-account');
    }

    /* -----------------------------------------------------------------
     | Not telling an anonymous visitor who has an account
     * ----------------------------------------------------------------- */

    public function test_an_unknown_address_fails_exactly_like_a_wrong_code(): void
    {
        $user = User::factory()->unverified()->create();
        $user->issueVerificationCode();

        $this->post('/email/verify', [
            'email' => 'nobody@example.com',
            'code' => '123456',
        ])->assertSessionHasErrors('code');
        $unknownMessage = session('errors')->first('code');

        $this->post('/email/verify', [
            'email' => $user->email,
            'code' => '123456',
        ])->assertSessionHasErrors('code');
        $wrongMessage = session('errors')->first('code');

        // Same message. If these diverge, the form enumerates.
        $this->assertSame($wrongMessage, $unknownMessage);
    }

    public function test_a_verified_account_is_told_so_rather_than_failing(): void
    {
        // The one answer that may differ, because it reveals nothing an
        // attacker did not already supply.
        $user = User::factory()->create();

        $this->post('/email/verify', [
            'email' => $user->email,
            'code' => '123456',
        ])->assertRedirect('/my-account')
            ->assertSessionHas('info');
    }

    /* -----------------------------------------------------------------
     | Asking for another
     * ----------------------------------------------------------------- */

    public function test_the_notice_page_is_reachable_as_a_guest(): void
    {
        // Registration does not sign anybody in, so this page has to work for
        // whoever the code was actually sent to.
        $this->get('/email/verify')
            ->assertOk()
            ->assertSee('Confirm Your Email')
            ->assertSee('Verification Code');
    }

    /* -----------------------------------------------------------------
     | The shape of the flow
     |
     | One question per page. These used to fail and are here to stop the two
     | email boxes on one screen coming back.
     * ----------------------------------------------------------------- */

    public function test_the_code_page_asks_for_a_code_and_nothing_else(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->post('/email/resend', ['email' => $user->email]);

        $html = $this->get('/email/verify')->assertOk()->getContent();

        $this->assertStringContainsString('name="code"', $html);

        // No email field at all when the address is already known: one visible
        // input on the page, and it is the code. This is the shape the earlier
        // version got wrong, when it asked for an address that was already
        // known and stacked a second form underneath.
        $this->assertSame(0, substr_count($html, 'name="email"'));
        $this->assertSame(0, substr_count($html, 'type="email"'));
        $this->assertSame(1, substr_count($html, '<form'));
    }

    public function test_the_resend_page_is_the_only_page_with_an_email_box(): void
    {
        $this->get('/email/resend')
            ->assertOk()
            ->assertSee('Send a New Code');

        $html = $this->get('/email/resend')->getContent();

        $this->assertStringContainsString('type="email" id="email"', $html);
        // And it is the address that is asked for, not the code.
        $this->assertStringNotContainsString('name="code"', $html);
    }

    public function test_the_two_pages_link_to_each_other(): void
    {
        // A link rather than a stacked second form, so neither page competes
        // with the other for attention.
        $this->get('/email/verify')
            ->assertOk()
            ->assertSee(route('verification.resend.form'), false);

        $this->get('/email/resend')
            ->assertOk()
            ->assertSee(route('verification.notice'), false);
    }

    public function test_the_address_is_shown_in_full_on_screen(): void
    {
        // The address is no longer masked - it's shown in full so the user can
        // confirm it's correct without ambiguity.
        $user = User::factory()->unverified()->create(['email' => 'student@example.com']);

        $this->post('/email/resend', ['email' => $user->email]);

        $html = $this->get('/email/verify')->assertOk()->getContent();

        $this->assertStringContainsString('student@example.com', $html);
        $this->assertStringNotContainsString('s••••@example.com', $html);
    }

    public function test_a_short_local_part_is_shown_in_full(): void
    {
        // Short local parts are also shown in full.
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);

        $this->post('/email/resend', ['email' => $user->email]);

        $html = $this->get('/email/verify')->assertOk()->getContent();

        $this->assertStringContainsString('a@example.com', $html);
        $this->assertStringNotContainsString('a•@example.com', $html);
    }

    public function test_the_code_page_works_from_the_session_alone(): void
    {
        // The address is remembered by whoever sent the code, so submitting
        // the form must not require it back.
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // Resending is what puts the address in the session, and it mints the
        // code - the same two effects the rendered pages rely on.
        $this->post('/email/resend', ['email' => $user->email]);

        $code = $this->capturedCode($user);

        // Only the code is posted, exactly as the rendered form does.
        $this->post('/email/verify', ['code' => $code])
            ->assertRedirect('/my-account');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_a_missing_address_sends_the_person_to_the_resend_page(): void
    {
        // Session gone and no address supplied: there is nothing to compare the
        // code against, so the only honest answer is to ask for one.
        $this->post('/email/verify', ['code' => '123456'])
            ->assertRedirect(route('verification.resend.form'));
    }

    public function test_the_code_field_shows_no_sample_value(): void
    {
        // A placeholder of 123456 was shipped here and read as the code that
        // had been sent: it was clicked straight through, then typed in as
        // though it were real. Nothing on this field may look like a code, so
        // the guidance has to be words.
        $response = $this->get('/email/verify')->assertOk();

        $this->assertStringNotContainsString(
            'placeholder',
            $this->inputFor($response->getContent(), 'code'),
        );

        $this->assertStringContainsString(
            'Enter the six digit code that we sent to your mail.',
            $response->getContent(),
        );
    }

    /**
     * The real digits of the code currently outstanding for this user.
     *
     * Only a hash is stored, so the code genuinely cannot be read back out of
     * the database - which is the point of storing a hash, and the reason
     * this goes to the notification instead. That it can be recovered this way
     * in a test is a fact about the test, not about the model.
     */
    private function capturedCode(User $user): string
    {
        $issued = $user->fresh()->verification_code_hash;

        $this->assertNotNull($issued, 'no code is outstanding for this user');

        $captured = null;

        Notification::assertSentTo(
            $user,
            EmailVerificationCode::class,
            function (EmailVerificationCode $notification) use ($user, $issued, &$captured) {
                if ($this->digest($user, $notification->code) === $issued) {
                    $captured = $notification->code;
                }

                return true;
            },
        );

        $this->assertNotNull($captured, 'the outstanding code was not among those sent');

        return $captured;
    }

    /**
     * Pull out one input element by its id, for asserting on its attributes.
     */
    private function inputFor(string $html, string $id): string
    {
        preg_match('/<input[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>/', $html, $matches);

        $this->assertNotEmpty($matches, "no input found with id [{$id}]");

        return $matches[0];
    }

    public function test_resending_sends_to_an_unverified_address(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->post('/email/resend', ['email' => $user->email])
            ->assertRedirect('/email/verify');

        Notification::assertSentTo($user, EmailVerificationCode::class);
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

    public function test_resending_does_not_send_to_a_locked_account(): void
    {
        // Otherwise asking for a new code is how the attempt counter gets
        // thrown away and the lockout becomes advisory.
        //
        // The attempt counter is driven directly rather than through the form:
        // the redeem route is throttled per IP at 10/minute, so driving it
        // through HTTP here would trip the throttle before the allowance ran
        // out, and the test would be asserting a 429 rather than the lockout.
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $user->issueVerificationCode();

        $max = config('auth.verification_code.max_attempts');
        $user->forceFill(['verification_code_attempts' => $max])->save();

        $this->assertTrue($user->fresh()->verificationCodeLocked());

        $this->post('/email/resend', ['email' => $user->email])
            ->assertRedirect('/email/verify');

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

    public function test_the_code_carries_the_digits_that_were_mailed(): void
    {
        // Proves the notification carries the code that was actually stored,
        // rather than a second, independently generated one.
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->post('/email/resend', ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            EmailVerificationCode::class,
            function (EmailVerificationCode $notification) use ($user) {
                return $notification->code === $user->fresh()->verification_code_hash
                    ? false
                    : $user->fresh()->verificationCodeMatches($notification->code);
            },
        );
    }

    /**
     * Pin a known code, so leading zeroes can be tested.
     */
    private function forceCode(User $user, string $code): void
    {
        $user->forceFill([
            'verification_code_hash' => $this->digest($user, $code),
            'verification_code_sent_at' => now(),
            'verification_code_attempts' => 0,
            'verification_code_locked_until' => null,
        ])->save();
    }

    /**
     * The stored form of a code, worked out the same way the model does.
     *
     * Deliberately not `$user->verificationCodeMatches()`, which would make
     * these assertions circular: the test has to state independently what the
     * stored value ought to be, or it proves only that the model agrees with
     * itself.
     */
    private function digest(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$code, config('app.key'));
    }
}
