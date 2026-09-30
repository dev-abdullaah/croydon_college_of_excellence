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
use Illuminate\Support\Facades\Schema;
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

    /**
     * Describe a live site, where the paywall is on.
     *
     * The paywall is off by default so the material can be built and marked
     * before anything is sold. Every test here is about what a paying or
     * would-be-paying customer sees, so it opts back in explicitly.
     */
    private function requirePurchase(): static
    {
        config(['course-content.require_purchase' => true]);

        return $this;
    }

    /* -----------------------------------------------------------------
     | Catalogue and homepage
     | ----------------------------------------------------------------- */

    public function test_homepage_advertises_both_courses_with_prices(): void
    {
        $this->requirePurchase();

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
    }

    /* -----------------------------------------------------------------
     | The catalogue page

     | The account page and the checkout cancel page both send a visitor to
     | somewhere to buy. Both used to link to the homepage with an anchor on
     | the paid-courses section, which meant landing at the top of a long page
     | and having to scroll past the hero, the gallery and the testimonials to
     | reach the thing they were sent to buy. So they get a page of their own,
     | and the anchor is gone.
     * ----------------------------------------------------------------- */

    public function test_the_catalogue_page_lists_both_courses_with_prices(): void
    {
        $this->requirePurchase();

        $this->get('/courses')
            ->assertOk()
            ->assertSee('Choose The Course You Need')
            ->assertSee('Buy Life in the UK Course')
            ->assertSee('Buy 24 Mock Tests Package')
            ->assertSee('£99')
            ->assertSee('£49');
    }

    public function test_the_catalogue_page_is_public(): void
    {
        // It is a selling page. Requiring an account to look at what is for
        // sale would be the wrong way round.
        $this->get('/courses')->assertOk();

        $this->get(route('courses.index'))
            ->assertOk()
            ->assertSee('Choose The Course You Need');
    }

    public function test_the_catalogue_page_still_links_to_each_course(): void
    {
        $this->get('/courses')
            ->assertOk()
            ->assertSee(route('courses.show', 'life-in-the-uk-course'), false)
            ->assertSee(route('courses.show', '24-mock-tests'), false);
    }

    public function test_the_catalogue_page_says_so_when_there_is_nothing_to_sell(): void
    {
        // An empty list with no explanation reads as a broken site.
        Course::query()->update(['is_active' => false]);
        config(['catalog.courses' => []]);

        $this->get('/courses')
            ->assertOk()
            ->assertSee('Courses are not available right now')
            ->assertSee('+44 7405 073764');
    }

    public function test_nothing_links_to_the_homepage_anchor_any_more(): void
    {
        // The old target. While this anchor is still linked from anywhere, the
        // scroll-through-the-whole-homepage problem is still live.
        $dead = [];

        foreach ($this->allBladeViews() as $path) {
            if (str_contains((string) file_get_contents($path), '#paid-courses')) {
                $dead[] = $path;
            }
        }

        $this->assertSame([], $dead, 'views still link to home#paid-courses');
    }

    /**
     * Every blade view under resources/views, whatever the shell handed back.
     *
     * @return array<int, string>
     */
    private function allBladeViews(): array
    {
        $found = [];

        $directory = new \RecursiveDirectoryIterator(resource_path('views'));
        $directory->setFlags(\FilesystemIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    public function test_the_account_page_sends_an_empty_visitor_to_the_catalogue(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->get('/my-account')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'href="'.route('courses.index').'"',
            $html,
            'the account page does not link to the catalogue page'
        );
        $this->assertStringNotContainsString(
            '#paid-courses',
            $html,
            'the account page still links to the homepage anchor'
        );
    }

    public function test_the_catalogue_button_uses_the_theme_button_and_is_centred(): void
    {
        // It is the theme's own button (rbt-btn), not a Bootstrap .btn, and it
        // is the one thing to do on that card, so it sits in a centred block.
        $html = $this->actingAs(User::factory()->create())
            ->get('/my-account')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<a href="'.preg_quote(route('courses.index'), '/').'"\s+class="rbt-btn hover-icon-reverse btn-border-gradient radius-round d-inline-flex"/',
            $html,
            'the browse button is not the expected theme button'
        );

        $this->assertMatchesRegularExpression(
            '/<div class="text-center[^"]*">\s*<a href="'.preg_quote(route('courses.index'), '/').'"/',
            $html,
            'the browse button is not centred'
        );

        // hover-icon-reverse animates .btn-text and two .btn-icon spans, so a
        // class with none of that markup inside would animate nothing.
        //
        // Matched on the button's own classes, not on its href: the layout's
        // menu also links to the catalogue page, and reading the first href
        // would pick that plain link up instead of this one.
        preg_match(
            '/<a[^>]*class="rbt-btn hover-icon-reverse btn-border-gradient radius-round d-inline-flex"[^>]*>(.*?)<\/a>/s',
            $html,
            $button
        );

        $this->assertNotEmpty($button, 'the browse button was not found on the page');
        $this->assertStringContainsString(route('courses.index'), $button[0]);
        $this->assertStringContainsString('icon-reverse-wrapper', $button[1]);
        $this->assertStringContainsString('class="btn-text"', $button[1]);
        $this->assertSame(2, substr_count($button[1], 'class="btn-icon"'));
    }

    public function test_the_cancel_page_sends_a_visitor_to_the_catalogue(): void
    {
        $html = $this->actingAs(User::factory()->create())
            ->withSession(['checkout.course' => 'life-in-the-uk-course'])
            ->get('/checkout/cancel')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('courses.index'), $html);
        $this->assertStringNotContainsString('#paid-courses', $html);
    }

    /**
     * Nothing is sold as a file, so the catalogue must not point at one.
     *
     * The material is read and tested on the website from the JSON content
     * files. This is the regression guard for the DOCX era: no downloadable
     * document is described anywhere a visitor or a buyer can see.
     */
    public function test_no_course_is_advertised_as_a_downloadable_file(): void
    {
        foreach (Course::all() as $course) {
            $this->assertObjectNotHasProperty(
                'documents',
                $course,
                "{$course->slug} still exposes a documents relation."
            );
        }

        // The catalogue config must not reintroduce a file to sell.
        foreach (config('catalog.courses', []) as $definition) {
            $this->assertArrayNotHasKey(
                'documents',
                $definition,
                "config/catalog.php still lists downloadable files for {$definition['slug']}."
            );
        }

        // And the schema must not keep a table for them either.
        $this->assertFalse(
            Schema::hasTable('course_documents'),
            'The course_documents table still exists; nothing is sold as a file.'
        );
    }

    /**
     * The old download URL must be gone, not merely unauthorised.
     *
     * A route that 404s proves there is nothing behind it; a route that 403s
     * would mean the files are still being served to somebody.
     */
    public function test_the_download_url_no_longer_exists(): void
    {
        $this->get('/my-account/downloads/1')->assertNotFound();
    }

    /* -----------------------------------------------------------------
     | Authentication
     | ----------------------------------------------------------------- */

    public function test_a_visitor_can_register_but_not_reach_their_account(): void
    {
        // The behaviour this replaced: registration used to end with
        // Auth::login and a redirect to the dashboard, so the whole site was
        // reachable with an address nobody had confirmed.
        $this->post('/register', [
            'name' => 'Sajib Islam',
            'email' => 'sajib@example.com',
            'password' => 'Sup3rSecret!',
            'password_confirmation' => 'Sup3rSecret!',
        ])->assertRedirect('/email/verify');

        $this->assertGuest();
        $this->get('/my-account')->assertRedirect('/login');
    }

    /* -----------------------------------------------------------------
     | Where a message is shown
     |
     | Feedback from a redirect used to be a bar of alert markup in a
     | container at the top of the page. That put it directly under the
     | header, where it read as part of the navigation, and made it part of
     | the document flow, so it shoved the page down by its own height and
     | the layout jumped when it went.
     |
     | It is a fixed overlay now. The checks below are on the wrapper and its
     | positioning rather than on the prose, because "is it in the flow" and
     | "is it in the corner" are the two things that were wrong.
     * ----------------------------------------------------------------- */

    public function test_a_message_is_shown_as_a_fixed_overlay(): void
    {
        // Registration no longer ends at the dashboard, so the message is
        // seeded onto the session directly rather than produced by signing
        // up. The overlay is the thing under test, not how a message is
        // caused.
        $this->actingAs(User::factory()->create())
            ->withSession(['success' => 'Your account is ready'])
            ->get('/my-account')
            ->assertOk()
            ->assertSee('Your account is ready')
            ->assertSee('class="site-toasts"', false)
            ->assertSee('class="alert alert-success site-toast"', false)
            // Outside the flow, so it cannot move the page, and above the
            // sticky header, which is z-index 99 and would otherwise cover it.
            ->assertSee('class="site-toasts" role="region"', false);
    }

    /* -----------------------------------------------------------------
     | The buttons on the account page

     | Signing out is the one thing here that undoes something, so it is red
     | and the same size as the buttons around it. It was a .btn-sm, which at
     | this theme's root font size came out about 20px tall with 8.75px type -
     | smaller than the body text next to it.
     * ----------------------------------------------------------------- */

    public function test_signing_out_is_a_large_red_button(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/my-account')
            ->assertOk()
            ->assertSee('class="btn btn-lg btn-danger"', false);
    }

    public function test_the_account_page_buttons_are_all_the_same_size(): void
    {
        // One page, one button size. A mixture is what makes a page look
        // unfinished, and this page had 20px, 31px and 45px buttons on it.
        $html = $this->actingAs(User::factory()->create())
            ->get('/my-account')
            ->assertOk()
            ->getContent();

        preg_match_all('/class="(btn btn-[^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $class) {
            $this->assertStringContainsString('btn-lg', $class, "a small button: {$class}");
        }
    }

    public function test_bootstrap_button_sizes_are_left_to_bootstrap(): void
    {
        // styles.css must not redefine .btn, .btn-sm or .btn-lg.
        //
        // It briefly did. Having read bootstrap.min.css and seen the sizes in
        // rem, the sizes were restated in px in the theme's stylesheet to
        // compensate for `html { font-size: 10px }` - which made every one of
        // them far too big, because it was answering a question nobody had
        // asked. `btn-lg` is a Bootstrap class; what it looks like is
        // Bootstrap's business.
        //
        // Anchored so `.rbt-btn.btn-lg`, which is the theme's own separate
        // button and legitimately stays, is not what this reads.
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        preg_match_all('/(?<![\w.-])(\.btn|\.btn-sm|\.btn-lg)\s*\{([^}]*)\}/', $css, $matches, PREG_SET_ORDER);

        $this->assertSame([], $matches, 'styles.css is overriding Bootstrap button sizing');
    }

    public function test_a_message_carries_a_dismiss_control(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['success' => 'All done'])
            ->get('/my-account')
            ->assertOk()
            ->assertSee('data-site-toast-dismiss', false)
            ->assertSee('Dismiss this message', false);
    }

    /* -----------------------------------------------------------------
     | Which messages leave on their own

     | The close button being there and nothing else is what left people
     | clicking the x on every confirmation. Confirmations now take themselves
     | off the screen.
     |
     | The ones that must NOT are the half that matters just as much. A list of
     | validation errors that disappears before it is read is worse than no
     | list, and there is no timeout short enough to be safe and long enough to
     | finish reading. So the opt-in is on the message, not a timer that
     | applies to all of them - which is what makes it possible to leave errors
     | alone without special-casing them in the script.
     * ----------------------------------------------------------------- */

    public function test_a_confirmation_leaves_the_screen_by_itself(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['success' => 'Your email address is verified'])
            ->get('/my-account')
            ->assertOk()
            ->assertSee('data-site-toast-autoclose="6000"', false);
    }

    public function test_a_longer_message_is_given_longer_before_it_goes(): void
    {
        // Six seconds is a reading time, not a sentence.
        $this->actingAs(User::factory()->create())
            ->withSession(['info' => 'Something worth reading at length'])
            ->get('/my-account')
            ->assertOk()
            ->assertSee('data-site-toast-autoclose="9000"', false);
    }

    public function test_a_validation_summary_never_leaves_the_screen_by_itself(): void
    {
        $html = $this->followingRedirects()
            ->post('/register', [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertOk()
            ->assertSee('Please check the form')
            ->getContent();

        $this->assertStringNotContainsString('data-site-toast-autoclose', $html);
    }

    public function test_a_warning_or_an_error_stays_put_too(): void
    {
        // Same reasoning as the validation summary: these are instructions, not
        // news. Only confirmations are safe to lose.
        foreach (['warning' => 'Careful', 'error' => 'That did not work'] as $level => $text) {
            $html = $this->actingAs(User::factory()->create())
                ->withSession([$level => $text])
                ->get('/my-account')
                ->assertOk()
                ->assertSee($text)
                ->getContent();

            $this->assertStringNotContainsString(
                'data-site-toast-autoclose',
                $html,
                "a {$level} message must not dismiss itself",
            );
        }
    }

    public function test_the_script_is_what_acts_on_the_opt_in(): void
    {
        // Read the script rather than trusting the markup. An attribute nothing
        // reads would render perfectly in every test above and leave a
        // confirmation sitting there forever, which is the bug this replaces.
        $js = (string) file_get_contents(public_path('assets/js/custom.js'));

        $this->assertStringContainsString('.site-toast[data-site-toast-autoclose]', $js);
        $this->assertStringContainsString('getAttribute("data-site-toast-autoclose")', $js);
    }

    public function test_a_message_being_read_is_not_timed_out(): void
    {
        // WCAG 2.2.1: a message that sets its own timer has to be adjustable.
        // Hover or tab into it and the countdown stops, and starts again on the
        // way out, so nobody is halfway through a sentence when it goes.
        $js = (string) file_get_contents(public_path('assets/js/custom.js'));

        foreach (['mouseenter', 'focusin', 'mouseleave', 'focusout'] as $event) {
            $this->assertStringContainsString(
                '"'.$event.'"',
                $js,
                "reading a message should pause on {$event}",
            );
        }
    }

    public function test_nothing_is_rendered_on_a_page_without_a_message(): void
    {
        // The wrapper used to render unconditionally, putting a stray empty
        // container above the content of every page in the site.
        $html = $this->actingAs(User::factory()->create())
            ->get('/my-account')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('site-toasts', $html);
        $this->assertStringNotContainsString('Please check the form', $html);
    }

    public function test_a_validation_failure_is_shown_as_a_toast(): void
    {
        // No actingAs here: /register sits behind the `guest` middleware, so
        // a signed-in visitor is redirected away before validation is reached.
        //
        // One request rather than two, because carrying the error bag across a
        // separate follow-up GET leans on flash data surviving between
        // requests, which is not something to assert layout on.
        $this->followingRedirects()
            ->post('/register', [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])
            ->assertOk()
            ->assertSee('Please check the form')
            ->assertSee('class="alert alert-danger site-toast"', false)
            ->assertSee('class="site-toasts"', false);
    }

    public function test_the_toast_overlay_is_pinned_centred_and_clears_the_header(): void
    {
        // Read the stylesheet rather than trusting the markup: a toast that is
        // not actually fixed, or that sits below the header's z-index, would
        // look correct in a test and wrong on the page.
        //
        // The header is not a fixed height (134px on a desktop, 239px on a
        // phone), so the 50px gap is measured from a custom property that
        // custom.js fills in, rather than from a hardcoded pixel value.
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        preg_match('/\.site-toasts\s*\{([^}]*)\}/', $css, $block);
        $rules = $block[1] ?? '';

        $this->assertMatchesRegularExpression('/position:\s*fixed/', $rules, 'the overlay must be out of the flow');

        // 50px below the header, however tall that header happens to be.
        $this->assertMatchesRegularExpression(
            '/top:\s*calc\(var\(--site-header-height[^;]*\+\s*50px\)/',
            $rules,
            'the message must sit 50px below the header'
        );

        // Centred horizontally. The margin is done with a transform rather
        // than auto margins so that the box can still be pinned by its left
        // and right edges on narrow screens.
        $this->assertMatchesRegularExpression('/left:\s*50%/', $rules);
        $this->assertMatchesRegularExpression('/transform:\s*translateX\(-50%\)/', $rules);

        preg_match('/z-index:\s*(\d+)/', $rules, $found);
        $toastIndex = (int) $found[1];

        preg_match('/\.rbt-header \.rbt-header-wrapper\.rbt-sticky\s*\{([^}]*)\}/', $css, $header);
        preg_match('/z-index:\s*(\d+)/', $header[1] ?? '', $headerIndex);

        $this->assertNotEmpty($headerIndex, 'could not find the sticky header z-index to compare against');
        $this->assertGreaterThan(
            (int) $headerIndex[1],
            $toastIndex,
            'a message under the sticky header would be hidden once the header pins'
        );
    }

    public function test_the_header_height_drives_the_message_position(): void
    {
        // The per-breakpoint fallbacks have to exist for the no-script case,
        // and they have to be defined on the root element so that the inline
        // style custom.js writes can override them.
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        $this->assertMatchesRegularExpression(
            '/:root\s*\{[^}]*--site-header-height:\s*134px/',
            $css,
            'the desktop fallback must live on the root element'
        );
        $this->assertMatchesRegularExpression(
            '/max-width:\s*575px[^}]*--site-header-height/',
            $css,
            'the phone fallback must exist, since the header is tallest there'
        );

        $script = (string) file_get_contents(public_path('assets/js/custom.js'));
        $this->assertStringContainsString('--site-header-height', $script);
        $this->assertStringContainsString('orientationchange', $script, 'the gap must be recalculated on rotation');
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

    /**
     * A Stripe Price that is not configured must say so, on screen.
     *
     * The suite fakes the gateway and sets both price ids, so it never sees
     * the state a fresh install is really in. Without a Stripe Price the
     * checkout cannot start, and the controller redirects back - which used
     * to look like a button that did nothing, because the homepage rendered
     * no session feedback. This covers both halves.
     */
    public function test_a_missing_stripe_price_tells_the_visitor_instead_of_reloading_silently(): void
    {
        config(['stripe.prices.course' => null]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/')
            ->post('/checkout/life-in-the-uk-course');

        // It goes back to where the button lives...
        $response->assertRedirect('/');
        $response->assertSessionHas('error');

        // ...and the message is actually rendered on that page.
        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Online payments are temporarily unavailable');

        // Nothing was written: a failed start must not leave a purchase row
        // that a webhook could later flip to paid.
        $this->assertSame(0, Purchase::count());
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

    public function test_guests_cannot_reach_the_learning_area_by_typing_the_url(): void
    {
        $course = $this->course('life-in-the-uk-course');

        $this->get(route('learn.index', $course))->assertRedirect('/login');
    }

    public function test_a_signed_in_user_without_the_purchase_gets_a_403(): void
    {
        $this->requirePurchase();

        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->actingAs($user)->get(route('learn.index', $course))->assertForbidden();
    }

    public function test_a_purchaser_can_open_the_learning_area(): void
    {
        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->markAsPaid($user, $course, 'cs_test_open');

        $this->actingAs($user)->get(route('learn.index', $course))->assertOk();
    }

    public function test_the_course_does_not_unlock_the_mock_test_package(): void
    {
        $this->requirePurchase();

        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');
        $mocks = $this->course('24-mock-tests');

        $this->markAsPaid($user, $course, 'cs_test_course_only');

        $this->assertTrue($user->fresh()->hasPurchased($course));
        $this->assertFalse($user->fresh()->hasPurchased($mocks));

        $this->actingAs($user)->get(route('learn.index', $mocks))->assertForbidden();
    }

    public function test_the_mock_test_package_does_not_unlock_the_course(): void
    {
        $this->requirePurchase();

        $user = User::factory()->create();
        $course = $this->course('life-in-the-uk-course');
        $mocks = $this->course('24-mock-tests');

        $this->markAsPaid($user, $mocks, 'cs_test_mocks_only');

        $this->assertTrue($user->fresh()->hasPurchased($mocks));
        $this->assertFalse($user->fresh()->hasPurchased($course));

        $this->actingAs($user)->get(route('learn.index', $course))->assertForbidden();
    }

    /**
     * Even with the source files on disk, no URL may hand them out.
     *
     * The .docx files are temporary build input. This asserts the guarantee
     * holds without depending on whether the files are currently present, so
     * it keeps its teeth after the folder is deleted.
     */
    public function test_no_url_serves_a_source_document(): void
    {
        foreach ([
            '/course-files/Life%20in%20the%20UK%20Lesson%201-10.docx',
            '/course-files/6%20Classroom%20Mock%20Test.docx',
            '/my-account/downloads/1',
            '/my-account/downloads/9999',
        ] as $path) {
            $this->get($path)->assertNotFound();
        }
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

    /* -----------------------------------------------------------------
     | The paywall being switched off
     |
     | While the lessons and papers are being built, the paywall is off and
     | any signed-in account can open everything. Stripe may not even be
     | configured. These tests pin that down, because the danger is a live
     | site quietly shipping in this state.
     | ----------------------------------------------------------------- */

    public function test_with_the_paywall_off_any_signed_in_account_can_open_the_learning_area(): void
    {
        $this->assertFalse(config('course-content.require_purchase'));

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('learn.index', $this->course('life-in-the-uk-course')))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('learn.index', $this->course('24-mock-tests')))
            ->assertOk();
    }

    public function test_with_the_paywall_off_a_guest_is_still_sent_to_login(): void
    {
        // A sitting is a database row keyed to a user, so a guest cannot be
        // let in to take a quiz. Turning the paywall off relaxes the purchase
        // check only, never the sign-in.
        $this->get(route('learn.index', $this->course('life-in-the-uk-course')))
            ->assertRedirect(route('login'));
    }

    public function test_with_the_paywall_off_no_stripe_configuration_is_needed(): void
    {
        config([
            'stripe.key' => null,
            'stripe.secret' => null,
            'stripe.prices.course' => null,
            'stripe.prices.mock_tests' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('learn.index', $this->course('life-in-the-uk-course')))
            ->assertOk();
    }

    public function test_with_the_paywall_off_the_cta_sends_a_customer_straight_to_the_lessons(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/courses/life-in-the-uk-course')
            ->assertOk()
            ->assertSee('Start Learning')
            ->assertDontSee('Buy Now')
            ->assertDontSee('Stripe');
    }

    public function test_with_the_paywall_off_a_guest_is_asked_to_sign_in_rather_than_buy(): void
    {
        $this->get('/courses/life-in-the-uk-course')
            ->assertOk()
            ->assertSee('Sign In To Start')
            ->assertDontSee('Buy Now');
    }

    public function test_payments_doctor_warns_that_the_paywall_is_off(): void
    {
        $this->artisan('payments:doctor')
            ->expectsOutputToContain('THE PAYWALL IS OFF')
            ->assertSuccessful();
    }

    public function test_payments_doctor_does_not_warn_once_the_paywall_is_on(): void
    {
        $this->requirePurchase();

        $this->artisan('payments:doctor')
            ->doesntExpectOutputToContain('THE PAYWALL IS OFF')
            ->assertSuccessful();
    }

    /* -----------------------------------------------------------------
     | Mail is only real in production
     |
     | The .env in this repository is committed, so it is copied around:
     | between machines, into backups, and onto the server. If a copy of the
     | live mailbox credentials sat in it, then any developer running a
     | registration test would send mail through the real mailbox from
     | wherever they happened to be sitting, with no error to say so.
     |
     | So the smtp mailer resolves to the log transport unless the app is in
     | production, and the credentials are not even read. These tests hold
     | that line in place, because the failure it prevents is invisible.
     * ----------------------------------------------------------------- */

    public function test_the_live_mailbox_is_inert_outside_production(): void
    {
        $this->assertNotSame('production', config('app.env'));

        $mail = config('mail.mailers.smtp');

        $this->assertSame('log', $mail['transport'], 'smtp must not talk to a real server off production');
        $this->assertNull($mail['host'] ?? null, 'the smtp host must not be read off production');
        $this->assertNull($mail['password'] ?? null, 'the mailbox password must not be read off production');
    }

    public function test_the_smtp_mailer_becomes_real_in_production(): void
    {
        // config/mail.php reads APP_ENV when it is loaded, so the branch is
        // exercised by loading the file again with production set.
        $previous = $_ENV['APP_ENV'] ?? null;

        try {
            $_ENV['APP_ENV'] = 'production';
            putenv('APP_ENV=production');

            $mail = require config_path('mail.php');

            $this->assertSame('smtp', $mail['mailers']['smtp']['transport']);
            $this->assertSame(465, $mail['mailers']['smtp']['port']);
        } finally {
            if ($previous === null) {
                unset($_ENV['APP_ENV']);
            } else {
                $_ENV['APP_ENV'] = $previous;
            }

            putenv('APP_ENV='.$previous);
        }
    }

    public function test_the_mailpit_mailer_can_only_reach_this_machine(): void
    {
        // Offered as a local convenience, so it is hard-coded rather than read
        // from the environment. That is the point: there is no way to aim it
        // at the live mailbox.
        $this->assertSame('127.0.0.1', config('mail.mailers.mailpit.host'));
        $this->assertSame(1025, config('mail.mailers.mailpit.port'));
        $this->assertNull(config('mail.mailers.mailpit.username'));
    }

    public function test_payments_doctor_catches_a_local_env_uploaded_to_a_live_site(): void
    {
        // The whole failure is that APP_ENV says `local` on a real site, so
        // the doctor has to be told what the site is serving. Left to itself
        // it reads the same wrong .env and reports everything fine.
        $this->artisan('payments:doctor --expect=croydoncollegeofexcellence.co.uk')
            ->expectsOutputToContain('a local .env has been uploaded')
            ->expectsOutputToContain('stack traces')
            ->expectsOutputToContain('point at this machine')
            ->assertFailed();
    }

    public function test_payments_doctor_catches_mail_being_logged_on_a_live_site(): void
    {
        // A config cache built on a developer machine bakes the log transport
        // in, and then a live site accepts every email without complaint.
        config(['mail.default' => 'smtp']);

        $this->artisan('payments:doctor --expect=croydoncollegeofexcellence.co.uk')
            ->expectsOutputToContain('messages are written to a log, not sent')
            ->assertFailed();
    }

    public function test_payments_doctor_is_happy_on_a_developer_machine(): void
    {
        // No --expect, so nothing here should be treated as a live site.
        $this->artisan('payments:doctor')
            ->doesntExpectOutputToContain('a local .env has been uploaded')
            ->doesntExpectOutputToContain('Serving')
            ->assertSuccessful();
    }

    public function test_payments_doctor_is_happy_on_a_correctly_configured_live_site(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://www.croydoncollegeofexcellence.co.uk',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.hostinger.com',
            'mail.mailers.smtp.username' => 'no-reply@croydoncollegeofexcellence.co.uk',
        ]);

        $this->artisan('payments:doctor --expect=www.croydoncollegeofexcellence.co.uk')
            ->doesntExpectOutputToContain('a local .env has been uploaded')
            ->doesntExpectOutputToContain('messages are written to a log')
            ->expectsOutputToContain('smtp -> smtp')
            ->assertSuccessful();
    }

    public function test_a_guest_is_sent_to_login_before_checking_out(): void
    {
        $this->requirePurchase();

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

        // Nothing is owned, so the purchase card must be absent. The
        // marketing copy may still name the course, so this asserts on the
        // part of the page that only a purchase produces.
        $this->actingAs($user)->get('/my-account')
            ->assertOk()
            ->assertSee('You have not purchased anything yet')
            ->assertDontSee('Your material')
            ->assertDontSee('Open the course');

        $this->markAsPaid($user, $this->course('life-in-the-uk-course'), 'cs_test_dashboard');

        $this->actingAs($user)->get('/my-account')
            ->assertOk()
            ->assertSee('Your material')
            ->assertSee('Open the course')
            ->assertSee(route('learn.index', $this->course('life-in-the-uk-course')), escape: false);

        $this->markAsPaid($user, $this->course('24-mock-tests'), 'cs_test_dashboard_mocks');

        $this->actingAs($user)->get('/my-account')
            ->assertOk()
            ->assertSee('24 Mock Tests Package')
            ->assertSee('Life in the UK Course');
    }

    /**
     * The account page must not send a buyer looking for a file.
     *
     * The old page listed downloadable documents. Nothing is a file now, so
     * the words and links that promised one must not reappear.
     */
    public function test_the_account_page_offers_no_downloads(): void
    {
        $user = User::factory()->create();

        $this->markAsPaid($user, $this->course('life-in-the-uk-course'), 'cs_test_no_downloads');

        $response = $this->actingAs($user)->get('/my-account')->assertOk();

        $response->assertDontSee('Download', escape: false);
        $response->assertDontSee('/my-account/downloads/', escape: false);
        $response->assertSee('Nothing is downloaded.', escape: false);
    }

    /* -----------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------- */

    protected function course(string $slug): Course
    {
        return Course::where('slug', $slug)->firstOrFail();
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
