<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Services\StripeService;
use Database\Seeders\CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiConnectionException;
use Tests\TestCase;

class PaidCoursesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Test-mode Stripe settings. The webhook signing secret is a real one
        // so signature verification is genuinely exercised; anything that
        // would hit the network is faked per test.
        config([
            'stripe.key' => 'pk_test_123',
            'stripe.secret' => 'sk_test_123',
            'stripe.webhook.secret' => 'whsec_test_secret',
            'stripe.prices.course' => 'price_test_course',
            'stripe.prices.mock_tests' => 'price_test_mock_tests',
        ]);

        $this->seed(CourseSeeder::class);
    }

    /* -----------------------------------------------------------------
     | Catalogue and homepage
     | ----------------------------------------------------------------- */

    public function test_homepage_advertises_both_courses_with_prices(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Prepare For The Official Life in the UK Test')
            ->assertSee('Buy Life in the UK Course')
            ->assertSee('Buy 24 Mock Tests Package')
            ->assertSee('£99')
            ->assertSee('£49')
            ->assertSee('Life in the UK Lessons 1-10')
            ->assertSee('6 Classroom Mock Tests with teacher answer keys')
            ->assertSee('24 mock tests');
    }

    public function test_the_two_courses_are_distinct(): void
    {
        $course = $this->course('life-in-the-uk-course');
        $mocks = $this->course('24-mock-tests');

        $this->assertNotSame($course->id, $mocks->id);
        $this->assertSame(9900, $course->price);
        $this->assertSame(4900, $mocks->price);
        $this->assertSame('£99', $course->formattedPrice());
        $this->assertSame('£49', $mocks->formattedPrice());
        $this->assertSame('price_test_course', $course->stripePriceId());
        $this->assertSame('price_test_mock_tests', $mocks->stripePriceId());
        $this->assertCount(3, $course->documents);
        $this->assertCount(1, $mocks->documents);
    }

    public function test_every_catalogue_document_exists_on_disk(): void
    {
        $this->requireCourseFiles();

        Course::with('documents')->get()->each(function (Course $course) {
            $course->documents->each(function ($document) {
                $this->assertFileExists(
                    base_path('course-files/'.$document->filename),
                    "Missing course file: {$document->filename}"
                );
            });
        });
    }

    /**
     * A catalogue row may outlive its file, and the site has to cope.
     *
     * The .docx files were only ever present while the material was being laid
     * out. The learning area serves the same content from the JSON files, so a
     * missing file is a normal state: the download must fail cleanly, and the
     * account page must point the buyer at the content rather than at a dead
     * link.
     */
    public function test_a_missing_download_fails_cleanly_rather_than_erroring(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->markAsPaid($user, $course, 'cs_test_missing_file');

        $document = $course->documents->firstOrFail();

        if ($document->fileExists()) {
            $this->markTestSkipped('The source .docx is present, so there is no missing file to test.');
        }

        $this->actingAs($user)
            ->get("/my-account/downloads/{$document->id}")
            ->assertNotFound();

        // The account page must not offer a link that dead-ends, and must not
        // pretend the course has nothing in it.
        $this->actingAs($user)
            ->get('/my-account')
            ->assertOk()
            ->assertDontSee("/my-account/downloads/{$document->id}")
            ->assertSee($document->title);
    }

    /* -----------------------------------------------------------------
     | Authentication
     | ----------------------------------------------------------------- */

    public function test_a_visitor_can_register_and_reach_their_account(): void
    {
        $this->post('/register', [
            'name' => 'Sajib Islam',
            'email' => 'sajib@example.com',
            'password' => 'Sup3rSecret!',
            'password_confirmation' => 'Sup3rSecret!',
        ])->assertRedirect('/my-account');

        $this->assertAuthenticated();
        $this->get('/my-account')->assertOk()->assertSee('sajib@example.com');
    }

    public function test_a_visitor_can_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'learner@example.com',
            'password' => 'Sup3rSecret!',
        ]);

        $this->post('/login', [
            'email' => 'learner@example.com',
            'password' => 'Sup3rSecret!',
        ])->assertRedirect('/my-account');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rejected_with_bad_credentials(): void
    {
        User::factory()->create([
            'email' => 'learner@example.com',
            'password' => 'Sup3rSecret!',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'learner@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_guest_cannot_reach_the_account_page(): void
    {
        $this->get('/my-account')->assertRedirect('/login');
    }

    /* -----------------------------------------------------------------
     | Checkout
     | ----------------------------------------------------------------- */

    public function test_guests_cannot_start_checkout(): void
    {
        $this->post('/checkout/life-in-the-uk-course')->assertRedirect('/login');
    }

    public function test_checkout_uses_the_server_side_stripe_price_and_records_a_pending_purchase(): void
    {
        $user = User::factory()->create();

        $captured = [];
        $this->fakeStripe($captured);

        $this->actingAs($user)
            ->post('/checkout/life-in-the-uk-course')
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_life-in-the-uk-course');

        // The line item came from configuration, never from the request body.
        $this->assertSame('payment', $captured['mode']);
        $this->assertSame([['price' => 'price_test_course', 'quantity' => 1]], $captured['line_items']);
        $this->assertSame((string) $user->id, $captured['client_reference_id']);
        $this->assertSame((string) $user->id, $captured['metadata']['user_id']);
        $this->assertStringContainsString('session_id={CHECKOUT_SESSION_ID}', $captured['success_url']);

        $purchase = Purchase::where('stripe_checkout_session_id', 'cs_test_life-in-the-uk-course')->firstOrFail();

        $this->assertSame(Purchase::STATUS_PENDING, $purchase->status);
        $this->assertSame($user->id, $purchase->user_id);
        $this->assertSame(9900, $purchase->amount);
        $this->assertSame('gbp', $purchase->currency);
        $this->assertNull($purchase->paid_at);

        // Pending is not paid, so it unlocks nothing.
        $this->assertFalse($user->fresh()->hasPurchased('life-in-the-uk-course'));
    }

    public function test_client_supplied_prices_are_ignored(): void
    {
        $user = User::factory()->create();

        $captured = [];
        $this->fakeStripe($captured);

        $this->actingAs($user)->post('/checkout/24-mock-tests', [
            'price' => 1,
            'amount' => 1,
            'currency' => 'usd',
            'course_id' => 999,
        ])->assertRedirect();

        $this->assertSame([['price' => 'price_test_mock_tests', 'quantity' => 1]], $captured['line_items']);
        $this->assertSame(4900, Purchase::firstOrFail()->amount);
    }

    public function test_a_user_cannot_checkout_a_course_they_already_own(): void
    {
        $user = User::factory()->create();

        $this->markAsPaid($user, $this->course('24-mock-tests'), 'cs_test_existing');

        // Stripe is deliberately not faked here: reaching Stripe would be a bug.
        $this->actingAs($user)->post('/checkout/24-mock-tests')->assertRedirect('/my-account');

        $this->assertSame(1, Purchase::count());
    }

    public function test_an_inactive_course_cannot_be_bought(): void
    {
        $user = User::factory()->create();
        $this->course('24-mock-tests')->update(['is_active' => false]);

        $this->actingAs($user)
            ->post('/checkout/24-mock-tests')
            ->assertNotFound();
    }

    /* -----------------------------------------------------------------
     | Webhook
     | ----------------------------------------------------------------- */

    public function test_a_verified_webhook_records_the_purchase_and_grants_access(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event(
            'evt_completed_1',
            'checkout.session.completed',
            $this->sessionObject($user, $course, 'paid')
        ))->assertOk();

        $purchase = Purchase::firstOrFail();

        $this->assertSame(Purchase::STATUS_PAID, $purchase->status);
        $this->assertSame('cs_test_completed', $purchase->stripe_checkout_session_id);
        $this->assertSame('pi_test_intent', $purchase->stripe_payment_intent_id);
        $this->assertSame('cus_test_customer', $purchase->stripe_customer_id);
        $this->assertSame(9900, $purchase->amount);
        $this->assertSame('gbp', $purchase->currency);
        $this->assertSame('evt_completed_1', $purchase->stripe_event_id);
        $this->assertSame($user->email, $purchase->customer_email);
        $this->assertNotNull($purchase->paid_at);

        $this->assertTrue($user->fresh()->hasPurchased($course));
    }

    public function test_a_replayed_webhook_event_does_not_duplicate_the_purchase(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $event = $this->event('evt_replayed', 'checkout.session.completed', $this->sessionObject($user, $course, 'paid'));

        $this->sendWebhook($event)->assertOk();
        $this->sendWebhook($event)->assertOk();
        $this->sendWebhook($event)->assertOk();

        $this->assertSame(1, Purchase::count());
        $this->assertSame(1, StripeWebhookEvent::count());
        $this->assertNotNull(Purchase::firstOrFail()->paid_at);
    }

    public function test_two_distinct_events_for_one_session_still_produce_one_purchase(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event('evt_a', 'checkout.session.completed', $this->sessionObject($user, $course, 'paid')))
            ->assertOk();
        $this->sendWebhook($this->event('evt_b', 'checkout.session.async_payment_succeeded', $this->sessionObject($user, $course, 'paid')))
            ->assertOk();

        $this->assertSame(1, Purchase::count());
        $this->assertSame(Purchase::STATUS_PAID, Purchase::firstOrFail()->status);
    }

    public function test_a_completed_session_that_was_not_paid_does_not_grant_access(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event('evt_unpaid', 'checkout.session.completed', $this->sessionObject($user, $course, 'unpaid')))
            ->assertOk();

        $this->assertFalse($user->fresh()->hasPurchased($course));
        $this->assertSame(Purchase::STATUS_PENDING, Purchase::firstOrFail()->status);
    }

    public function test_a_failed_payment_is_recorded_as_failed(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        // The row checkout opened before the card was declined.
        Purchase::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'stripe_checkout_session_id' => 'cs_test_declined',
            'amount' => 9900,
            'currency' => 'gbp',
            'status' => Purchase::STATUS_PENDING,
        ]);

        $this->sendWebhook($this->event('evt_failed', 'payment_intent.payment_failed', [
            'id' => 'pi_test_intent',
            'object' => 'payment_intent',
            'metadata' => [
                'user_id' => (string) $user->id,
                'course_id' => (string) $course->id,
            ],
            'last_payment_error' => ['message' => 'Your card was declined.'],
        ]))->assertOk();

        $purchase = Purchase::firstOrFail();

        $this->assertSame(Purchase::STATUS_FAILED, $purchase->status);
        $this->assertSame('pi_test_intent', $purchase->stripe_payment_intent_id);
        $this->assertSame('cs_test_declined', $purchase->stripe_checkout_session_id);
        $this->assertSame('Your card was declined.', $purchase->failure_reason);
        $this->assertFalse($user->fresh()->hasPurchased($course));
    }

    public function test_a_declined_card_cannot_overturn_a_payment_that_already_succeeded(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event('evt_paid_first', 'checkout.session.completed', $this->sessionObject($user, $course, 'paid')))
            ->assertOk();

        $this->sendWebhook($this->event('evt_failed_later', 'payment_intent.payment_failed', [
            'id' => 'pi_test_intent',
            'object' => 'payment_intent',
            'metadata' => [
                'user_id' => (string) $user->id,
                'course_id' => (string) $course->id,
            ],
            'last_payment_error' => ['message' => 'Your card was declined.'],
        ]))->assertOk();

        $this->assertSame(1, Purchase::count());
        $this->assertSame(Purchase::STATUS_PAID, Purchase::firstOrFail()->status);
        $this->assertTrue($user->fresh()->hasPurchased($course));
    }

    public function test_a_failed_intent_we_never_started_is_ignored(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        // Signed by Stripe, but describing a payment this site never opened.
        $this->sendWebhook($this->event('evt_orphan', 'payment_intent.payment_failed', [
            'id' => 'pi_test_orphan',
            'object' => 'payment_intent',
            'metadata' => [
                'user_id' => (string) $user->id,
                'course_id' => (string) $course->id,
            ],
            'last_payment_error' => ['message' => 'Your card was declined.'],
        ]))->assertOk();

        $this->assertSame(0, Purchase::count());
        $this->assertFalse($user->fresh()->hasPurchased($course));
    }

    public function test_an_expired_session_is_marked_expired(): void
    {
        $user = User::factory()->create();

        Purchase::create([
            'user_id' => $user->id,
            'course_id' => $this->course('24-mock-tests')->id,
            'stripe_checkout_session_id' => 'cs_test_expired',
            'amount' => 4900,
            'currency' => 'gbp',
            'status' => Purchase::STATUS_PENDING,
        ]);

        $this->sendWebhook($this->event('evt_expired', 'checkout.session.expired', [
            'id' => 'cs_test_expired',
            'object' => 'checkout.session',
        ]))->assertOk();

        $this->assertSame(Purchase::STATUS_EXPIRED, Purchase::firstOrFail()->status);
        $this->assertFalse($user->fresh()->hasPurchased('24-mock-tests'));
    }

    public function test_a_refund_revokes_access(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event('evt_paid', 'checkout.session.completed', $this->sessionObject($user, $course, 'paid')))
            ->assertOk();

        $this->assertTrue($user->fresh()->hasPurchased($course));

        $this->sendWebhook($this->event('evt_refunded', 'charge.refunded', [
            'id' => 'ch_test_charge',
            'object' => 'charge',
            'payment_intent' => 'pi_test_intent',
            'amount_refunded' => 9900,
        ]))->assertOk();

        $purchase = Purchase::firstOrFail();
        $this->assertSame(Purchase::STATUS_REFUNDED, $purchase->status);
        $this->assertNotNull($purchase->refunded_at);
        $this->assertFalse($user->fresh()->hasPurchased($course));
    }

    public function test_the_webhook_rejects_a_bad_signature(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $payload = json_encode($this->event(
            'evt_forged',
            'checkout.session.completed',
            $this->sessionObject($user, $course, 'paid')
        ));

        $this->postRawWebhook($payload, 't='.time().',v1='.str_repeat('0', 64))
            ->assertStatus(403);

        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, StripeWebhookEvent::count());
    }

    public function test_the_webhook_rejects_a_payload_signed_with_another_secret(): void
    {
        $user = User::factory()->create();
        $payload = json_encode($this->event('evt_wrongkey', 'checkout.session.completed', $this->sessionObject($user, $this->course('life-in-the-uk-course'), 'paid')));

        $this->postRawWebhook($payload, $this->sign($payload, 'whsec_someone_elses_secret'))
            ->assertStatus(403);

        $this->assertSame(0, Purchase::count());
    }

    public function test_the_webhook_requires_a_signature_header(): void
    {
        $this->postRawWebhook('{"id":"evt_x","object":"event","type":"checkout.session.completed"}', null)
            ->assertStatus(400);
    }

    public function test_the_webhook_fails_closed_when_no_secret_is_configured(): void
    {
        config(['stripe.webhook.secret' => null]);

        $user = User::factory()->create();
        $payload = json_encode($this->event('evt_unconfigured', 'checkout.session.completed', $this->sessionObject($user, $this->course('life-in-the-uk-course'), 'paid')));

        $this->postRawWebhook($payload, $this->sign($payload, 'whsec_test_secret'))
            ->assertStatus(500);

        $this->assertSame(0, Purchase::count());
    }

    public function test_an_unrelated_event_is_acknowledged_without_side_effects(): void
    {
        $this->sendWebhook($this->event('evt_other', 'customer.created', [
            'id' => 'cus_test_customer',
            'object' => 'customer',
        ]))->assertOk();

        $this->assertSame(0, Purchase::count());
        $this->assertSame(1, StripeWebhookEvent::count());
    }

    /* -----------------------------------------------------------------
     | Protected content
     | ----------------------------------------------------------------- */

    public function test_guests_cannot_download_course_files_by_typing_the_url(): void
    {
        $document = $this->course('life-in-the-uk-course')->documents->first();

        $this->get("/my-account/downloads/{$document->id}")->assertRedirect('/login');
    }

    public function test_a_signed_in_user_without_the_purchase_gets_a_403(): void
    {
        $user = User::factory()->create();
        $document = $this->course('life-in-the-uk-course')->documents->first();

        $this->actingAs($user)->get("/my-account/downloads/{$document->id}")->assertForbidden();
    }

    public function test_a_purchaser_can_download_their_course_files(): void
    {
        $this->requireCourseFiles();

        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->markAsPaid($user, $course, 'cs_test_download');

        foreach ($course->documents as $document) {
            $response = $this->actingAs($user)->get("/my-account/downloads/{$document->id}");

            $response->assertOk();
            $response->assertDownload($document->downloadName());
            $this->assertSame(
                realpath(base_path('course-files/'.$document->filename)),
                $response->baseResponse->getFile()->getPathname()
            );
        }
    }

    public function test_the_course_does_not_unlock_the_mock_test_package(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');
        $mocks = $this->course('24-mock-tests');

        $this->markAsPaid($user, $course, 'cs_test_course_only');

        $this->assertTrue($user->fresh()->hasPurchased($course));
        $this->assertFalse($user->fresh()->hasPurchased($mocks));

        // The 403 must not depend on the file being present: an unpurchased
        // course is refused before the file is ever looked for.
        foreach ($mocks->documents as $document) {
            $this->actingAs($user)->get("/my-account/downloads/{$document->id}")->assertForbidden();
        }
    }

    public function test_the_mock_test_package_does_not_unlock_the_course(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');
        $mocks = $this->course('24-mock-tests');

        $this->markAsPaid($user, $mocks, 'cs_test_mocks_only');

        $this->assertTrue($user->fresh()->hasPurchased($mocks));
        $this->assertFalse($user->fresh()->hasPurchased($course));

        foreach ($course->documents as $document) {
            $this->actingAs($user)->get("/my-account/downloads/{$document->id}")->assertForbidden();
        }
    }

    public function test_a_download_cannot_escape_the_course_files_directory(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->markAsPaid($user, $course, 'cs_test_traversal');

        $document = $course->documents->first();
        $document->forceFill(['filename' => '../../.env'])->save();

        $this->actingAs($user)->get("/my-account/downloads/{$document->id}")->assertNotFound();
    }

    public function test_course_files_are_not_reachable_through_the_web_root(): void
    {
        $this->get('/course-files/Life%20in%20the%20UK%20Lesson%201-10.docx')->assertNotFound();
    }

    /* -----------------------------------------------------------------
     | Return trip from Stripe
     | ----------------------------------------------------------------- */

    public function test_the_success_page_does_not_grant_access_on_its_own(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        // Stripe is stubbed to fail: the page must not treat the URL as proof.
        $this->stubStripeLookupToFail();

        $this->actingAs($user)
            ->get('/checkout/success?session_id=cs_test_never_paid')
            ->assertOk()
            ->assertSee('We Are Confirming Your Payment');

        $this->assertFalse($user->fresh()->hasPurchased($course));
        $this->assertSame(0, Purchase::count());
    }

    public function test_the_success_page_confirms_once_the_purchase_is_recorded(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->markAsPaid($user, $course, 'cs_test_confirmed');

        $this->actingAs($user)
            ->get('/checkout/success?session_id=cs_test_confirmed')
            ->assertOk()
            ->assertSee('Payment Confirmed')
            ->assertSee('Life in the UK Course');
    }

    public function test_the_success_page_validates_the_session_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/checkout/success?session_id='.urlencode("cs_test_' OR 1=1--"))
            ->assertSessionHasErrors('session_id');
    }

    public function test_the_success_page_will_not_show_another_users_purchase(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->markAsPaid($owner, $this->course('life-in-the-uk-course'), 'cs_test_someone_elses');

        $this->stubStripeLookupToFail();

        $this->actingAs($other)
            ->get('/checkout/success?session_id=cs_test_someone_elses')
            ->assertOk()
            ->assertSee('We Are Confirming Your Payment')
            ->assertDontSee('Payment Confirmed');
    }

    public function test_a_cancelled_payment_is_never_recorded_as_paid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/checkout/cancel')->assertOk()->assertSee('No Payment Was Taken');

        $this->assertSame(0, Purchase::where('status', Purchase::STATUS_PAID)->count());
        $this->assertFalse($user->fresh()->hasPurchased('24-mock-tests'));
    }

    public function test_the_cancel_page_offers_a_retry_for_the_course_that_was_abandoned(): void
    {
        $user = User::factory()->create();

        // The slug remembered when checkout was started, as it is in
        // PurchaseService::beginCheckout().
        $this->actingAs($user)
            ->withSession(['checkout.course' => '24-mock-tests'])
            ->get('/checkout/cancel')
            ->assertOk()
            ->assertSee('Try 24 Mock Tests Package Again')
            ->assertSee(action([CheckoutController::class, 'store'], '24-mock-tests'), escape: false);

        // Consumed, so a later visit to the cancel page does not repeat it.
        $this->actingAs($user)
            ->get('/checkout/cancel')
            ->assertOk()
            ->assertDontSee('Try 24 Mock Tests Package Again');
    }

    public function test_the_cancel_page_does_not_offer_a_retry_for_an_inactive_course(): void
    {
        $user = User::factory()->create();
        $this->course('24-mock-tests')->update(['is_active' => false]);

        $this->actingAs($user)
            ->withSession(['checkout.course' => '24-mock-tests'])
            ->get('/checkout/cancel')
            ->assertOk()
            ->assertDontSee('Try 24 Mock Tests Package Again');
    }

    public function test_a_guest_is_sent_to_login_before_checking_out(): void
    {
        $this->get('/courses/life-in-the-uk-course')
            ->assertOk()
            ->assertSee('Sign In To Buy')
            ->assertSee('Life in the UK Course');
    }

    /* -----------------------------------------------------------------
     | Account page
     | ----------------------------------------------------------------- */

    public function test_the_account_page_lists_only_what_the_user_owns(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/my-account')
            ->assertOk()
            ->assertSee('You have not purchased anything yet')
            ->assertDontSee('Life in the UK Lessons 1-10');

        $this->markAsPaid($user, $this->course('life-in-the-uk-course'), 'cs_test_dashboard');

        $this->actingAs($user)->get('/my-account')
            ->assertOk()
            ->assertSee('Life in the UK Lessons 1-10')
            ->assertSee('6 Classroom Mock Tests')
            ->assertDontSee('Total 24 Mock Tests - Life in the UK');
    }

    /* -----------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------- */

    protected function course(string $slug): Course
    {
        return Course::where('slug', $slug)->firstOrFail();
    }

    /**
     * Skip the calling test when the source .docx files are not on this machine.
     *
     * They were handed to a designer and are not part of the repository. The
     * lessons and papers they produced now live in the JSON files the site
     * serves, so the download tests simply have nothing to download.
     */
    protected function requireCourseFiles(): void
    {
        if (! is_file(base_path('course-files/Life in the UK Lesson 1-10.docx'))) {
            $this->markTestSkipped(
                'The .docx source documents are not present; they were temporary and the download '
                .'feature has no file to serve. The course content itself is covered by LearningAreaTest.'
            );
        }
    }

    /**
     * Replace the Stripe gateway so no test ever touches the network.
     *
     * @param  array  $captured  Filled with the checkout session parameters.
     */
    protected function fakeStripe(array &$captured): void
    {
        // A full closure, not an arrow function: arrow functions capture by
        // value, which would silently discard what we captured here.
        $onCreate = function (array $params) use (&$captured) {
            $captured = $params;
        };

        $this->app->instance(StripeService::class, $this->stripeMock($onCreate));
    }

    /**
     * Stripe knows nothing about the session - the situation when a customer
     * reaches the success URL with a session that was never paid.
     */
    protected function stubStripeLookupToFail(): void
    {
        $mock = $this->stripeMock();

        $mock->shouldReceive('retrieveCheckoutSession')
            ->andThrow(new ApiConnectionException('No such checkout session'));

        $this->app->instance(StripeService::class, $mock);
    }

    /**
     * A partial mock: the webhook signature check stays real, only the API
     * calls are replaced.
     */
    protected function stripeMock(?callable $onCreate = null): StripeService
    {
        $mock = Mockery::mock(StripeService::class)->makePartial();

        $mock->shouldReceive('createCheckoutSession')->andReturnUsing(
            function (array $params) use ($onCreate) {
                if ($onCreate) {
                    $onCreate($params);
                }

                $id = 'cs_test_'.$params['metadata']['course_slug'];

                return Session::constructFrom([
                    'id' => $id,
                    'object' => 'checkout.session',
                    'url' => "https://checkout.stripe.com/c/pay/{$id}",
                ]);
            }
        );

        return $mock;
    }

    protected function markAsPaid(User $user, Course $course, string $sessionId): Purchase
    {
        return Purchase::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'stripe_checkout_session_id' => $sessionId,
            'stripe_payment_intent_id' => 'pi_'.substr(sha1($sessionId), 0, 12),
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => Purchase::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    protected function sessionObject(User $user, Course $course, string $paymentStatus): array
    {
        return [
            'id' => 'cs_test_completed',
            'object' => 'checkout.session',
            'payment_status' => $paymentStatus,
            'status' => 'complete',
            'amount_total' => $course->price,
            'currency' => $course->currency,
            'client_reference_id' => (string) $user->id,
            'customer' => 'cus_test_customer',
            'payment_intent' => 'pi_test_intent',
            'customer_details' => [
                'email' => $user->email,
                'name' => $user->name,
            ],
            'metadata' => [
                'user_id' => (string) $user->id,
                'course_id' => (string) $course->id,
                'course_slug' => $course->slug,
            ],
        ];
    }

    protected function event(string $id, string $type, array $object): array
    {
        return [
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'livemode' => false,
            'data' => ['object' => $object],
        ];
    }

    /**
     * POST a raw webhook body signed exactly the way Stripe signs one.
     */
    protected function sendWebhook(array $event)
    {
        $payload = json_encode($event);

        return $this->postRawWebhook($payload, $this->sign($payload, config('stripe.webhook.secret')));
    }

    protected function postRawWebhook(string $payload, ?string $signature)
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== null) {
            $server['HTTP_STRIPE_SIGNATURE'] = $signature;
        }

        return $this->call('POST', '/stripe/webhook', [], [], [], $server, $payload);
    }

    protected function sign(string $payload, string $secret): string
    {
        $timestamp = time();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }
}
