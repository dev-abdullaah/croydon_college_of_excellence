<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Services\StripeService;
use Database\Seeders\CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Stripe\Checkout\Session;
use Tests\TestCase;

/**
 * The journey: Buy, account, code, order review, payment.
 *
 * These tests walk the path a real customer walks, rather than testing each
 * controller in isolation. The whole point of the change was that the course
 * somebody picked survives the four screens in between, and that is a property
 * of the sequence - any single page works fine on its own while the course is
 * quietly forgotten somewhere in the middle.
 */
class CheckoutJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'stripe.key' => 'pk_test_123',
            'stripe.secret' => 'sk_test_123',
            'stripe.webhook.secret' => 'whsec_test_secret',
            'stripe.prices.course' => 'price_test_course',
            'stripe.prices.mock_tests' => 'price_test_mock_tests',
        ]);

        $this->seed(CourseSeeder::class);

        // No test in here may reach the network. A fake session is returned
        // for any attempt to create one, and any attempt to look one up
        // answers "still open", so the reuse path is exercised without Stripe.
        $mock = Mockery::mock(StripeService::class)->makePartial();

        $mock->shouldReceive('createCheckoutSession')->andReturnUsing(
            function (array $params) {
                $id = 'cs_test_'.$params['metadata']['course_slug'];

                return Session::constructFrom([
                    'id' => $id,
                    'object' => 'checkout.session',
                    'url' => "https://checkout.stripe.com/c/pay/{$id}",
                ]);
            }
        );

        $mock->shouldReceive('retrieveCheckoutSession')->andReturnUsing(
            fn (string $sessionId) => Session::constructFrom([
                'id' => $sessionId,
                'object' => 'checkout.session',
                'status' => 'open',
                'payment_status' => 'unpaid',
                'url' => "https://checkout.stripe.com/c/pay/{$sessionId}",
            ])
        );

        $this->app->instance(StripeService::class, $mock);
    }

    /* -----------------------------------------------------------------
     | The whole thing, start to finish
     | ----------------------------------------------------------------- */

    /**
     * A guest clicks Buy and comes out the other end at the payment page.
     *
     * Every hop is asserted, because the failure this is here to catch is a
     * hop that silently drops the course: the customer arrives at the review
     * page and the thing they chose is gone, with no error anywhere.
     */
    public function test_a_guest_can_go_from_buy_to_stripe_without_losing_the_course(): void
    {
        $course = $this->course('life-in-the-uk-course');

        // 1. Buy. A guest is sent to make an account, and the course is
        //    remembered.
        $this->get(route('checkout.start', $course))
            ->assertRedirect(route('register'))
            ->assertSessionHas('checkout.intended_course', 'life-in-the-uk-course');

        // 2. The register page says what the account is for, rather than
        //    being a blank form with no reason attached to it.
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Life in the UK Course')
            ->assertSee('£99')
            ->assertSee('Create account')
            ->assertSee('Verify email')
            ->assertSee('Check order')
            ->assertSee('Pay');

        // 3. The account is created and stays unverified: no access, no
        //    sign-in, and a code on its way.
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Sam Learner',
            'email' => 'sam@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect(route('verification.notice'));

        $this->assertGuest();

        $user = User::where('email', 'sam@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->hasPurchased($course));

        // 4. The code page, and the course is still there.
        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Check order')
            ->assertSee('Pay');

        // 5. The code is accepted, the account is verified and they are signed
        //    in - and they land on the review, not the dashboard.
        $this->post(route('verification.verify'), ['code' => $this->capturedCode($user)])
            ->assertRedirect(route('checkout.review', $course));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertNotNull($user->fresh()->email_verified_at);

        // 6. The review page shows the right course at the right price.
        $this->get(route('checkout.review', $course))
            ->assertOk()
            ->assertSee('Check Your Order')
            ->assertSee('Life in the UK Course')
            ->assertSee('£99')
            ->assertSee('What happens next')
            ->assertSee('Terms and Refund Policy');

        // 7. Consent given, Stripe opens.
        $this->post(route('checkout.store', $course), ['consent' => '1'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_life-in-the-uk-course');
    }

    /* -----------------------------------------------------------------
     | checkout.start decides where somebody actually is
     | ----------------------------------------------------------------- */

    public function test_the_start_route_sends_each_kind_of_visitor_to_the_right_step(): void
    {
        $course = $this->course('life-in-the-uk-course');

        // A guest: make an account first.
        $this->get(route('checkout.start', $course))
            ->assertRedirect(route('register'));

        // Signed in but never verified: the code page, with the address
        // remembered so the code form is a single field.
        Notification::fake();

        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->get(route('checkout.start', $course))
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('verification.email', $unverified->email);

        Notification::assertSentTo($unverified, EmailVerificationCode::class);

        // Signed in and verified: straight to the order review.
        $verified = User::factory()->create();

        $this->actingAs($verified)
            ->get(route('checkout.start', $course))
            ->assertRedirect(route('checkout.review', $course));

        // Already own it: their account, told why they are not being sold it.
        $this->markAsPaid($verified, $course);

        $this->actingAs($verified)
            ->get(route('checkout.start', $course))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('info');
    }

    /**
     * An inactive course is not for sale, from the new entry point either.
     */
    public function test_the_start_route_is_a_404_for_an_inactive_course(): void
    {
        $course = $this->course('24-mock-tests');
        $course->update(['is_active' => false]);

        $this->get(route('checkout.start', $course))->assertNotFound();
    }

    /**
     * checkout.start is a GET because it is safe: it changes the session and
     * nothing else. It must never open a payment, even for somebody who is
     * signed in, verified and ready to buy.
     */
    public function test_the_start_route_never_creates_a_payment(): void
    {
        $course = $this->course('life-in-the-uk-course');

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('checkout.start', $course))->assertRedirect();

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('checkout.start', $course))
            ->assertRedirect();

        $this->assertSame(0, Purchase::count());
    }

    /**
     * It is rate limited, because it is a public route that sends real emails
     * for anybody who reaches it unverified.
     */
    public function test_the_start_route_is_throttled(): void
    {
        $course = $this->course('life-in-the-uk-course');
        $user = User::factory()->unverified()->create();

        Notification::fake();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)
                ->get(route('checkout.start', $course))
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->get(route('checkout.start', $course))
            ->assertStatus(429);
    }

    /**
     * A slug that no longer names an active course resolves to nothing.
     *
     * This is why the session holds a slug and not a URL. Whatever is put in
     * the session, the only thing that can come back out is a course that
     * exists and is for sale.
     */
    public function test_a_slug_that_no_longer_resolves_leads_nowhere(): void
    {
        $user = User::factory()->create();

        $this->withSession(['checkout.intended_course' => '../../etc/passwd'])
            ->get(route('verification.notice'))
            ->assertOk();

        // A retired course is not resumed either.
        $course = $this->course('24-mock-tests');
        $course->update(['is_active' => false]);

        $this->actingAs($user)
            ->get(route('checkout.review', $course))
            ->assertNotFound();
    }

    /* -----------------------------------------------------------------
     | Signing in mid-journey
     | ----------------------------------------------------------------- */

    /**
     * A returning customer who started a purchase, lost the tab, and came
     * back later is taken to the review page rather than the dashboard.
     */
    public function test_signing_in_with_an_intended_course_goes_to_the_review(): void
    {
        $course = $this->course('24-mock-tests');
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->withSession(['checkout.intended_course' => $course->slug])
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'correct-horse-battery',
            ])
            ->assertRedirect(route('checkout.review', $course));
    }

    /**
     * With no purchase in progress, signing in goes to the dashboard exactly
     * as it always did.
     */
    public function test_signing_in_without_an_intended_course_goes_to_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-horse-battery',
        ])->assertRedirect(route('dashboard'));
    }

    /**
     * A course they have already paid for is not worth a review page.
     */
    public function test_signing_in_with_an_owned_intended_course_goes_to_the_dashboard(): void
    {
        $course = $this->course('24-mock-tests');
        $user = User::factory()->create(['password' => 'correct-horse-battery']);

        $this->markAsPaid($user, $course);

        $this->withSession(['checkout.intended_course' => $course->slug])
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'correct-horse-battery',
            ])
            ->assertRedirect(route('dashboard'));
    }

    /**
     * The course survives the session wipe an unverified login performs.
     *
     * LoginController calls session()->invalidate() to clear the session id
     * and everything in it. That is correct, and it used to throw away the one
     * piece of session state that was about the next purchase rather than
     * about this sign-in - so somebody who was sent a code after a sign-in
     * attempt was then asked to pick their course again.
     */
    public function test_the_intended_course_survives_the_session_invalidation(): void
    {
        $course = $this->course('life-in-the-uk-course');

        Notification::fake();

        $user = User::factory()->unverified()->create(['password' => 'correct-horse-battery']);

        $this->withSession(['checkout.intended_course' => $course->slug])
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'correct-horse-battery',
            ])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('checkout.intended_course', $course->slug)
            ->assertSessionHas('verification.email', $user->email);

        // Still a guest: a correct password is not proof of the address.
        $this->assertGuest();

        // And the journey still finishes.
        $this->post(route('verification.verify'), ['code' => $this->capturedCode($user)])
            ->assertRedirect(route('checkout.review', $course));
    }

    /* -----------------------------------------------------------------
     | Signing up twice
     | ----------------------------------------------------------------- */

    /**
     * Somebody who abandoned sign-up comes back and finishes it.
     *
     * The address is taken, so a unique rule would have refused them and told
     * them the email was in use - which is a strange thing to be told about an
     * account they made themselves and never finished. Instead the name and
     * password are updated, a code goes out, and they carry on.
     */
    public function test_registering_again_with_an_unverified_email_resumes_the_account(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create([
            'name' => 'Old Name',
            'password' => 'the-first-password',
        ]);

        $this->travel(2)->minutes();

        $this->post(route('register'), [
            'name' => 'New Name',
            'email' => $user->email,
            'password' => 'a-completely-different-password',
            'password_confirmation' => 'a-completely-different-password',
        ])->assertRedirect(route('verification.notice'));

        // No second row.
        $this->assertSame(1, User::where('email', $user->email)->count());

        $user->refresh();

        $this->assertSame('New Name', $user->name);
        $this->assertTrue(Hash::check('a-completely-different-password', $user->password));
        $this->assertFalse(Hash::check('the-first-password', $user->password));
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, EmailVerificationCode::class);
        $this->assertTrue($user->verificationCodeMatches($this->capturedCode($user)));
    }

    /**
     * A finished account is not overwritten.
     *
     * This is the boundary the resume path is allowed to work inside. It only
     * ever touches accounts that have never been verified, because an address
     * nobody has claimed is not an account anybody can lose - whereas resetting
     * a password here on a live account would be account takeover for anyone
     * who knew the address.
     */
    public function test_registering_again_with_a_verified_email_changes_nothing(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'The Real Owner',
            'password' => 'their-own-password',
        ]);

        $this->post(route('register'), [
            'name' => 'Someone Else',
            'email' => $user->email,
            'password' => 'a-password-i-made-up',
            'password_confirmation' => 'a-password-i-made-up',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('info', 'You already have an account with this email. Please log in.');

        $user->refresh();

        $this->assertSame('The Real Owner', $user->name);
        $this->assertTrue(Hash::check('their-own-password', $user->password));
        $this->assertNotNull($user->email_verified_at);

        // No code was mailed, and nobody is signed in.
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    /**
     * The chosen course is kept across the "you already have an account" hop.
     */
    public function test_a_verified_duplicate_sign_up_keeps_the_intended_course(): void
    {
        $course = $this->course('life-in-the-uk-course');
        $user = User::factory()->create();

        $this->withSession(['checkout.intended_course' => $course->slug])
            ->post(route('register'), [
                'name' => 'Someone',
                'email' => $user->email,
                'password' => 'a-password-i-made-up',
                'password_confirmation' => 'a-password-i-made-up',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('checkout.intended_course', $course->slug);
    }

    /* -----------------------------------------------------------------
     | The resend cooldown
     | ----------------------------------------------------------------- */

    /**
     * Asking twice in a minute does not mean two emails.
     *
     * Each code is a real email, and a mailbox flooded with them is a problem
     * for the recipient. The window is per account rather than per IP, because
     * it is the mailbox that suffers.
     */
    public function test_a_second_code_is_not_mailed_inside_the_cooldown(): void
    {
        Notification::fake();

        config(['courses.code_resend_cooldown_seconds' => 60]);

        $user = User::factory()->unverified()->create();

        $this->post(route('verification.resend'), ['email' => $user->email])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success');

        $firstSentAt = $user->fresh()->verification_code_sent_at;

        // Ten seconds later, still inside the window.
        $this->travel(10)->seconds();

        $this->post(route('verification.resend'), ['email' => $user->email])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('info');

        $this->assertTrue(
            $firstSentAt->equalTo($user->fresh()->verification_code_sent_at),
            'No new code may be issued inside the cooldown.',
        );

        // Past the window, it mails again.
        $this->travel(60)->seconds();

        $this->post(route('verification.resend'), ['email' => $user->email])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success');

        $this->assertTrue(
            $user->fresh()->verification_code_sent_at->isAfter($firstSentAt),
        );
    }

    /**
     * The register-resume path obeys the same cooldown, and says so honestly
     * rather than claiming a code was sent.
     */
    public function test_the_register_resume_path_obeys_the_cooldown(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        // A code went out on the abandoned attempt a moment ago.
        $user->forceFill(['verification_code_sent_at' => now()])->save();

        $this->post(route('register'), [
            'name' => 'Back Again',
            'email' => $user->email,
            'password' => 'a-new-password-here',
            'password_confirmation' => 'a-new-password-here',
        ])->assertRedirect(route('verification.notice'))
            ->assertSessionHas('info');

        Notification::assertNothingSent();
    }

    /**
     * The resend form must not become a way of finding out who has an account.
     *
     * Every address gets the same answer, and the cooldown message - which
     * would otherwise only ever appear for a real account - is only shown when
     * we know a code is genuinely outstanding.
     */
    public function test_the_resend_form_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();

        config(['courses.code_resend_cooldown_seconds' => 0]);

        $user = User::factory()->unverified()->create();

        $this->post(route('verification.resend'), ['email' => $user->email])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success', 'If that address needs verifying, a fresh code is on its way.');

        $this->post(route('verification.resend'), ['email' => 'nobody@example.com'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success', 'If that address needs verifying, a fresh code is on its way.');
    }

    /* -----------------------------------------------------------------
     | The review page
     | ----------------------------------------------------------------- */

    /**
     * The price on the review page is the server's price.
     */
    public function test_the_review_page_shows_the_server_price(): void
    {
        $user = User::factory()->create();
        $course = $this->course('24-mock-tests');

        $this->actingAs($user)->get(route('checkout.review', $course))
            ->assertOk()
            ->assertSee('£49')
            ->assertDontSee('£1.00');
    }

    /**
     * The consent is a real requirement, not a decoration.
     *
     * An unchecked box, a missing field, and a value that is not a
     * conventional "yes" are all refused - and none of them creates a Stripe
     * session, because a session created without consent would record an
     * acceptance that never happened.
     */
    public function test_the_review_page_refuses_a_post_without_consent(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        foreach ([[], ['consent' => ''], ['consent' => '0'], ['consent' => 'no']] as $payload) {
            $this->actingAs($user)
                ->post(route('checkout.store', $course), $payload)
                ->assertSessionHasErrors('consent');
        }

        $this->assertSame(0, Purchase::count());
    }

    /**
     * Somebody who already owns the course is sent to it rather than shown a
     * page offering to sell it to them.
     */
    public function test_the_review_page_blocks_a_course_the_user_already_owns(): void
    {
        $course = $this->course('life-in-the-uk-course');
        $user = User::factory()->create();

        $this->markAsPaid($user, $course);

        $this->actingAs($user)->get(route('checkout.review', $course))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)->post(route('checkout.store', $course), ['consent' => '1'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(1, Purchase::count());
    }

    /**
     * The review page is behind the same two gates as the payment it leads to.
     */
    public function test_the_review_page_is_behind_auth_and_verified(): void
    {
        $course = $this->course('life-in-the-uk-course');

        $this->get(route('checkout.review', $course))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('checkout.review', $course))
            ->assertRedirect(route('verification.notice'));
    }

    /**
     * The consent wording is one config value, not something buried in a
     * template, because it is a legal statement the owner has to be able to
     * change without finding a Blade file.
     */
    public function test_the_consent_wording_comes_from_configuration(): void
    {
        config(['courses.consent_text' => 'A wording the owner has changed.']);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('checkout.review', $this->course('life-in-the-uk-course')))
            ->assertOk()
            ->assertSee('A wording the owner has changed.');
    }

    /* -----------------------------------------------------------------
     | After paying
     | ----------------------------------------------------------------- */

    /**
     * The account page marks the course that has just been bought.
     */
    public function test_the_dashboard_highlights_the_course_that_was_just_bought(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->markAsPaid($user, $course);

        $this->actingAs($user)
            ->withSession(['highlight_course' => $course->slug])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('New')
            ->assertSee('Payment received. This is yours.')
            ->assertSee('Start learning');

        // It is a one-off. A badge that never goes away stops meaning anything.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Payment received. This is yours.');
    }

    /**
     * A course the account does not own cannot be highlighted, whatever the
     * session says.
     */
    public function test_the_dashboard_will_not_highlight_an_unowned_course(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['highlight_course' => 'life-in-the-uk-course'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Payment received. This is yours.')
            ->assertDontSee('badge bg-primary">New');
    }

    /**
     * A purchase somebody started and did not finish is a to-do, not an
     * error, and it is one row per course however many attempts are in the
     * table.
     */
    public function test_the_dashboard_lists_one_entry_per_unfinished_course(): void
    {
        $user = User::factory()->create();
        $course = $this->course('24-mock-tests');

        // Three attempts at one course.
        foreach (range(1, 3) as $i) {
            Purchase::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'stripe_checkout_session_id' => 'cs_test_attempt_'.$i,
                'amount' => $course->price,
                'currency' => $course->currency,
                'status' => Purchase::STATUS_PENDING,
            ]);
        }

        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $response->assertSee('Complete your purchase')
            ->assertSee('24 Mock Tests Package')
            ->assertSee(route('checkout.start', $course), escape: false)
            ->assertSee('Continue')
            // The old heading described the database rows rather than the
            // customer's situation.
            ->assertDontSee('Recent checkout attempts')
            ->assertDontSee('Try again');

        $this->assertSame(
            1,
            substr_count($response->getContent(), route('checkout.start', $course)),
            'One entry per course, not per attempt.',
        );
    }

    /**
     * A finished purchase is not on the to-do list.
     */
    public function test_the_dashboard_does_not_list_a_paid_course_as_unfinished(): void
    {
        $user = User::factory()->create();
        $course = $this->course('24-mock-tests');

        $this->markAsPaid($user, $course);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Complete your purchase');
    }

    /* -----------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------- */

    protected function course(string $slug): Course
    {
        return Course::where('slug', $slug)->firstOrFail();
    }

    protected function markAsPaid(User $user, Course $course, string $sessionId = 'cs_test_paid'): Purchase
    {
        return Purchase::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'stripe_checkout_session_id' => $sessionId,
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => Purchase::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    /**
     * The real digits of the code that was mailed to this user.
     *
     * Only a keyed hash is stored, so the code cannot be read back out of the
     * database - which is the point of storing a hash. That it can be recovered
     * this way is a fact about the test, not about the model.
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
                if (hash_hmac('sha256', $user->id.'|'.$notification->code, config('app.key')) === $issued) {
                    $captured = $notification->code;
                }

                return true;
            },
        );

        $this->assertNotNull($captured, 'the outstanding code was not among those sent');

        return $captured;
    }
}
