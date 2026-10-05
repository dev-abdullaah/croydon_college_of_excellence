<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\StripeWebhookEvent;
use App\Models\Student;
use App\Services\StripeService;
use Database\Seeders\CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiConnectionException;
use Tests\Concerns\InteractsWithCourseContent;
use Tests\TestCase;

class PaidCoursesTest extends TestCase
{
    use InteractsWithCourseContent;
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
    }

    /* -----------------------------------------------------------------
     | The returning student banner

     | The banner above the course cards used to print one fixed sentence:
     | "Already enrolled? Sign in to jump straight to your study cards." That
     | was printed for everybody, so a learner who was already signed in was
     | being asked to sign in, and a learner who had bought nothing was told
     | their mock test papers were waiting for them. The wording has to follow
     | the account, which is what these three tests pin down.
     * ----------------------------------------------------------------- */

    public function test_the_banner_tells_a_guest_to_sign_in(): void
    {
        $home = $this->get('/')->assertOk();
        $catalogue = $this->get('/courses')->assertOk();

        foreach ([$home, $catalogue] as $response) {
            $response
                ->assertSee('Already enrolled?')
                ->assertSee('Sign in to jump straight to your study cards')
                ->assertSee('Sign In To Your Account')
                ->assertSee(route('login'), escape: false);

            $this->assertStringNotContainsString(
                'Welcome back',
                $response->getContent()
            );
        }
    }

    public function test_the_banner_welcomes_a_signed_in_learner_with_a_course(): void
    {
        $student = Student::factory()->create(['name' => 'Amina Rahman']);
        $this->markAsPaid($student, $this->course('life-in-the-uk-course'), 'cs_test_banner');

        $home = $this->actingAs($student)->get('/')->assertOk();
        $catalogue = $this->actingAs($student)->get('/courses')->assertOk();

        foreach ([$home, $catalogue] as $response) {
            $response
                ->assertSee('Welcome back, Amina.')
                ->assertSee('Your course is ready')
                ->assertSee('Go to My Account')
                ->assertSee(route('dashboard'), escape: false);

            // The guest wording is gone: nobody is asked to sign in while
            // already being the person who is signed in.
            $this->assertStringNotContainsString(
                'Already enrolled?',
                $response->getContent()
            );
            $this->assertStringNotContainsString(
                'Sign in to jump straight',
                $response->getContent()
            );
        }
    }

    public function test_the_banner_points_a_signed_in_learner_with_no_course_at_the_courses(): void
    {
        $student = Student::factory()->create(['name' => 'Sam Okafor']);

        // An unpaid purchase is not access, so it must not produce the
        // "your course is ready" wording.
        Purchase::create([
            'student_id' => $student->id,
            'course_id' => $this->course('24-mock-tests')->id,
            'stripe_checkout_session_id' => 'cs_test_unpaid_banner',
            'amount' => 4900,
            'currency' => 'gbp',
            'status' => Purchase::STATUS_PENDING,
        ]);

        $home = $this->actingAs($student)->get('/')->assertOk();
        $catalogue = $this->actingAs($student)->get('/courses')->assertOk();

        foreach ([$home, $catalogue] as $response) {
            $response
                ->assertSee('signed in, Sam.')
                ->assertSee('have a course yet')
                ->assertSee('See The Courses')
                ->assertSee('href="#course-list"', escape: false);

            // Not sent to an account that would be empty, and not told to
            // sign in when they already have.
            $this->assertStringNotContainsString(
                'Your course',
                $response->getContent()
            );
            $this->assertStringNotContainsString(
                'Sign in to jump straight',
                $response->getContent()
            );
        }
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
        $html = $this->actingAs(Student::factory()->create())
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

    public function test_the_account_heading_and_sign_out_stay_on_one_row(): void
    {
        $student = Student::factory()->create();
        $this->markAsPaid($student, $this->course('life-in-the-uk-course'), 'cs_test_account_bar');

        $html = $this->actingAs($student)
            ->get('/my-account')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<div class="account-bar[^"]*">\s*<h3[^>]*>\s*Your purchased materials\s*<\/h3>\s*<form/s',
            $html,
            'the heading and the Sign Out form must be siblings in one flex row, so they cannot stack'
        );

        // A Bootstrap column pair inside the bar is what stacked in the first
        // place: col-md-* collapses to full width below 768px, which put the
        // button on a line of its own on every phone.
        $this->assertSame(
            1,
            preg_match('/<div class="account-bar[^"]*">(.*?)<\/div>\s*<!--/s', $html, $bar),
            'could not isolate the account bar'
        );
        $this->assertStringNotContainsString(
            'col-md-',
            $bar[1],
            'the bar must not use grid columns, which stack on small screens'
        );

        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        $this->assertMatchesRegularExpression(
            '/\.account-bar\s*\{[^}]*flex-wrap:\s*nowrap/',
            $css,
            'the row must be pinned to one line'
        );
        $this->assertMatchesRegularExpression(
            '/\.account-bar \.title\s*\{[^}]*min-width:\s*0/',
            $css,
            'the heading must be able to shrink so the button is never pushed off the row'
        );
        $this->assertMatchesRegularExpression(
            '/@media[^{]*max-width:\s*767px[^{]*\{[^@]*?\.account-bar \.title\s*\{[^}]*font-size/',
            $css,
            'the heading needs a mobile step-down; --h3 is a flat 34px'
        );
    }

    public function test_the_catalogue_button_uses_the_theme_button_and_is_centred(): void
    {
        // It is the theme's own button (rbt-btn), not a Bootstrap .btn, and it
        // is the one thing to do on that card, so it sits in a centred block.
        $html = $this->actingAs(Student::factory()->create())
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
        $html = $this->actingAs(Student::factory()->create())
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
        $this->actingAs(Student::factory()->create())
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
        $this->actingAs(Student::factory()->create())
            ->get('/my-account')
            ->assertOk()
            ->assertSee('class="btn btn-lg btn-danger"', false);
    }

    public function test_the_account_page_buttons_are_all_the_same_size(): void
    {
        // One page, one button size. A mixture is what makes a page look
        // unfinished, and this page had 20px, 31px and 45px buttons on it.
        $html = $this->actingAs(Student::factory()->create())
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
        $this->actingAs(Student::factory()->create())
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
        $this->actingAs(Student::factory()->create())
            ->withSession(['success' => 'Your email address is verified'])
            ->get('/my-account')
            ->assertOk()
            ->assertSee('data-site-toast-autoclose="6000"', false);
    }

    public function test_a_longer_message_is_given_longer_before_it_goes(): void
    {
        // Six seconds is a reading time, not a sentence.
        $this->actingAs(Student::factory()->create())
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
            $html = $this->actingAs(Student::factory()->create())
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
        $html = $this->actingAs(Student::factory()->create())
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

    /**
     * The mobile account button has to say what it is.
     *
     * Its label used to carry `d-none d-sm-inline`, so on any phone narrower
     * than 576px it collapsed to a bare person icon with nothing written next
     * to it - the one control in the bar a first-time visitor could not name.
     * The label is now unconditional, and this test fails if anyone reaches
     * for a responsive utility to hide it again.
     */
    public function test_the_mobile_account_button_keeps_its_label(): void
    {
        $signedOut = $this->get('/')->assertOk()->getContent();
        $signedIn = $this->actingAs(Student::factory()->create())->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/btn-header-account[^>]*>\s*<i[^>]*><\/i>\s*<span(?![^>]*\bd-(none|sm-|md-|lg-|xl-))[^>]*>\s*Sign In\s*<\/span>/',
            $signedOut,
            'the label must render with no responsive utility that hides it below 576px'
        );

        $this->assertMatchesRegularExpression(
            '/btn-header-account[^>]*>\s*<i[^>]*><\/i>\s*<span(?![^>]*\bd-(none|sm-|md-|lg-|xl-))[^>]*>\s*Account\s*<\/span>/',
            $signedIn,
            'the label must render with no responsive utility that hides it below 576px'
        );

        // The class that carries the layout has to exist, otherwise the button
        // falls back to `.rbt-btn` (45px tall, 26px side padding) and overflows
        // the bar it sits in.
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        $this->assertMatchesRegularExpression(
            '/\.btn-header-account\s*\{[^}]*white-space:\s*nowrap/',
            $css,
            'the label must be kept on one line when the bar is tight'
        );
    }

    /**
     * The icon must sit beside the label with a gap, on the same line as it.
     *
     * `.rbt-btn i` pads the icon on its left and offsets it with `top: 2px`.
     * Feather glyphs are an icon font, so the padding lands before the icon
     * rather than between icon and label, and the offset drops the glyph below
     * centre - the desktop My Account button read as "MyAccount" sitting two
     * pixels low. `.btn-header-icon` corrects both, and it has to be on the
     * desktop button too, not just the mobile one.
     */
    public function test_the_header_account_icon_is_gapped_and_centred(): void
    {
        $signedIn = $this->actingAs(Student::factory()->create())
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/class="rbt-btn btn-gradient btn-header-icon"[^>]*>\s*<i[^>]*><\/i>\s*<span>\s*My Account\s*<\/span>/',
            $signedIn,
            'the desktop My Account button must carry the icon-alignment class'
        );

        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        $this->assertMatchesRegularExpression(
            '/\.btn-header-icon\s*\{[^}]*gap:\s*\d/',
            $css,
            'the gap between icon and label has to come from a flex gap, not from padding on the icon'
        );

        // `padding-left` must be cleared and the `top: 2px` offset removed,
        // otherwise the glyph still has no gap after it and still sits low.
        $this->assertMatchesRegularExpression(
            '/\.btn-header-icon i\s*\{[^}]*padding-left:\s*0[^}]*top:\s*0/',
            $css,
            'the icon must have its inherited left padding and top offset cleared'
        );

        // The correction has to come after the rule it corrects, or it loses
        // on source order and never applies.
        $this->assertGreaterThan(
            strpos($css, '.rbt-btn i {'),
            strpos($css, '.btn-header-icon i {'),
            'the icon corrections must follow the rule they are correcting'
        );
    }

    /**
     * Hero paragraphs have to be legible against the gradient, not just
     * against white.
     *
     * `.bg-gradient-9` paints purple-to-blue and then washes the top of the
     * section towards white with a `::after` overlay, so the background under
     * a hero paragraph changes with its position: very nearly white at the top,
     * substantially the gradient by the time the text has finished. `body`
     * sets `color: var(--color-body)` (#6b7385) - a grey picked to sit quietly
     * on white - which fell to 2.74:1 across the band these paragraphs occupy,
     * under the 4.5:1 WCAG AA wants for body text, and muddy rather than
     * deliberately secondary.
     *
     * So this reads the colour back out of the stylesheet and measures it,
     * rather than asserting the rule exists. Checking for presence would pass
     * just as happily on a colour that fails.
     */
    public function test_hero_paragraphs_are_legible_against_the_gradient_they_sit_on(): void
    {
        // Sanity: the markup this rule exists for is actually rendered.
        $this->assertMatchesRegularExpression(
            '/bg-gradient-9[\s\S]*?section-title[\s\S]*?<p class="mt--10 mb-0">/',
            $this->get('/login')->assertOk()->getContent(),
            'the login hero should render a section-title paragraph on the gradient'
        );

        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        $selector = '/\.bg-gradient-9 \.section-title p\s*\{[^}]*color:\s*(rgba?\([^)]*\)|#[0-9a-fA-F]{3,8})/';

        $this->assertMatchesRegularExpression(
            $selector,
            $css,
            'gradient heroes need an explicit paragraph colour; they inherit --color-body from body'
        );

        preg_match($selector, $css, $matches);
        $colour = $matches[1];
        $worst = $this->worstHeroContrast($colour);

        $this->assertGreaterThanOrEqual(
            4.5,
            $worst,
            sprintf(
                'hero paragraph colour %s reaches only %.2f:1 against the gradient; WCAG AA body text needs 4.5:1',
                $colour,
                $worst
            )
        );

        // Equal specificity to the dark-mode rule further down the file, so
        // this one has to come first or it would break dark mode instead of
        // the other way round.
        preg_match('/^\.bg-gradient-9 \.section-title p\s*\{/m', $css, $hero, PREG_OFFSET_CAPTURE);
        preg_match('/^\.active-dark-mode \.section-title p\s*\{/m', $css, $dark, PREG_OFFSET_CAPTURE);

        $this->assertGreaterThan(
            $hero[0][1],
            $dark[0][1],
            'dark mode paints these paragraphs its own colour and must win on source order'
        );
    }

    /**
     * Every section gradient the views actually use has to be handled in dark
     * mode.
     *
     * Dark mode sets `h1`..`h6` and `.section-title p` to white globally, so
     * any section background still pale in dark mode turns its own heading
     * invisible. `.bg-gradient-5` (a flat #EFF1FF) and `.bg-gradient-9` (a
     * purple-to-blue gradient under a white wash) both were left out while the
     * page banner was covered, which is why /courses-regular and /courses-send
     * looked right and their 21 sub-pages, plus the 20 hero sections, did not.
     *
     * The gradient's own colour is not the point - `btn-gradient` is left
     * alone on purpose, because a saturated fill takes white text perfectly
     * well. What matters is a *section* background, which is what `bg-gradient-N`
     * is, so that is what this walks.
     */
    public function test_every_section_gradient_used_by_the_views_has_dark_mode_support(): void
    {
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        $used = [];

        foreach ($this->bladeFiles() as $file) {
            preg_match_all('/\bbg-gradient-(\d+)\b/', (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $number) {
                $used[$number] = true;
            }
        }

        $this->assertNotEmpty($used, 'expected the views to use some section gradients');

        $missing = [];

        foreach (array_keys($used) as $number) {
            if (! preg_match('/^\.active-dark-mode \.bg-gradient-'.$number.'\s*\{/m', $css)) {
                $missing[] = 'bg-gradient-'.$number;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'these section gradients have no dark-mode override, so their headings turn '
                .'white on a pale background in dark mode'
        );

        // Swapping `.bg-gradient-9`'s gradient is not sufficient on its own.
        // Its `::after` lays a white wash over the top, and white text on top
        // of a white wash is no better than the pale gradient underneath, so
        // the wash has to be neutralised as well.
        $this->assertMatchesRegularExpression(
            '/^\.active-dark-mode \.bg-gradient-9::after\s*\{[^}]*background:\s*transparent/m',
            $css,
            'the white wash over .bg-gradient-9 has to be removed in dark mode'
        );
    }

    /**
     * Black text hardcoded into a block that dark mode repaints has to be
     * corrected, or it disappears.
     *
     * `.text-black` is Bootstrap's `color: rgba(0,0,0,1) !important`. Dark
     * mode turns `.bg-color-white` into `--color-darker`, so a paragraph
     * carrying that class lands on a dark background while staying pure
     * black - 1.33:1, invisible rather than dim. The Director's message does
     * exactly this on all eight of its paragraphs.
     *
     * Because the utility is `!important`, the correction has to be
     * `!important` too or it simply loses, which is the part a plausible
     * looking stylesheet edit gets wrong.
     */
    public function test_hardcoded_black_text_is_corrected_inside_darkened_blocks(): void
    {
        $this->assertStringContainsString(
            'text-black',
            $this->get('/director-message')->assertOk()->getContent(),
            "the Director's message still hardcodes black text into its paragraphs"
        );

        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        // Precondition: dark mode really does darken the block it sits in.
        $this->assertMatchesRegularExpression(
            '/^\.active-dark-mode \.bg-color-white\s*\{[^}]*background:\s*var\(--color-darker\)/m',
            $css,
            'dark mode repaints .bg-color-white as --color-darker'
        );

        $this->assertMatchesRegularExpression(
            '/^\.active-dark-mode \.bg-color-white \.text-black\s*\{[^}]*color:\s*var\(--color-white-off\)\s*!important/m',
            $css,
            'black text inside a darkened block must be inverted, and needs !important to '
                .'beat the !important it is overriding'
        );

        // And the colour it lands on must actually be readable.
        $darker = $this->cssColour($css, '--color-darker');

        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrast($this->flatten('var(--color-white-off)', $darker, $css), $darker),
            'the corrected text still does not clear AA on the darkened block'
        );
    }

    /**
     * The mobile drawer has to be repainted in dark mode, not just left alone.
     *
     * `.popup-mobile-menu` is inside <body>, which dark mode colours white,
     * and its panel was pinned to `--color-white` with no override anywhere.
     * So the nav inherited white onto white at 1.00:1 - the menu was not
     * merely ugly in dark mode, it was absent - and the close button was a
     * white disc on a white panel, so there was no visible way out of it
     * either.
     *
     * The failure is easy to reintroduce silently, because every individual
     * piece of the drawer's light styling still looks correct on its own.
     * Only the pairing is wrong, so that is what gets asserted.
     */
    public function test_the_mobile_drawer_is_repainted_in_dark_mode(): void
    {
        $menu = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('popup-mobile-menu', $menu);
        $this->assertMatchesRegularExpression(
            '/popup-mobile-menu[\s\S]*?class="mainmenu-nav"/',
            $menu,
            'the drawer should carry its own nav outside the header'
        );

        $css = (string) file_get_contents(public_path('assets/css/styles.css'));
        $darker = $this->cssColour($css, '--color-darker');

        // The panel itself, and the nav colour that had nothing to sit on.
        $this->assertMatchesRegularExpression(
            '/^\.active-dark-mode \.popup-mobile-menu \.inner-wrapper\s*\{[^}]*background-color:\s*var\(--color-darker\)/m',
            $css,
            'the drawer panel stays white in dark mode while dark mode paints its text white'
        );

        $this->assertMatchesRegularExpression(
            '/^\.active-dark-mode \.popup-mobile-menu \.mainmenu li a\s*\{[^}]*color:\s*var\(--color-white-dark\)/m',
            $css,
            'the drawer nav sets no colour of its own, so in dark mode it inherits white'
        );

        // The close button is an SVG on stroke="currentColor", so `color` has
        // to be set on the button for the glyph to follow.
        $this->assertMatchesRegularExpression(
            '/^\.active-dark-mode \.popup-mobile-menu \.inner-wrapper \.inner-top \.close-button\s*\{[^}]*color:/m',
            $css,
            'the close glyph inherits currentColor and needs an explicit colour in dark mode'
        );

        // The quick-action card used the same colour as the old panel, so once
        // the panel is darkened it has to be lifted off it or it disappears.
        preg_match(
            '/^\.active-dark-mode \.mobile-quick-actions\s*\{[^}]*background:\s*([^;]+);/m',
            $css,
            $quick
        );

        $this->assertNotSame(
            'var(--color-darker)',
            trim($quick[1] ?? ''),
            'the quick-action card is now the same colour as the drawer panel it sits on'
        );

        // Finally the point of all of it: legible text on the panel.
        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrast($this->flatten('var(--color-white-dark)', $darker, $css), $darker),
            'the drawer nav does not clear AA on the darkened panel'
        );
    }

    /**
     * The homepage promo's pills and the returning-student banner both have to
     * follow dark mode.
     *
     * The banner was the harder half, because its palette was an inline
     * `style` attribute. An inline style outranks every stylesheet rule short
     * of an `!important` one, so there was nothing to write - the banner was
     * `#f0f7ff` on a `#333d51` section at 10.09:1 and no amount of dark-mode
     * CSS could have moved it. The test asserts the palette has left the
     * markup, because putting it back would look correct and silently disable
     * dark mode all over again.
     *
     * The pills are Bootstrap's `.bg-light` and `.text-secondary`, both
     * `!important`, so their corrections have to be `!important` too.
     */
    public function test_the_homepage_promo_pills_and_banner_follow_dark_mode(): void
    {
        $home = $this->get('/')->assertOk()->getContent();

        // The banner carries a class, and no longer a palette in the markup.
        $this->assertMatchesRegularExpression(
            '/class="enrollment-banner\b[^"]*"/',
            $home,
            'the returning-student banner should be styled by class so dark mode can reach it'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/class="enrollment-banner[^"]*"\s+style="[^"]*#(?:f0f7ff|bee3f8|1e3a8a)/',
            $home,
            'the banner palette is back in an inline style, which no dark-mode rule can override'
        );

        // The four feature pills are still Bootstrap utilities in the markup,
        // which is fine as long as dark mode overrides them.
        $this->assertSame(
            4,
            substr_count($home, 'badge bg-light text-secondary'),
            'expected the four feature highlight pills'
        );

        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        // Pull the pill's dark fill and text colour out and measure them
        // rather than asserting a pattern. A presence check on
        // `background-color: ... !important` would pass just as happily on
        // Bootstrap's own `#f8f9fa`, which is the bug.
        preg_match(
            '/^\.active-dark-mode \.rbt-paid-courses-area \.badge\.bg-light\s*\{[^}]*background-color:\s*([^;]+);[^}]*\}/m',
            $css,
            $fill
        );
        preg_match(
            '/^\.active-dark-mode \.rbt-paid-courses-area \.badge\.text-secondary\s*\{[^}]*color:\s*([^;]+);/m',
            $css,
            $label
        );

        $this->assertNotEmpty($fill, 'the pills need !important to reach Bootstrap\'s .bg-light');
        $this->assertNotEmpty($label, 'the pills need !important to reach Bootstrap\'s .text-secondary');

        // The promo section is `bg-color-extra2`, which dark mode sets to a
        // flat #333d51 rather than a variable.
        preg_match('/^\.active-dark-mode \.bg-color-extra2\s*\{[^}]*background:\s*([^;]+);/m', $css, $section);

        $this->assertNotEmpty($section, 'the promo section needs a dark background for the pills to sit on');

        // The pill fill is translucent, so it has to be composited over the
        // section before it can be judged.
        $pill = $this->flatten(trim($fill[1]), '#333d51', $css);

        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrast($this->flatten(trim($label[1]), $pill, $css), $pill),
            sprintf(
                'the pill text measures only %.2f:1 on its own dark fill; Bootstrap\'s #f8f9fa '
                    .'with #6c757d text would pass this shape of check while being the bug',
                $this->contrast($this->flatten(trim($label[1]), $pill, $css), $pill)
            )
        );

        // The icon inside them, and the banner's own icon, are Bootstrap's
        // `.text-primary` - #0d6efd, which is 2.80:1 on these dark containers
        // and has to be lifted or it reads as a smudge.
        //
        // Matched by scope rather than by counting: an earlier version of this
        // asserted there were exactly two such rules in the file, which passed
        // only until an unrelated block needed its own accent lifted.
        foreach ([
            '/^\.active-dark-mode \.rbt-paid-courses-area \.badge \.text-primary\s*\{[^}]*color:\s*#8fb0ff\s*!important/m',
            '/^\.active-dark-mode \.enrollment-banner \.text-primary\s*\{[^}]*color:\s*#8fb0ff\s*!important/m',
        ] as $pattern) {
            $this->assertMatchesRegularExpression(
                $pattern,
                $css,
                'the promo and banner icons are Bootstrap\'s #0d6efd, which is 2.80:1 on these containers'
            );
        }

        // The banner's dark background is translucent precisely because it
        // appears over two different containers; a flat value would vanish
        // against one of them.
        $this->assertMatchesRegularExpression(
            '/^\.active-dark-mode \.enrollment-banner\s*\{[^}]*background:\s*rgba\(/m',
            $css,
            'the banner background must stay translucent so it reads on both containers it is used on'
        );

        // And the text on it has to clear AA on the lighter of the two.
        $darker = $this->cssColour($css, '--color-darker');
        $tint = $this->flatten('rgba(13, 110, 253, 0.16)', $darker, $css);

        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrast($this->flatten('var(--color-white-dark)', $tint, $css), $tint),
            'the banner text does not clear AA on its own dark background'
        );
    }

    /**
     * The course feature cards - "10 Structured Lessons", "576 Questions" and
     * the rest - are built from three Bootstrap utilities that do not follow
     * dark mode: `.bg-white`, `.text-primary` and `.text-muted`. All three
     * carry `!important` and none has a dark-mode rule anywhere in the theme.
     *
     * The card was the one that actually destroyed text rather than merely
     * jarring. Nothing between `.active-dark-mode` and the cards sets `color`,
     * so the lesson lists inherited white from the root and sat on a white
     * card: 1.00:1. This test pins the reason that happened, because the fix
     * looks like a background change and nothing about it suggests the list
     * items depend on it. Give any of these `<li>`s a colour class and the
     * coupling becomes explicit; until then the card's colour is load-bearing
     * for every unclassed descendant inside it.
     *
     * @see https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html
     */
    public function test_the_course_feature_cards_follow_dark_mode(): void
    {
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        // `bg-color-extra2` is the pale section both sets of cards live in, and
        // dark mode flattens it to a fixed #333d51 rather than a variable.
        preg_match('/^\.active-dark-mode \.bg-color-extra2\s*\{[^}]*background:\s*([^;]+);/m', $css, $section);
        $this->assertNotEmpty($section, 'these sections need a dark background of their own');

        $sectionBg = $this->flatten(trim($section[1]), $this->cssColour($css, '--color-darker'), $css);

        // --- The card surface ------------------------------------------------
        preg_match(
            '/^\.active-dark-mode \.bg-color-extra2 \.bg-white:not\(\.rbt-badge-3\)\s*\{[^}]*background-color:\s*([^;]+);/m',
            $css,
            $cardRule
        );

        $this->assertNotEmpty(
            $cardRule,
            'the feature cards stay #ffffff in dark mode while dark mode paints their text white'
        );

        $card = $this->flatten(trim($cardRule[1]), $sectionBg, $css);

        // The card has to be distinguishable from the panel it sits on, or it
        // reads as a hole rather than a surface. Light mode manages this with
        // the border alone - #ffffff on #F9F9FF is 1.05:1 - so 1.22:1 is in
        // keeping, but 1.00:1 would mean the card has sunk into the panel.
        $this->assertGreaterThan(
            1.05,
            $this->contrast($card, $sectionBg),
            'the darkened card is indistinguishable from the section behind it'
        );

        // --- The lesson lists, which had no colour of their own --------------
        // Read straight from the markup. Each of the three pages shapes these
        // cards differently, so each is matched on its own terms rather than
        // one pattern stretched over all three.
        $this->assertMatchesRegularExpression(
            '/class="bg-white[^"]*"[^>]*>.*?<ul class="rbt-list-style-1 list-unstyled mb-0 small">\s*'
                .'<li><i class="feather-check text-primary"><\/i> <strong>Lessons 1-2:<\/strong>/s',
            $this->get('/courses')->assertOk()->getContent(),
            'expected the catalogue cards to be .bg-white with an unclassed checkmark list inside'
        );

        // The bare `<li>`s are the point of this page. Pin that they carry no
        // colour class, which is precisely what made them depend on the card's
        // own background to be readable at all.
        $this->assertMatchesRegularExpression(
            '/class="bg-white[^"]*"[^>]*>\s*<strong class="d-block mb-1 text-primary">'
                .'Core Knowledge &amp; History:<\/strong>\s*'
                .'<ul class="list-unstyled mb-0">\s*<li>&bull; Lesson 1: /',
            $this->get('/courses/life-in-the-uk-course')->assertOk()->getContent(),
            'the lesson list should be unclassed, inheriting the card\'s colour'
        );

        $this->assertMatchesRegularExpression(
            '/class="bg-white[^"]*"[^>]*>\s*<h5 class="title mb-1 text-primary">576 Questions<\/h5>\s*'
                .'<p class="mb-0 text-muted">/',
            $this->get('/courses/24-mock-tests')->assertOk()->getContent(),
            'the mock-test cards should be .bg-white with a .text-primary heading and a .text-muted note'
        );

        // The action bar at the top of a course page, on `bg-color-white`. Both
        // links use the same two utilities on a different background, which is
        // why they needed their own scope rather than the section rules above.
        $this->assertMatchesRegularExpression(
            '/<div class="course-actions[^"]*">\s*'
                .'<a href="[^"]*" class="text-primary fw-bold">.*?'
                .'<a href="[^"]*" class="text-muted small">/s',
            $this->get('/courses/life-in-the-uk-course')->assertOk()->getContent(),
            'the course action bar should carry .course-actions so its links can be lifted in dark mode'
        );

        // Inheriting, they take the dark-mode root colour. Measured rather than
        // assumed, because this is the pair that read 1.00:1 before.
        $inherited = $this->flatten('var(--color-white)', $card, $css);

        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrast($inherited, $card),
            sprintf(
                'text inheriting from the root does not clear AA on the darkened card (%.2f:1). '
                    .'Left as it was, the lesson lists inherited white and sat on a white card.',
                $this->contrast($inherited, $card)
            )
        );

        // --- The two colour utilities ---------------------------------------
        // Three scopes, three backgrounds: the card, the section the cross-sell
        // lines sit on directly, and the `bg-color-white` action bar at the top
        // of a course page.
        $darker = $this->cssColour($css, '--color-darker');

        $scopes = [
            ['.bg-color-extra2', $card, 'the card'],
            ['.bg-color-extra2', $sectionBg, 'the section itself'],
            ['.course-actions', $darker, 'the course page action bar'],
        ];

        foreach (['text-primary', 'text-muted'] as $utility) {
            foreach ($scopes as [$scope, $against, $where]) {
                preg_match(
                    '/^\.active-dark-mode '.preg_quote($scope, '/').' \.'.$utility.'\s*\{[^}]*color:\s*([^;]+);/m',
                    $css,
                    $rule
                );

                $this->assertNotEmpty(
                    $rule,
                    "Bootstrap's .{$utility} is !important and has no dark-mode rule, so it wins by default"
                );

                $colour = $this->flatten(trim($rule[1]), $against, $css);

                $this->assertGreaterThanOrEqual(
                    4.5,
                    $this->contrast($colour, $against),
                    sprintf(
                        '.%s reads only %.2f:1 on %s. Bootstrap\'s own value is #6c757d for muted and '
                            .'#0d6efd for primary, and both fail outright on a dark surface.',
                        $utility,
                        $this->contrast($colour, $against),
                        $where
                    )
                );
            }
        }

        // --- The discount badges, which must be left alone ------------------
        // `.rbt-badge-3 { background: transparent !important }` only beats
        // `.bg-white` on source order, being (0,1,0) against (0,1,0). A dark
        // rule at (0,3,0) outranks it, and the badge would gain a fill it has
        // never had, sitting behind its own SVG badge face.
        foreach (['/courses-regular', '/courses-send'] as $url) {
            $page = $this->get($url)->assertOk()->getContent();

            $this->assertStringContainsString(
                'rbt-badge-3 bg-white',
                $page,
                "expected the discount badges on {$url} to still carry .bg-white"
            );
        }

        $this->assertStringContainsString(
            '.rbt-badge-3',
            $cardRule[0],
            'the card rule must exclude .rbt-badge-3, or the 22 discount badges acquire a dark fill'
        );
    }

    /**
     * The "Life in the UK" dropdown in the header.
     *
     * A worse version of the course-card bug, and worth pinning precisely
     * because the cause is so easy to reintroduce: the theme's light-mode token
     * for the title, `--color-heading`, is the same value dark mode paints the
     * dropdown panel with, `--color-darker`. So the two course rows rendered
     * #192335 on #192335. Any rule that reaches for `--color-heading` as "the
     * dark text colour" will collide with the dark panel again.
     *
     * The hover case is asserted separately because it fails differently.
     * Dark mode already sets `color: ... !important` on the hovered anchor, and
     * it still cannot help: the title has its own explicit colour, and explicit
     * always beats inherited however the ancestor is weighted. Only a rule on
     * the title itself reaches it.
     */
    public function test_the_header_rich_submenu_follows_dark_mode(): void
    {
        $header = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            'submenu submenu-rich',
            $header,
            'the Life in the UK dropdown should still be a .submenu-rich'
        );

        // The two rows the report was about, unclassed apart from the span.
        $this->assertMatchesRegularExpression(
            '/<span class="submenu-rich-title">\s*📚 Life in the UK Course/su',
            $header,
            'expected the course row title in the header dropdown'
        );

        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        // Read the panel out of the dark rule rather than assuming the token.
        preg_match(
            '/^\.active-dark-mode \.rbt-header \.mainmenu-nav \.mainmenu li\.has-dropdown \.submenu\s*'
                .'\{[^}]*background-color:\s*([^;]+);/m',
            $css,
            $panelRule
        );

        $this->assertNotEmpty($panelRule, 'the dropdown panel needs a dark background');

        $panel = $this->flatten(trim($panelRule[1]), $this->cssColour($css, '--color-darker'), $css);

        // This is the whole point, and it is a root cause rather than a symptom:
        // the theme's light-mode token for the title, `--color-heading`, is the
        // same value dark mode paints the panel with, `--color-darker`. Asserted
        // so that if the two ever diverge - someone retones the heading colour -
        // this fails and asks whether the rules below are still needed, instead
        // of quietly leaving them there as a no-op that looks like coverage.
        $heading = $this->cssColour($css, '--color-heading');

        $this->assertLessThan(
            1.5,
            $this->contrast($heading, $panel),
            sprintf(
                'the light-mode title colour %s is now %.2f:1 against the dark panel %s. If that has been '
                    .'fixed at the token level, the .submenu-rich dark-mode rules are redundant and should go.',
                $heading,
                $this->contrast($heading, $panel),
                $panel
            )
        );

        // --- The title and description, both read from the stylesheet --------
        foreach ([
            ['submenu-rich-title', 'var(--color-white)'],
            ['submenu-rich-desc', 'var(--color-white-dark)'],
        ] as [$class, $expected]) {
            preg_match(
                '/^\.active-dark-mode \.'.$class.'\s*\{[^}]*color:\s*([^;]+);/m',
                $css,
                $rule
            );

            $this->assertNotEmpty($rule, ".{$class} needs a dark-mode colour");

            $colour = $this->flatten(trim($rule[1]), $panel, $css);

            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrast($colour, $panel),
                sprintf('.%s reads only %.2f:1 on the dark panel', $class, $this->contrast($colour, $panel))
            );

            // And on a hovered row, which dark mode repaints one step lighter.
            // 1.19:1 before, because the title kept its own colour through the
            // hover and explicit beats inherited.
            $hover = $this->cssColour($css, '--color-bodyest');

            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrast($colour, $hover),
                sprintf(
                    '.%s reads only %.2f:1 on a hovered row. Dark mode repaints the hover to %s but '
                        .'cannot reach a child that sets its own colour.',
                    $class,
                    $this->contrast($colour, $hover),
                    $hover
                )
            );

            $this->assertStringContainsString(
                $expected,
                $rule[1],
                'held explicitly so a later edit to the dark value is a decision, not a drift'
            );
        }

        // --- The two account rows, on Bootstrap's .text-primary -------------
        // Needs `!important` to be reached at all.
        preg_match(
            '/^\.active-dark-mode \.submenu-rich \.text-primary\s*\{[^}]*color:\s*([^;]+);/m',
            $css,
            $accent
        );

        $this->assertNotEmpty(
            $accent,
            "Bootstrap's .text-primary is !important with no dark-mode rule, so it wins by default"
        );

        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrast($this->flatten(trim($accent[1]), $panel, $css), $panel),
            'the account rows stay #0d6efd, which is 3.50:1 on the panel'
        );

        // The `!important` is the whole mechanism, and asserting only the colour
        // value misses it: a rule without it reads back perfectly well from the
        // stylesheet while Bootstrap's own `.text-primary !important` outranks it
        // in the browser and the rule does nothing at all.
        $this->assertStringContainsString(
            '!important',
            $accent[0],
            'this rule needs !important to reach Bootstrap\'s .text-primary, which is itself !important'
        );

        // --- The separator between the two groups --------------------------
        // `rgba(0, 0, 0, .15)` on a dark panel is 1.06:1: the rule exists and
        // the line is not there.
        preg_match(
            '/^\.active-dark-mode \.submenu-rich \.dropdown-divider\s*\{[^}]*border-top-color:\s*([^;]+);/m',
            $css,
            $divider
        );

        $this->assertNotEmpty($divider, 'the divider between the courses and the account link needs a dark-mode colour');

        $rule_ = $this->flatten(trim($divider[1]), $panel, $css);

        $this->assertGreaterThan(
            1.10,
            $this->contrast($rule_, $panel),
            sprintf('the divider still composites to %.2f:1 on the panel, so it is not visible', $this->contrast($rule_, $panel))
        );
    }

    public function test_a_visitor_can_sign_in(): void
    {
        $student = Student::factory()->create([
            'email' => 'learner@example.com',
            'password' => 'Sup3rSecret!',
        ]);

        $this->post('/login', [
            'email' => 'learner@example.com',
            'password' => 'Sup3rSecret!',
        ])->assertRedirect('/my-account');

        $this->assertAuthenticatedAs($student);
    }

    public function test_login_is_rejected_with_bad_credentials(): void
    {
        Student::factory()->create([
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
        $student = Student::factory()->create();

        $captured = [];
        $this->fakeStripe($captured);

        $this->actingAs($student)
            ->post('/checkout/life-in-the-uk-course', ['consent' => '1'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_life-in-the-uk-course');

        // The line item came from configuration, never from the request body.
        $this->assertSame('payment', $captured['mode']);
        $this->assertSame([['price' => 'price_test_course', 'quantity' => 1]], $captured['line_items']);
        $this->assertSame((string) $student->id, $captured['client_reference_id']);
        $this->assertSame((string) $student->id, $captured['metadata']['student_id']);
        $this->assertStringContainsString('session_id={CHECKOUT_SESSION_ID}', $captured['success_url']);

        $purchase = Purchase::where('stripe_checkout_session_id', 'cs_test_life-in-the-uk-course')->firstOrFail();

        $this->assertSame(Purchase::STATUS_PENDING, $purchase->status);
        $this->assertSame($student->id, $purchase->student_id);
        $this->assertSame(9900, $purchase->amount);
        $this->assertSame('gbp', $purchase->currency);
        $this->assertNull($purchase->paid_at);

        // Pending is not paid, so it unlocks nothing.
        $this->assertFalse($student->fresh()->hasPurchased('life-in-the-uk-course'));
    }

    /**
     * The session must expire, and inside the window Stripe accepts.
     *
     * Without expires_at, a Checkout Session lives for 24 hours, which is a
     * long time for a page that is holding a card form and a price that could
     * change. Stripe rejects expires_at outside 30 minutes to 24 hours, so the
     * bounds are worth pinning as well as the default.
     */
    public function test_a_checkout_session_is_given_a_bounded_expiry(): void
    {
        $student = Student::factory()->create();

        $captured = [];
        $this->fakeStripe($captured);

        $this->actingAs($student)
            ->post('/checkout/24-mock-tests', ['consent' => '1'])
            ->assertRedirect();

        $this->assertArrayHasKey('expires_at', $captured);

        $seconds = $captured['expires_at'] - now()->getTimestamp();
        $minutes = (int) round($seconds / 60);

        $this->assertSame(60, $minutes, 'The default session lifetime is 60 minutes.');
        $this->assertGreaterThanOrEqual(30 * 60, $seconds, 'Stripe refuses anything under 30 minutes.');
        $this->assertLessThanOrEqual(24 * 60 * 60, $seconds, 'Stripe refuses anything over 24 hours.');

        // A setting outside Stripe's range is clamped rather than sent, because
        // the alternative is the API rejecting the request at the one moment
        // the customer is trying to pay.
        config(['courses.checkout_expiry_minutes' => 5]);

        $captured = [];
        $this->fakeStripe($captured);

        $this->actingAs($student)
            ->post('/checkout/24-mock-tests', ['consent' => '1'])
            ->assertRedirect();

        $this->assertGreaterThanOrEqual(30 * 60, $captured['expires_at'] - now()->getTimestamp());
    }

    /**
     * The acceptance of the terms is recorded, and it is the version that was
     * on the page at the time.
     *
     * This is the only evidence that anybody agreed to anything, and it is
     * written when the box is ticked rather than when the money arrives - so
     * an acceptance that leads to an abandoned or failed payment is still on
     * file.
     */
    public function test_the_consent_is_recorded_against_the_purchase(): void
    {
        config(['courses.terms_version' => '2026-01']);

        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        // The live lookup is what makes the second press of the button reuse
        // the open session instead of replacing it, and which of those two
        // happens is what this test is about.
        $created = 0;
        $this->fakeStripeWithLiveLookup($created);

        $this->actingAs($student)
            ->post('/checkout/life-in-the-uk-course', ['consent' => '1'])
            ->assertRedirect();

        $purchase = Purchase::firstOrFail();

        $this->assertNotNull($purchase->terms_accepted_at);
        $this->assertSame('2026-01', $purchase->terms_version);

        // Re-opening the same pending session is not a fresh agreement, so the
        // original timestamp has to survive rather than being overwritten.
        $firstAccepted = $purchase->terms_accepted_at;

        $this->travel(1)->minutes();

        $this->actingAs($student)
            ->post('/checkout/'.$course->slug, ['consent' => '1'])
            ->assertRedirect();

        $reused = $purchase->fresh();

        $this->assertSame(1, $created, 'The second press should have reused the open session.');
        $this->assertTrue(
            $firstAccepted->equalTo($reused->terms_accepted_at),
            'The moment the terms were accepted must not move when the checkout is re-opened.',
        );
        $this->assertSame('2026-01', $reused->terms_version);
    }

    /**
     * A purchase must not be opened without the consent being given.
     *
     * A checkout session created without it would record an acceptance that
     * never happened, on a purchase of digital content that cannot be handed
     * back. Stripe is not faked here: reaching it at all would be the bug.
     */
    public function test_a_checkout_without_consent_is_refused(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $captured = [];
        $this->fakeStripe($captured);

        $response = $this->actingAs($student)
            ->post('/checkout/'.$course->slug);

        // The refusal goes back to the review page, which is the page with the
        // box on it, rather than to a bare error screen or - far worse - out
        // to Stripe.
        $response->assertSessionHasErrors('consent')
            ->assertRedirect(route('checkout.review', $course));

        $this->actingAs($student)
            ->get(route('checkout.review', $course))
            ->assertOk()
            ->assertSee('Check Your Order');

        $this->assertSame([], $captured, 'No Stripe session may be created without consent.');
        $this->assertSame(0, Purchase::count());
    }

    /**
     * Two clicks on Pay make one payment, not two.
     *
     * The first click opens a Stripe session. The second finds the pending
     * purchase, asks Stripe whether that session is still open, and is handed
     * the same URL back. Two sessions for one attempt means two ways to pay
     * for one course, and the customer who completes the wrong one has paid
     * for something they can no longer reach.
     */
    public function test_a_second_click_reuses_the_open_stripe_session(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $created = 0;

        $this->fakeStripeWithLiveLookup($created);

        $first = $this->actingAs($student)->post('/checkout/'.$course->slug, ['consent' => '1']);
        $second = $this->actingAs($student)->post('/checkout/'.$course->slug, ['consent' => '1']);

        $first->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_life-in-the-uk-course');
        $second->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_life-in-the-uk-course');

        $this->assertSame(1, $created, 'Only one Stripe Checkout Session may be created.');
        $this->assertSame(1, Purchase::count(), 'And only one pending purchase row.');
    }

    /**
     * A session Stripe has since closed is not sent anybody back to.
     *
     * The customer would be dropped onto Stripe's expired-session page, which
     * is a dead end with no way forward except back. A fresh session is opened
     * instead, so the button always leads somewhere.
     */
    public function test_an_expired_stripe_session_is_replaced_rather_than_reused(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        // A pending row inside the checkout lifetime whose session is gone.
        Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'stripe_checkout_session_id' => 'cs_test_dead',
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => Purchase::STATUS_PENDING,
        ]);

        $created = 0;
        $this->fakeStripeWithLiveLookup($created, existingStatus: 'expired');

        $this->actingAs($student)
            ->post('/checkout/'.$course->slug, ['consent' => '1'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_life-in-the-uk-course');

        $this->assertSame(1, $created);
        $this->assertSame(2, Purchase::count());
    }

    /**
     * A session that turns out to have been paid is recorded, not duplicated.
     *
     * The customer finished the payment on some other page and came back to
     * press Pay again. The pending row is worth asking about, Stripe says it
     * is paid, and the access is granted through exactly the same idempotent
     * path the webhook uses - so the webhook arriving afterwards updates that
     * row rather than creating a second purchase.
     */
    public function test_a_pending_session_that_turns_out_to_be_paid_is_recorded_not_duplicated(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'stripe_checkout_session_id' => 'cs_test_paid_elsewhere',
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => Purchase::STATUS_PENDING,
        ]);

        $created = 0;
        $this->fakeStripeWithLiveLookup(
            $created,
            existingStatus: 'complete',
            existingPaymentStatus: 'paid',
            student: $student,
            course: $course,
        );

        $this->actingAs($student)
            ->post('/checkout/'.$course->slug, ['consent' => '1'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame(0, $created, 'No new session is opened for a course that has been paid.');
        $this->assertSame(1, Purchase::count());
        $this->assertTrue($student->fresh()->hasPurchased($course));
    }

    /**
     * Two clicks arriving together still make one session.
     *
     * The reuse check is a read followed by a write, so without the lock both
     * clicks can see "no open session" and both create one. This fires the
     * two requests with the lock already held, which is what concurrency looks
     * like from the service's point of view.
     */
    public function test_a_concurrent_second_click_cannot_open_a_second_session(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $created = 0;
        $this->fakeStripeWithLiveLookup($created);

        // Take the lock the way a second request arriving mid-flight would.
        $lock = Cache::lock("checkout:{$student->id}:{$course->id}", 10);
        $this->assertTrue($lock->get());

        $this->actingAs($student)
            ->post('/checkout/'.$course->slug, ['consent' => '1'])
            ->assertRedirect(route('checkout.review', $course));

        $this->assertSame(0, $created, 'The locked-out request must not reach Stripe.');
        $this->assertSame(0, Purchase::count());

        $lock->release();

        // Once released, the same request succeeds.
        $this->actingAs($student)
            ->post('/checkout/'.$course->slug, ['consent' => '1'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_life-in-the-uk-course');

        $this->assertSame(1, $created);
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

        $student = Student::factory()->create();

        $response = $this->actingAs($student)
            ->from('/')
            ->post('/checkout/life-in-the-uk-course', ['consent' => '1']);

        // It goes back to the order review, which is the page the Pay button is
        // on, so the message is read next to the thing that failed...
        $response->assertRedirect(route('checkout.review', 'life-in-the-uk-course'));
        $response->assertSessionHas('error');

        // ...and the message is actually rendered on that page.
        $this->actingAs($student)
            ->get('/checkout/life-in-the-uk-course/review')
            ->assertOk()
            ->assertSee('Online payments are temporarily unavailable');

        // Nothing was written: a failed start must not leave a purchase row
        // that a webhook could later flip to paid.
        $this->assertSame(0, Purchase::count());
    }

    public function test_client_supplied_prices_are_ignored(): void
    {
        $student = Student::factory()->create();

        $captured = [];
        $this->fakeStripe($captured);

        $this->actingAs($student)->post('/checkout/24-mock-tests', [
            'consent' => '1',
            'price' => 1,
            'amount' => 1,
            'currency' => 'usd',
            'course_id' => 999,
        ])->assertRedirect();

        $this->assertSame([['price' => 'price_test_mock_tests', 'quantity' => 1]], $captured['line_items']);
        $this->assertSame(4900, Purchase::firstOrFail()->amount);
    }

    public function test_a_student_cannot_checkout_a_course_they_already_own(): void
    {
        $student = Student::factory()->create();

        $this->markAsPaid($student, $this->course('24-mock-tests'), 'cs_test_existing');

        // Stripe is deliberately not faked here: reaching Stripe would be a bug.
        $this->actingAs($student)
            ->post('/checkout/24-mock-tests', ['consent' => '1'])
            ->assertRedirect('/my-account');

        $this->assertSame(1, Purchase::count());
    }

    public function test_an_inactive_course_cannot_be_bought(): void
    {
        $student = Student::factory()->create();
        $this->course('24-mock-tests')->update(['is_active' => false]);

        $this->actingAs($student)
            ->post('/checkout/24-mock-tests', ['consent' => '1'])
            ->assertNotFound();

        $this->actingAs($student)
            ->get('/checkout/24-mock-tests/review')
            ->assertNotFound();
    }

    /* -----------------------------------------------------------------
     | Webhook
     | ----------------------------------------------------------------- */

    public function test_a_verified_webhook_records_the_purchase_and_grants_access(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event(
            'evt_completed_1',
            'checkout.session.completed',
            $this->sessionObject($student, $course, 'paid')
        ))->assertOk();

        $purchase = Purchase::firstOrFail();

        $this->assertSame(Purchase::STATUS_PAID, $purchase->status);
        $this->assertSame('cs_test_completed', $purchase->stripe_checkout_session_id);
        $this->assertSame('pi_test_intent', $purchase->stripe_payment_intent_id);
        $this->assertSame('cus_test_customer', $purchase->stripe_customer_id);
        $this->assertSame(9900, $purchase->amount);
        $this->assertSame('gbp', $purchase->currency);
        $this->assertSame('evt_completed_1', $purchase->stripe_event_id);
        $this->assertSame($student->email, $purchase->customer_email);
        $this->assertNotNull($purchase->paid_at);

        $this->assertTrue($student->fresh()->hasPurchased($course));
    }

    public function test_a_replayed_webhook_event_does_not_duplicate_the_purchase(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $event = $this->event('evt_replayed', 'checkout.session.completed', $this->sessionObject($student, $course, 'paid'));

        $this->sendWebhook($event)->assertOk();
        $this->sendWebhook($event)->assertOk();
        $this->sendWebhook($event)->assertOk();

        $this->assertSame(1, Purchase::count());
        $this->assertSame(1, StripeWebhookEvent::count());
        $this->assertNotNull(Purchase::firstOrFail()->paid_at);
    }

    public function test_two_distinct_events_for_one_session_still_produce_one_purchase(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event('evt_a', 'checkout.session.completed', $this->sessionObject($student, $course, 'paid')))
            ->assertOk();
        $this->sendWebhook($this->event('evt_b', 'checkout.session.async_payment_succeeded', $this->sessionObject($student, $course, 'paid')))
            ->assertOk();

        $this->assertSame(1, Purchase::count());
        $this->assertSame(Purchase::STATUS_PAID, Purchase::firstOrFail()->status);
    }

    public function test_a_completed_session_that_was_not_paid_does_not_grant_access(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event('evt_unpaid', 'checkout.session.completed', $this->sessionObject($student, $course, 'unpaid')))
            ->assertOk();

        $this->assertFalse($student->fresh()->hasPurchased($course));
        $this->assertSame(Purchase::STATUS_PENDING, Purchase::firstOrFail()->status);
    }

    public function test_a_failed_payment_is_recorded_as_failed(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        // The row checkout opened before the card was declined.
        Purchase::create([
            'student_id' => $student->id,
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
                'student_id' => (string) $student->id,
                'course_id' => (string) $course->id,
            ],
            'last_payment_error' => ['message' => 'Your card was declined.'],
        ]))->assertOk();

        $purchase = Purchase::firstOrFail();

        $this->assertSame(Purchase::STATUS_FAILED, $purchase->status);
        $this->assertSame('pi_test_intent', $purchase->stripe_payment_intent_id);
        $this->assertSame('cs_test_declined', $purchase->stripe_checkout_session_id);
        $this->assertSame('Your card was declined.', $purchase->failure_reason);
        $this->assertFalse($student->fresh()->hasPurchased($course));
    }

    public function test_a_declined_card_cannot_overturn_a_payment_that_already_succeeded(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event('evt_paid_first', 'checkout.session.completed', $this->sessionObject($student, $course, 'paid')))
            ->assertOk();

        $this->sendWebhook($this->event('evt_failed_later', 'payment_intent.payment_failed', [
            'id' => 'pi_test_intent',
            'object' => 'payment_intent',
            'metadata' => [
                'student_id' => (string) $student->id,
                'course_id' => (string) $course->id,
            ],
            'last_payment_error' => ['message' => 'Your card was declined.'],
        ]))->assertOk();

        $this->assertSame(1, Purchase::count());
        $this->assertSame(Purchase::STATUS_PAID, Purchase::firstOrFail()->status);
        $this->assertTrue($student->fresh()->hasPurchased($course));
    }

    public function test_a_failed_intent_we_never_started_is_ignored(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        // Signed by Stripe, but describing a payment this site never opened.
        $this->sendWebhook($this->event('evt_orphan', 'payment_intent.payment_failed', [
            'id' => 'pi_test_orphan',
            'object' => 'payment_intent',
            'metadata' => [
                'student_id' => (string) $student->id,
                'course_id' => (string) $course->id,
            ],
            'last_payment_error' => ['message' => 'Your card was declined.'],
        ]))->assertOk();

        $this->assertSame(0, Purchase::count());
        $this->assertFalse($student->fresh()->hasPurchased($course));
    }

    public function test_an_expired_session_is_marked_expired(): void
    {
        $student = Student::factory()->create();

        Purchase::create([
            'student_id' => $student->id,
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
        $this->assertFalse($student->fresh()->hasPurchased('24-mock-tests'));
    }

    public function test_a_refund_revokes_access(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->sendWebhook($this->event('evt_paid', 'checkout.session.completed', $this->sessionObject($student, $course, 'paid')))
            ->assertOk();

        $this->assertTrue($student->fresh()->hasPurchased($course));

        $this->sendWebhook($this->event('evt_refunded', 'charge.refunded', [
            'id' => 'ch_test_charge',
            'object' => 'charge',
            'payment_intent' => 'pi_test_intent',
            'amount_refunded' => 9900,
        ]))->assertOk();

        $purchase = Purchase::firstOrFail();
        $this->assertSame(Purchase::STATUS_REFUNDED, $purchase->status);
        $this->assertNotNull($purchase->refunded_at);
        $this->assertFalse($student->fresh()->hasPurchased($course));
    }

    public function test_the_webhook_rejects_a_bad_signature(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $payload = json_encode($this->event(
            'evt_forged',
            'checkout.session.completed',
            $this->sessionObject($student, $course, 'paid')
        ));

        $this->postRawWebhook($payload, 't='.time().',v1='.str_repeat('0', 64))
            ->assertStatus(403);

        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, StripeWebhookEvent::count());
    }

    public function test_the_webhook_rejects_a_payload_signed_with_another_secret(): void
    {
        $student = Student::factory()->create();
        $payload = json_encode($this->event('evt_wrongkey', 'checkout.session.completed', $this->sessionObject($student, $this->course('life-in-the-uk-course'), 'paid')));

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

        $student = Student::factory()->create();
        $payload = json_encode($this->event('evt_unconfigured', 'checkout.session.completed', $this->sessionObject($student, $this->course('life-in-the-uk-course'), 'paid')));

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

    public function test_a_signed_in_student_without_the_purchase_gets_a_403(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->actingAs($student)->get(route('learn.index', $course))->assertForbidden();
    }

    public function test_a_purchaser_can_open_the_learning_area(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->markAsPaid($student, $course, 'cs_test_open');

        $this->actingAs($student)->get(route('learn.index', $course))->assertOk();
    }

    public function test_the_course_does_not_unlock_the_mock_test_package(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');
        $mocks = $this->course('24-mock-tests');

        $this->markAsPaid($student, $course, 'cs_test_course_only');

        $this->assertTrue($student->fresh()->hasPurchased($course));
        $this->assertFalse($student->fresh()->hasPurchased($mocks));

        $this->actingAs($student)->get(route('learn.index', $mocks))->assertForbidden();
    }

    public function test_the_mock_test_package_does_not_unlock_the_course(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');
        $mocks = $this->course('24-mock-tests');

        $this->markAsPaid($student, $mocks, 'cs_test_mocks_only');

        $this->assertTrue($student->fresh()->hasPurchased($mocks));
        $this->assertFalse($student->fresh()->hasPurchased($course));

        $this->actingAs($student)->get(route('learn.index', $course))->assertForbidden();
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
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        // Stripe is stubbed to fail: the page must not treat the URL as proof.
        $this->stubStripeLookupToFail();

        $this->actingAs($student)
            ->get('/checkout/success?session_id=cs_test_never_paid')
            ->assertOk()
            ->assertSee('We Are Confirming Your Payment');

        $this->assertFalse($student->fresh()->hasPurchased($course));
        $this->assertSame(0, Purchase::count());
    }

    /**
     * A confirmed payment lands on My Account, with the course highlighted.
     *
     * The course is not announced on this page. It is somewhere they can go
     * and read, and the dashboard already renders every paid purchase, so
     * making them wait on a confirmation screen to be told what they just
     * bought is a step between them and the thing they paid for.
     */
    public function test_a_confirmed_payment_redirects_to_the_account_page_with_the_course_highlighted(): void
    {
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $this->markAsPaid($student, $course, 'cs_test_confirmed');

        $this->actingAs($student)
            ->get('/checkout/success?session_id=cs_test_confirmed')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Payment received. Your course is ready below.')
            ->assertSessionHas('highlight_course', 'life-in-the-uk-course');

        $this->actingAs($student)->get('/my-account')
            ->assertOk()
            ->assertSee('New')
            ->assertSee('Payment received. This is yours.')
            ->assertSee('Start learning');
    }

    /**
     * Nothing on the success page claims a receipt was emailed.
     *
     * Whether Stripe sends one is a setting in the Stripe dashboard, not
     * something this application controls or can observe. Promising a receipt
     * that may never arrive is how somebody who has just paid a hundred pounds
     * ends up emailing us to ask where it is.
     */
    public function test_the_success_page_does_not_claim_a_receipt_was_emailed(): void
    {
        $student = Student::factory()->create();

        $this->markAsPaid($student, $this->course('life-in-the-uk-course'), 'cs_test_no_receipt');

        $response = $this->actingAs($student)
            ->get('/checkout/success?session_id=cs_test_no_receipt')
            ->assertRedirect(route('dashboard'));

        $response->assertSessionMissing('success_receipt');

        $this->actingAs($student)->get('/my-account')
            ->assertOk()
            ->assertDontSee('receipt has been sent', escape: false)
            ->assertDontSee('A receipt has been sent', escape: false);
    }

    /**
     * The waiting page really does wait, and stops.
     *
     * "This page updates automatically" used to be printed on a page that did
     * not update, which is worse than saying nothing: it tells somebody who
     * has just paid that they should sit and watch. The meta refresh is what
     * makes the sentence true, and the counter is what stops it going on for
     * ever if the webhook never arrives.
     */
    public function test_the_waiting_page_refreshes_until_the_maximum_attempt_then_stops(): void
    {
        $student = Student::factory()->create();

        $this->stubStripeLookupToFail();

        // Still waiting: it refreshes, and says so.
        $this->actingAs($student)
            ->get('/checkout/success?session_id=cs_test_pending')
            ->assertOk()
            ->assertSee('We Are Confirming Your Payment')
            ->assertSee('http-equiv="refresh"', escape: false)
            ->assertSee('Still checking', escape: false)
            ->assertSee('(1 of 10)', escape: false)
            ->assertSee('attempt=2', escape: false);

        // The last attempt: no further refresh, and a message that gets help
        // rather than spinning.
        $final = $this->actingAs($student)
            ->get('/checkout/success?session_id=cs_test_pending&attempt=10')
            ->assertOk()
            ->assertSee('This Is Taking Longer Than Usual')
            ->assertSee('info@croydoncollegeofexcellence.co.uk')
            ->assertSee(route('dashboard'), escape: false);

        $this->assertStringNotContainsString('http-equiv="refresh"', $final->getContent());
        $this->assertFalse($student->fresh()->hasPurchased('life-in-the-uk-course'));
    }

    /**
     * The attempt counter is a counter, not a control.
     *
     * It decides whether one more request is made and nothing else - no
     * access, no purchase, no page that would say so. It is clamped to the
     * configured maximum so a hand-typed query string cannot set a browser
     * looping against Stripe forever.
     */
    public function test_the_attempt_counter_cannot_be_pushed_past_the_maximum(): void
    {
        $student = Student::factory()->create();

        $this->stubStripeLookupToFail();

        $this->actingAs($student)
            ->get('/checkout/success?session_id=cs_test_pending&attempt=9999')
            ->assertOk()
            ->assertSee('This Is Taking Longer Than Usual');

        // Absurd or negative values are treated as the first attempt, not
        // echoed into the page.
        $this->actingAs($student)
            ->get('/checkout/success?session_id=cs_test_pending&attempt=-5')
            ->assertOk()
            ->assertSee('(1 of 10)', escape: false);
    }

    /**
     * The success route is throttled, because it is the one page that is
     * designed to be loaded repeatedly.
     */
    public function test_the_success_page_is_throttled(): void
    {
        $student = Student::factory()->create();

        $this->stubStripeLookupToFail();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($student)
                ->get('/checkout/success?session_id=cs_test_'.$i)
                ->assertOk();
        }

        $this->actingAs($student)
            ->get('/checkout/success?session_id=cs_test_over_the_limit')
            ->assertStatus(429);
    }

    public function test_the_success_page_validates_the_session_id(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($student)
            ->get('/checkout/success?session_id='.urlencode("cs_test_' OR 1=1--"))
            ->assertSessionHasErrors('session_id');
    }

    public function test_the_success_page_will_not_show_another_students_purchase(): void
    {
        $owner = Student::factory()->create();
        $other = Student::factory()->create();

        $this->markAsPaid($owner, $this->course('life-in-the-uk-course'), 'cs_test_someone_elses');

        // Stripe confirms the payment, for its real owner. The other person
        // must still see the waiting page, not a purchase.
        $created = 0;
        $this->fakeStripeWithLiveLookup($created, existingStatus: 'complete', existingPaymentStatus: 'paid');

        $this->actingAs($other)
            ->get('/checkout/success?session_id=cs_test_someone_elses')
            ->assertOk()
            ->assertSee('We Are Confirming Your Payment')
            ->assertDontSee('Payment received');

        $this->assertFalse($other->fresh()->hasPurchased('life-in-the-uk-course'));
    }

    public function test_a_cancelled_payment_is_never_recorded_as_paid(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($student)->get('/checkout/cancel')->assertOk()->assertSee('No Payment Was Taken');

        $this->assertSame(0, Purchase::where('status', Purchase::STATUS_PAID)->count());
        $this->assertFalse($student->fresh()->hasPurchased('24-mock-tests'));
    }

    public function test_the_cancel_page_offers_a_retry_for_the_course_that_was_abandoned(): void
    {
        $student = Student::factory()->create();

        // The slug remembered when checkout was started, as it is in
        // PurchaseService::beginCheckout().
        $this->actingAs($student)
            ->withSession(['checkout.course' => '24-mock-tests'])
            ->get('/checkout/cancel')
            ->assertOk()
            ->assertSee('Try 24 Mock Tests Package Again')
            // The retry goes to checkout.start, not straight to the payment
            // form: start is the one place that knows whether this person still
            // needs consent, a payment, or nothing at all.
            ->assertSee(route('checkout.start', '24-mock-tests'), escape: false)
            ->assertDontSee(action([CheckoutController::class, 'store'], '24-mock-tests'), escape: false);

        // Consumed, so a later visit to the cancel page does not repeat it.
        $this->actingAs($student)
            ->get('/checkout/cancel')
            ->assertOk()
            ->assertDontSee('Try 24 Mock Tests Package Again');
    }

    public function test_the_cancel_page_does_not_offer_a_retry_for_an_inactive_course(): void
    {
        $student = Student::factory()->create();
        $this->course('24-mock-tests')->update(['is_active' => false]);

        $this->actingAs($student)
            ->withSession(['checkout.course' => '24-mock-tests'])
            ->get('/checkout/cancel')
            ->assertOk()
            ->assertDontSee('Try 24 Mock Tests Package Again');
    }

    /* -----------------------------------------------------------------
     | No paywall switch
     |
     | The paywall used to be switchable while the lessons and papers were
     | being built and marked, which meant a site could quietly ship with
     | every course open to any signed-in account. There is no switch now:
     | a completed Stripe payment is the only thing that opens the material.
     | These tests hold that line in place.
     | ----------------------------------------------------------------- */

    public function test_a_guest_is_sent_to_login_and_not_shown_a_paywall_bypass(): void
    {
        // A sitting is a database row keyed to a student, so a guest cannot be
        // let in to take a quiz, and there is no longer any state in which
        // one could be waved through.
        $this->get(route('learn.index', $this->course('life-in-the-uk-course')))
            ->assertRedirect(route('login'));
    }

    public function test_a_signed_in_account_without_a_purchase_cannot_open_either_course(): void
    {
        $student = Student::factory()->create();

        foreach (['life-in-the-uk-course', '24-mock-tests'] as $slug) {
            $this->actingAs($student)
                ->get(route('learn.index', $this->course($slug)))
                ->assertForbidden();
        }
    }

    public function test_the_course_page_asks_everybody_without_the_course_to_buy_it(): void
    {
        $this->get('/courses/life-in-the-uk-course')
            ->assertOk()
            ->assertSee('Buy Now')
            ->assertDontSee('Start Learning');

        $this->actingAs(Student::factory()->create())
            ->get('/courses/life-in-the-uk-course')
            ->assertOk()
            ->assertSee('Buy Now')
            ->assertDontSee('Start Learning');
    }

    public function test_a_guest_is_offered_the_buy_button_rather_than_a_free_sign_in(): void
    {
        $this->get('/courses/life-in-the-uk-course')
            ->assertOk()
            ->assertSee('Buy Now')
            ->assertDontSee('Sign In To Start');
    }

    public function test_payments_doctor_states_that_access_needs_a_payment(): void
    {
        $this->artisan('payments:doctor')
            ->expectsOutputToContain('every course needs a completed payment')
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

    /**
     * A guest on a course page gets one Buy button, not a login form.
     *
     * The page used to fork on whether somebody was signed in, and a guest was
     * given "Sign In To Buy" - which sent them to a sign-in form with the
     * course already forgotten, so afterwards there was nothing to come back
     * to. The single button is a link into the journey, which remembers the
     * course across every step of it.
     */
    public function test_a_guest_is_offered_one_buy_button_that_leads_into_the_journey(): void
    {
        $this->get('/courses/life-in-the-uk-course')
            ->assertOk()
            ->assertSee('Life in the UK Course')
            ->assertSee('Buy Now &mdash; £99', escape: false)
            ->assertSee(route('checkout.start', 'life-in-the-uk-course'), escape: false)
            ->assertSee('Already have an account?')
            ->assertSee(route('login'), escape: false);

        // And that journey really does begin by remembering the course.
        $this->get(route('checkout.start', 'life-in-the-uk-course'))
            ->assertRedirect(route('register'))
            ->assertSessionHas('checkout.intended_course', 'life-in-the-uk-course');
    }

    /**
     * Every Buy button in the site is a link into the journey.
     *
     * A POST form to checkout.store behind `auth` was the reason a guest lost
     * their course. The one place that may still post there is the review
     * page, which is behind auth and verified and carries the consent box -
     * anywhere else, a Buy button is doing this wrong.
     */
    public function test_no_buy_button_still_posts_straight_at_checkout(): void
    {
        $allowed = 'pages/checkout/review.blade.php';
        $offenders = [];

        foreach ($this->allBladeViews() as $path) {
            if (str_contains(file_get_contents($path), 'checkout.store') && ! str_ends_with($path, $allowed)) {
                $offenders[] = basename(dirname($path)).'/'.basename($path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These views still post straight at checkout.store: '.implode(', ', $offenders)
        );

        // And the Buy buttons point at the start of the journey.
        $this->get('/')
            ->assertSee(route('checkout.start', 'life-in-the-uk-course'), escape: false);

        $this->get('/courses')
            ->assertSee(route('checkout.start', '24-mock-tests'), escape: false);
    }

    /* -----------------------------------------------------------------
     | Account page
     | ----------------------------------------------------------------- */

    public function test_the_account_page_lists_only_what_the_student_owns(): void
    {
        $student = Student::factory()->create();

        // Nothing is owned, so the purchase card must be absent. The
        // marketing copy may still name the course, so this asserts on the
        // part of the page that only a purchase produces.
        $this->actingAs($student)->get('/my-account')
            ->assertOk()
            ->assertSee('You have not purchased anything yet')
            ->assertDontSee('Your material')
            ->assertDontSee('Open the course');

        $this->markAsPaid($student, $this->course('life-in-the-uk-course'), 'cs_test_dashboard');

        $this->actingAs($student)->get('/my-account')
            ->assertOk()
            ->assertSee('Your material')
            ->assertSee('Open the course')
            ->assertSee(route('learn.index', $this->course('life-in-the-uk-course')), escape: false);

        $this->markAsPaid($student, $this->course('24-mock-tests'), 'cs_test_dashboard_mocks');

        $this->actingAs($student)->get('/my-account')
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
        $student = Student::factory()->create();

        $this->markAsPaid($student, $this->course('life-in-the-uk-course'), 'cs_test_no_downloads');

        $response = $this->actingAs($student)->get('/my-account')->assertOk();

        $response->assertDontSee('Download', escape: false);
        $response->assertDontSee('/my-account/downloads/', escape: false);
        $response->assertSee('Nothing is downloaded.', escape: false);
    }

    public function test_the_lesson_reading_paper_follows_dark_mode(): void
    {
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        // The dark card surface, read from the dark-mode token set rather than
        // hardcoded, so this keeps measuring if the surface is retoned.
        $this->assertNotSame(
            '',
            $darkBlock = $this->darkTokenBlock($css),
            'dark mode has to redefine the --lz-* tokens. Painting the surfaces '
                .'class by class while leaving the tokens light is what broke this page.'
        );

        preg_match('/--lz-surface:\s*([^;]+);/', $darkBlock, $surface);

        $card = $this->flattenDark(trim($surface[1]), $css);

        // --- Root cause, asserted directly -----------------------------------
        // Light mode reads its tokens from :root. If the dark surface is the same
        // value as any of them, the override below is redundant and this fails
        // rather than letting it sit there as a no-op that looks like coverage.
        foreach (['--lz-text', '--lz-muted', '--lz-accent'] as $variable) {
            $light = $this->cssColour($css, $variable);

            $this->assertLessThan(
                4.5,
                $this->contrast($light, $card),
                sprintf(
                    'the light-mode %s value %s is now %.2f:1 on the dark card %s. If the lesson surfaces have '
                        .'been fixed at the token level, the .active-dark-mode --lz-* block is redundant and should go.',
                    $variable,
                    $light,
                    $this->contrast($light, $card),
                    $card
                )
            );
        }

        // --- Every colour token a rule consumes has a dark value ------------
        // This is the assertion that would have caught the report. The old dark
        // block patched six classes and eleven rules read a token directly, so
        // the arithmetic was always going to come out wrong; checking the two
        // sets against each other fails the moment a thirteenth consumer arrives
        // without a dark value.
        //
        // --lz-accent-fill is the one exemption, and it is the point of it: that
        // token exists so the filled chips can keep white text, which only works
        // while the value stays saturated. "Dark mode forgot to lighten this one"
        // is the correct behaviour, so it is named rather than worked around.
        $themeInvariant = ['accent-fill'];

        preg_match_all('/--lz-([a-z-]+):\s*(#[0-9a-fA-F]{3,8}|rgba?\([^;]+\));/', $this->lzTokenBlock($css), $declared);
        $consumed = array_unique($declared[1]);

        foreach ($consumed as $token) {
            if (in_array($token, $themeInvariant, true)) {
                $this->assertStringContainsString(
                    '--lz-'.$token.':',
                    $this->lzTokenBlock($css),
                    sprintf('--lz-%s is consumed but never declared', $token)
                );

                continue;
            }

            $this->assertStringContainsString(
                '--lz-'.$token.':',
                $darkBlock,
                sprintf('--lz-%s is consumed by a light-mode rule and has no value under .active-dark-mode', $token)
            );
        }

        // --- What the reader actually sees, measured -------------------------
        // Every text token gets the same treatment, rather than naming the two the
        // report happened to mention. Pass/fail marks are text on a soft fill
        // inside the same card, they had the same defect, and nothing in the
        // layout makes them less visible than a question.
        //
        // The floor is well above AA on purpose. The light values measured
        // 1.06:1, so "clears AA" is barely a guard - these should read clearly,
        // not merely technically.
        $floors = [
            '--lz-text' => ['the question', 13.0],
            '--lz-muted' => ['the answer', 7.0],
            '--lz-pass' => ['the pass mark', 5.0],
            '--lz-fail' => ['the fail mark', 4.5],
        ];

        foreach ($floors as $variable => [$what, $floor]) {
            preg_match('/'.preg_quote($variable, '/').':\s*([^;]+);/', $darkBlock, $dark);

            // Where the token has a `-soft` companion, that fill is what actually
            // sits behind the text - measuring against the bare card would flatter
            // the ratio by up to a full step. The companions only exist for the
            // accent and pass/fail tokens; text and muted sit straight on the card.
            $companion = preg_quote($variable, '/').'-soft';
            $hasCompanion = (bool) preg_match('/'.$companion.':/', $this->lzTokenBlock($css));

            $backdrop = $card;

            if ($hasCompanion) {
                preg_match('/'.$companion.':\s*([^;]+);/', $darkBlock, $soft);

                $this->assertNotEmpty(
                    $soft,
                    sprintf(
                        '%s (%s) is consumed over a %s fill. That fill is the backdrop the text sits on '
                            .'and it needs a dark value of its own.',
                        $what,
                        $variable,
                        $variable.'-soft'
                    )
                );

                $backdrop = $this->flattenDark(trim($soft[1]), $css, $card);
            }

            $colour = $this->flattenDark(trim($dark[1]), $css, $backdrop);
            $ratio = $this->contrast($colour, $backdrop);

            $this->assertGreaterThanOrEqual(
                4.5,
                $ratio,
                sprintf(
                    '%s (%s) reads only %.2f:1 on %s',
                    $what,
                    $variable,
                    $ratio,
                    $hasCompanion ? 'its soft fill '.$backdrop : 'the dark card'
                )
            );

            $this->assertGreaterThanOrEqual(
                $floor,
                $ratio,
                sprintf(
                    '%s (%s) passes AA at %.2f:1 but is too flat to read comfortably',
                    $what,
                    $variable,
                    $ratio
                )
            );
        }

        // --- The filled chips keep white text --------------------------------
        // --lz-accent lightens for text on the soft fill, but white on that value
        // is 2.14:1. Every rule that fills with the accent and paints white text
        // on top has to read --lz-accent-fill, which stays saturated.
        //
        // The fill is read from :root, not from the dark block, because it is
        // theme-invariant by design. The accent is read from the dark block,
        // because the split between them is the thing being checked.
        preg_match('/--lz-accent-fill:\s*([^;]+);/', $this->lzTokenBlock($css), $fill);
        preg_match('/--lz-accent:\s*([^;]+);/', $darkBlock, $accent);

        $this->assertNotEmpty($fill, 'the filled accent chips need a fill token that white text survives');

        $white = $this->contrast('#ffffff', $this->flatten(trim($fill[1]), $card, $css));
        $this->assertGreaterThanOrEqual(
            4.5,
            $white,
            sprintf('white on --lz-accent-fill %s is only %.2f:1', trim($fill[1]), $white)
        );

        // And the assertion that the two are genuinely different jobs: had they
        // stayed one value, either the text chips or the filled ones would fail.
        $this->assertNotSame(
            trim($accent[1]),
            trim($fill[1]),
            'if the accent text colour and the accent fill are the same value, one of them has to be unreadable'
        );

        // Checked on the *background* declaration, not on whether the token
        // appears anywhere in the rule. A chip whose background was reverted to
        // var(--lz-accent) while its border-color kept --lz-accent-fill satisfies
        // a looser check on the whole body, which is how this kind of half-revert
        // ships unnoticed.
        // Driven by the token's real usages rather than a hand-written selector
        // list. A list is a snapshot, and the two rules that matter most - the
        // hovered lesson-card badge and the active page link - were not on it.
        // Deriving from the stylesheet means a fifth filled chip is covered
        // because it exists, not because someone remembered.
        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        $filled = 0;
        $resolved = $this->flatten(trim($fill[1]), $card, $css);

        foreach ($rules as [, $selector, $body]) {
            // Only rules that put a fill behind text. Using the token for a
            // border alone is not what this is about.
            preg_match('/[\s;]background:\s*([^;]+);/', $body, $background);

            if (! $background || ! str_contains($background[1], 'var(--lz-accent-fill)')) {
                continue;
            }

            // The guard is anchored on a declaration boundary for the same
            // reason: a bare `color:` also matches inside `border-color:`.
            if (! preg_match('/[\s;]color:\s*(#[0-9a-fA-F]{3,8})/', $body, $text)) {
                continue;
            }

            $filled++;

            // expandHex first. luminance() slices six digits straight out of the
            // string, so a shorthand `#fff` reads as `#ff0000` and white-on-blue
            // silently measures as red-on-blue, at 1.43:1. That is what made the
            // unmutated stylesheet fail this check the first time it ran.
            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrast($this->expandHex($text[1]), $resolved),
                sprintf(
                    '%s{%s} paints %s on --lz-accent-fill %s. If the fill has been lightened to track '
                        .'--lz-accent (%s), it needs a token of its own rather than sharing one.',
                    trim($selector),
                    '',
                    $text[1],
                    trim($fill[1]),
                    trim($accent[1] ?? '')
                )
            );
        }

        // Otherwise the loop above proves nothing: it would have skipped every
        // rule it saw and still reported success. Four is the hover badge, the
        // picked-option chip, the current-page jump button and the active link.
        $this->assertGreaterThanOrEqual(
            4,
            $filled,
            'expected at least four rules to fill with --lz-accent-fill behind hex text. Finding fewer '
                .'means a filled surface is reading the lightened accent instead, where white text is 2.14:1.'
        );

        // --- Pagination ------------------------------------------------------
        // `$items->links()` emits Bootstrap 5's markup, because AppServiceProvider
        // calls Paginator::useBootstrapFive(). That is a different class tree from
        // the theme's .rbt-pagination, which already has seven dark-mode rules, so
        // the two paginations on this site look nothing alike. Bootstrap's
        // .page-link is `background-color: #fff` - a row of white boxes on a dark
        // card.
        preg_match(
            '/^\.active-dark-mode \.lz-card \.pagination \.page-link\s*\{([^}]*)\}/m',
            $css,
            $pageLink
        );

        $this->assertNotEmpty(
            $pageLink,
            "Bootstrap's .page-link is background-color: #fff and needs a dark-mode rule of its own"
        );

        preg_match('/background:\s*([^;]+);/', $pageLink[1], $pageBackground);
        // Anchored on a declaration boundary: a bare `color:` also matches inside
        // `border-color:`, and that silently measured the border instead.
        preg_match('/[\s;]color:\s*([^;]+);/', $pageLink[1], $pageColour);

        $pageSurface = $this->flattenDark(trim($pageBackground[1]), $css, $card);

        $this->assertSame(
            $card,
            $pageSurface,
            'the page links should sit on the card surface, not float as white boxes inside it'
        );

        $linkRatio = $this->contrast($this->flattenDark(trim($pageColour[1]), $css, $pageSurface), $pageSurface);

        $this->assertGreaterThanOrEqual(
            4.5,
            $linkRatio,
            sprintf('an inactive page link reads only %.2f:1', $linkRatio)
        );

        // The active link fills, so it gets the saturated token for the same
        // reason the chips do.
        $this->assertMatchesRegularExpression(
            '/^\.active-dark-mode \.lz-card \.pagination \.page-item\.active \.page-link\s*\{[^}]*var\(\s*--lz-accent-fill\s*\)/m',
            $css,
            'the active page link is white on a fill, so it needs --lz-accent-fill rather than the lightened accent'
        );

        // Bootstrap ships no dark pagination at all, so there is nothing to
        // out-specify and no !important to reach. Asserting the selector shape
        // is what proves the cascade actually lands: (0,4,0) against (0,1,0).
        $this->assertStringNotContainsString(
            '!important',
            $pageLink[1],
            'nothing in Bootstrap\'s pagination is !important, so this should win on specificity alone'
        );

        // --- .text-muted, which does need it --------------------------------
        preg_match('/^\.active-dark-mode \.lz-card \.text-muted\s*\{([^}]*)\}/m', $css, $muted);

        $this->assertNotEmpty(
            $muted,
            'the "Lesson 3 of 10" and "Page 1 of 4" lines are Bootstrap\'s .text-muted, which has no dark value'
        );

        $this->assertStringContainsString(
            '!important',
            $muted[1],
            'Bootstrap\'s own .text-muted is !important, so without it this rule is dead'
        );

        $mutedRatio = $this->contrast($this->flattenDark(trim($muted[1]), $css, $card), $card);

        $this->assertGreaterThanOrEqual(
            4.5,
            $mutedRatio,
            sprintf('the muted meta line reads only %.2f:1', $mutedRatio)
        );
    }

    public function test_the_lesson_question_and_answer_text_is_readable(): void
    {
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        // `html { font-size: 10px }`, so a rem here is a tenth of its face value.
        // 1.55rem was 15.5px, which is small for exam content the reader is
        // meant to be studying rather than skimming.
        preg_match('/^html\s*\{[^}]*font-size:\s*([\d.]+)px/m', $css, $root);

        $this->assertNotEmpty($root, 'expected an explicit root font-size to scale these against');

        $base = (float) $root[1];

        foreach ([
            // The answer and the explanation are supporting text under a bold
            // question, so they take a step down rather than matching exactly -
            // hierarchy, not a slab. Both still have to clear a legible size.
            ['.lz-item .lz-q', 'the question', 18.0],
            ['.lz-item .lz-a', 'the answer', 17.0],
            ['.lz-opt', 'a quiz option, which is the same question once it is playable', 18.0],
            ['.lz-explain', 'the explanation, which is the answer written out', 17.0],
        ] as [$selector, $what, $floor]) {
            // All matched rules, and only the first match used. A single selector
            // has several rules in this stylesheet - `.lz-explain` also appears in
            // a `.is-pass`/`is-fail` grouping - so `preg_match` with an offset-free
            // pattern returns whichever the engine reached first and the rest go
            // unchecked. Whichever one is picked, a `font-size` must be found: if
            // the size lived in the rule being skipped, this would report a
            // missing size and ask for the reason, rather than passing silently.
            preg_match_all(
                '/'.str_replace(' ', '\s+', preg_quote($selector, '/')).'\s*\{([^}]*)\}/',
                $css,
                $matches
            );

            $this->assertNotEmpty(
                $matches[1],
                sprintf('expected %s in the stylesheet', $selector)
            );

            $sizes = [];

            foreach ($matches[1] as $body) {
                if (preg_match('/font-size:\s*([\d.]+)rem/', $body, $size)) {
                    $sizes[] = (float) $size[1];
                }
            }

            // Any rule that does set a size must clear the floor. Checking the
            // whole set rather than the first hit is what catches a later,
            // narrower rule quietly reinstating the old size.
            foreach ($sizes as $rem) {
                $pixels = $rem * $base;

                $this->assertGreaterThanOrEqual(
                    $floor,
                    $pixels,
                    sprintf(
                        '%s (%s) renders at %.1fpx on a %dpx root, under the %.1fpx floor. 1.55rem was '
                            .'15.5px, which is small for exam content meant to be studied rather than skimmed.',
                        $what,
                        $selector,
                        $pixels,
                        (int) $base,
                        $floor
                    )
                );
            }

            $this->assertNotEmpty(
                $sizes,
                sprintf('%s (%s) sets no rem font-size, so its rendered size is unchecked', $what, $selector)
            );
        }

        // The answer is supporting text under a bold question, so it takes a
        // size step down rather than matching it exactly - hierarchy, not a
        // flat slab. Both still clear their own floor.
        preg_match('/^\.lz-item \.lz-q\s*\{[^}]*font-size:\s*([\d.]+)rem/m', $css, $question);
        preg_match('/^\.lz-item \.lz-a\s*\{[^}]*font-size:\s*([\d.]+)rem/m', $css, $answer);

        $this->assertGreaterThan(
            (float) ($answer[1] ?? 0),
            (float) ($question[1] ?? 0),
            'the question should read as the louder of the two; they were the same size apart from .1rem'
        );
    }

    public function test_the_learn_buttons_are_readable_in_dark_mode(): void
    {
        $course = $this->course('life-in-the-uk-course');
        $student = Student::factory()->create();

        $this->markAsPaid($student, $course, 'cs_test_learn_buttons');

        [$lesson] = $this->makeLessons($course, 1);

        $this->actingAs($student)->post(route('learn.lessons.complete', [$course, $lesson]));

        // The unread button only appears once the lesson is read, so this is the
        // state the new button actually ships in.
        $html = $this->actingAs($student)->get(route('learn.lessons.show', [$course, $lesson]))
            ->assertOk()
            ->assertSee('Mark as unread')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*class="[^"]*btn-outline-secondary[^"]*"[^>]*>\s*Mark as unread/s',
            $html,
            'the unread control should be a real submit button, not a link - the reader works '
                .'with scripting off, and only a form submission survives that'
        );

        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        // --- The rule has to exist, and on the right selector -----------------
        // Scoped to .lz-card rather than lifted onto .btn-outline-secondary
        // globally: this is the lesson reader and the paper player's nav, not a
        // sitewide restyle.
        preg_match(
            '/\.active-dark-mode\s+\.lz-card\s+\.btn-outline-secondary\s*(?:,\s*[^{]*)?\{([^}]*)\}/',
            $css,
            $rule
        );

        $this->assertNotEmpty($rule, 'expected a dark-mode rule for .btn-outline-secondary on an lz-card');

        // The label specifically, not just any colour in the block.
        $this->assertMatchesRegularExpression(
            '/(?:^|[\s;])color:\s*var\(--lz-muted\)/',
            $rule[1],
            'the outline label should take --lz-muted. Bootstrap paints --bs-secondary, which is '
                .'3.16:1 on the dark card - below AA for a label and barely over the 3:1 a control '
                .'border needs, so the button reads as a smudge rather than something to press.'
        );

        $this->assertMatchesRegularExpression(
            '/(?:^|[\s;])border-color:\s*var\(--lz-muted\)/',
            $rule[1],
            'a border left at --bs-secondary is the same 3.16:1, so the button has no visible edge'
        );

        // --- And it has to actually measure -----------------------------------
        // Asserting the token is named is not the same as asserting it reads.
        // flattenDark, not flatten: flatten resolves var() against the first
        // match in the file, which for these tokens is the light :root value, so
        // it would hand back #ffffff for a card painting #27272e.
        $card = $this->flattenDark('var(--lz-surface)', $css);

        $label = $this->flattenDark('var(--lz-muted)', $css, $card);

        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrast($label, $card),
            sprintf(
                'the outline button label resolves to %s on a card of %s, which must clear 4.5:1. '
                    .'It does not.',
                $label,
                $card
            )
        );

        // Hover: Bootstrap's own is white on #6c757d, which passes, but the rule
        // here replaces it, so the replacement has to be checked too.
        preg_match(
            '/\.active-dark-mode\s+\.lz-card\s+\.btn-outline-secondary:hover\s*\{([^}]*)\}/',
            $css,
            $hover
        );

        $this->assertNotEmpty($hover, 'the hover state needs a rule, since the base one overrides it');

        preg_match('/(?:^|[\s;])color:\s*([^;]+);/', $hover[1], $hoverColour);

        $hoverFlat = str_contains($hoverColour[1] ?? '', 'var(')
            ? $this->flattenDark($hoverColour[1], $css, $this->flattenDark('var(--lz-surface-2)', $css, $card))
            : $this->expandHex(trim($hoverColour[1] ?? ''));

        $hoverBg = $this->flattenDark('var(--lz-surface-2)', $css, $card);

        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrast($hoverFlat, $hoverBg),
            sprintf('on hover the label resolves to %s on %s, which must clear 4.5:1.', $hoverFlat, $hoverBg)
        );

        // --- The dark value is a real override -------------------------------
        // Not "the light block does not mention the token" - it does, that is
        // where the light value lives. What matters is that the two differ, so
        // the rule above is doing something rather than restating daylight.
        $darkBlock = $this->darkTokenBlock($css);

        preg_match('/--lz-muted:\s*([^;]+);/', $this->lzTokenBlock($css), $lightMuted);
        preg_match('/--lz-muted:\s*([^;]+);/', $darkBlock, $darkMuted);

        $this->assertNotEmpty($darkMuted, 'expected --lz-muted to be overridden for dark mode');

        $this->assertNotSame(
            trim($lightMuted[1] ?? ''),
            trim($darkMuted[1] ?? ''),
            sprintf(
                '--lz-muted is %s in both themes, so the dark override is a no-op and the outline '
                    .'button falls back to the light value on a dark card.',
                trim($lightMuted[1] ?? '?')
            )
        );

        // And the light theme's own pairing must still clear AA, or scoping the fix to
        // dark mode would have traded a dark failure for a light one. This uses
        // flatten(), not flattenDark(): flatten resolves var() against the first
        // match in the file, which for these tokens is the light :root value -
        // which is exactly what is wanted here. flattenDark would resolve against
        // the dark block and silently re-measure the pairing above.
        $lightCard = $this->flatten('var(--lz-surface)', '#ffffff', $css);
        $lightLabel = $this->flatten('var(--lz-muted)', $lightCard, $css);

        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrast($lightLabel, $lightCard),
            sprintf(
                'the light theme pairs %s on %s, which must clear 4.5:1 on its own merits',
                $lightLabel,
                $lightCard
            )
        );
    }

    public function test_the_paper_sidebar_scrolls_away_with_the_page(): void
    {
        $course = $this->course('life-in-the-uk-course');
        $student = Student::factory()->create();

        $this->markAsPaid($student, $course, 'cs_test_paper_sidebar');

        // 24 questions, so the jump grid is three or four rows deep - the shape
        // that made the pinned rail outgrow the space below the header.
        $quiz = $this->makeQuiz($course, 'mock_test', 1, ['a', 'b', 'c', 'd'], 24);

        $html = $this->actingAs($student)->get(route('learn.quizzes.play', [$course, $quiz]))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            24,
            substr_count($html, 'data-goto'),
            'expected a jump button per question, so this is the tall-sidebar case'
        );

        // Isolate the sidebar column so an assertion cannot pass on a pin
        // belonging to something else on the page.
        preg_match('/<div class="col-lg-4">(.*?)<\/div>\s*<\/div>\s*<\/div>/s', $html, $column);

        $this->assertNotEmpty($column, 'could not find the paper sidebar column');

        $sidebar = $column[1];

        // --- Nothing in the sidebar is pinned ---------------------------------
        // This is the reported bug stated as a structural fact. The progress card
        // used to be sticky, which put the rules card - and the Finish button -
        // underneath it.
        //
        // `position: sticky` creates a stacking context even at z-index:auto, so
        // the pinned box paints above its in-flow siblings while keeping its slot
        // in flow. The rules card then scrolled up into the pinned card's band and
        // disappeared under it.
        $this->assertDoesNotMatchRegularExpression(
            '/position\s*:\s*sticky/i',
            $sidebar,
            'nothing in the paper sidebar may be sticky. A pinned card paints above its in-flow '
                .'siblings and hides the Finish button underneath it.'
        );

        // Same thing, caught in the stylesheet rather than the markup. A pin could
        // also be introduced entirely in CSS, which is where the offset lived once
        // it was not an inline style.
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        $this->assertDoesNotMatchRegularExpression(
            '/\.lz-aside[^{}]*\{[^}]*position\s*:\s*sticky/is',
            $css,
            '.lz-aside is pinned again. See the "Paper sidebar" note in styles.css for why a cap, '
                .'an internal scroll and a pinned column all end up worse.'
        );

        // --- And nothing in it scrolls either ---------------------------------
        // A capped sticky rail needs overflow-y, and that is a second scroll
        // context inside the page: the wheel stops moving the page and the rail
        // captures the gesture. There is no rail any more, so there is nothing to
        // be a scroll container.
        $this->assertDoesNotMatchRegularExpression(
            '/(?:overflow|max-height)\s*:/i',
            $sidebar,
            'the sidebar must not be its own scroll container. The page is the only scroll '
                .'context; a nested one captures the wheel.'
        );

        // --- No dead rail left behind -----------------------------------------
        // The wrapper and its rules were removed rather than left in place
        // unstyled, so nothing may reintroduce them by name.
        $this->assertStringNotContainsString(
            'lz-rail',
            $html,
            'the .lz-rail wrapper is gone; an unstyled element should not linger in the markup'
        );

        $this->assertStringNotContainsString(
            'lz-rail',
            $css,
            'the rail had no styling left once it stopped being pinned, so its rules should be '
                .'gone too rather than sitting in the stylesheet matching nothing'
        );

        // --- Both cards are still there, in order -----------------------------
        // Dropping the pin must not have dropped the sidebar's content with it:
        // the progress card first, the rules card second.
        $this->assertStringContainsString(
            'data-progress-bar',
            $sidebar,
            'the progress bar should still be in the sidebar'
        );

        $this->assertMatchesRegularExpression(
            '/minutes\s*&middot;\s*pass at/s',
            $sidebar,
            'the "N minutes - pass at X/Y" card should still be in the sidebar'
        );

        // The one control that must never be lost.
        $this->assertStringContainsString(
            'form="paper-form"',
            $sidebar,
            'the Finish button posts the paper via form="paper-form"; it has to still be here'
        );

        $this->assertLessThan(
            strpos($sidebar, 'form="paper-form"'),
            strpos($sidebar, 'data-progress-bar'),
            'the progress card should come before the rules card, so the sidebar reads top-down '
                .'as progress, then rules, then finish'
        );
    }

    /** The block that declares the `--lz-*` tokens, comments and all. */
    private function lzTokenBlock(string $css): string
    {
        // Anchored on `--lz-radius`, which is declared once. By the time this
        // section is reached the file holds several `:root` blocks, so matching
        // on `:root` would pick whichever came last.
        preg_match('/\{([^{}]*--lz-radius:[^{}]*)\}/', $css, $root);

        return $root[1] ?? '';
    }

    /** The `.active-dark-mode` block that redefines the `--lz-*` tokens. */
    private function darkTokenBlock(string $css): string
    {
        // Anchored on `--lz-surface`, not on `.active-dark-mode {` alone: the file
        // holds other single-declaration `.active-dark-mode` blocks and matching
        // the first of those would read an unrelated rule's body.
        preg_match('/^\.active-dark-mode\s*\{([^}]*--lz-surface:[^}]*)\}/m', $css, $block);

        return $block[1] ?? '';
    }

    /**
     * Resolve a `var(--lz-*)` reference the way the browser would in dark mode.
     *
     * `flatten()` resolves against the first match in the file, which for these
     * tokens is the light `:root` declaration - so handed a dark-mode rule
     * directly it would hand back `#ffffff` for a card that paints `#27272e`.
     */
    private function flattenDark(string $colour, string $css, string $background = '#ffffff'): string
    {
        $dark = $this->darkTokenBlock($css);

        if (preg_match('/var\(\s*(--lz-[\w-]+)\s*\)/', $colour, $reference)) {
            preg_match('/'.preg_quote($reference[1], '/').':\s*([^;]+);/', $dark, $token);

            $colour = trim($token[1] ?? $this->cssColour($css, $reference[1]));
        }

        return $this->flatten($colour, $background, $css);
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
     * A fake Stripe that can also answer questions about an existing session.
     *
     * Reusing an open Checkout Session means asking Stripe what state the old
     * one is in, which the plain fake above does not model. This one does:
     * `existingStatus` and `existingPaymentStatus` decide what Stripe says
     * about a session it is already holding, and `$created` counts the new
     * sessions opened, which is how the "only one session per purchase
     * attempt" tests see what happened.
     *
     * Passing the buyer and the course makes the answered session carry the
     * metadata Stripe would really have on it, which matters whenever the
     * answer is about money: a session that reports itself as paid is written
     * into the database, and it can only be attached to a purchase if it says
     * who it belongs to. Without them the fake reports a payment for nobody.
     *
     * @param  string  $existingStatus  Stripe's `status` for a session it already has.
     * @param  string  $existingPaymentStatus  Stripe's `payment_status` for the same.
     */
    protected function fakeStripeWithLiveLookup(
        int &$created,
        string $existingStatus = 'open',
        string $existingPaymentStatus = 'unpaid',
        ?Student $student = null,
        ?Course $course = null
    ): void {
        $onCreate = function (array $params) use (&$created) {
            $created++;
        };

        $mock = $this->stripeMock($onCreate);

        $mock->shouldReceive('retrieveCheckoutSession')->andReturnUsing(
            function (string $sessionId) use ($existingStatus, $existingPaymentStatus, $student, $course) {
                $session = [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'status' => $existingStatus,
                    'payment_status' => $existingPaymentStatus,
                    'url' => "https://checkout.stripe.com/c/pay/{$sessionId}",
                ];

                if ($student && $course) {
                    $session += [
                        'client_reference_id' => (string) $student->id,
                        'metadata' => [
                            'student_id' => (string) $student->id,
                            'course_id' => (string) $course->id,
                            'course_slug' => $course->slug,
                        ],
                        'customer_details' => [
                            'email' => $student->email,
                            'name' => $student->name,
                        ],
                        'amount_total' => $course->price,
                        'currency' => $course->currency,
                        'payment_intent' => 'pi_test_'.$sessionId,
                    ];
                }

                return Session::constructFrom($session);
            }
        );

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

    protected function markAsPaid(Student $student, Course $course, string $sessionId): Purchase
    {
        return Purchase::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'stripe_checkout_session_id' => $sessionId,
            'stripe_payment_intent_id' => 'pi_'.substr(sha1($sessionId), 0, 12),
            'amount' => $course->price,
            'currency' => $course->currency,
            'status' => Purchase::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    protected function sessionObject(Student $student, Course $course, string $paymentStatus): array
    {
        return [
            'id' => 'cs_test_completed',
            'object' => 'checkout.session',
            'payment_status' => $paymentStatus,
            'status' => 'complete',
            'amount_total' => $course->price,
            'currency' => $course->currency,
            'client_reference_id' => (string) $student->id,
            'customer' => 'cus_test_customer',
            'payment_intent' => 'pi_test_intent',
            'customer_details' => [
                'email' => $student->email,
                'name' => $student->name,
            ],
            'metadata' => [
                'student_id' => (string) $student->id,
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

    /**
     * Lowest contrast ratio a hero paragraph can hit anywhere in the band its
     * `.section-title` occupies, given its colour.
     *
     * The section-title is the first content inside a `.rbt-conatct-area`,
     * which carries `.rbt-section-gap`'s 40px top padding, so the text sits in
     * the top 40% of the section. That band is walked at 1% steps against
     * both ends of the gradient, with the `::after` white wash applied at the
     * same 0%-to-10% alpha ramp the stylesheet uses.
     */
    /** Every Blade view, for tests that need to know what the templates use. */
    private function bladeFiles(): array
    {
        return $this->allBladeViews();
    }

    private function worstHeroContrast(string $colour): float
    {
        $css = (string) file_get_contents(public_path('assets/css/styles.css'));

        $worst = INF;

        foreach (['--color-secondary', '--color-primary'] as $variable) {
            $end = $this->cssColour($css, $variable);

            for ($percent = 0; $percent <= 40; $percent++) {
                $background = $this->blend('#ffffff', $end, 1.0 - 0.9 * ($percent / 100));
                $foreground = $this->flatten($colour, $background);
                $worst = min($worst, $this->contrast($foreground, $background));
            }
        }

        return $worst;
    }

    /** Resolve a `--color-*` custom property from the stylesheet. */
    private function cssColour(string $css, string $variable): string
    {
        preg_match('/'.preg_quote($variable, '/').':\s*(#[0-9a-fA-F]{3,8})/', $css, $matches);

        return $this->expandHex($matches[1] ?? '#000000');
    }

    /** Composite a possibly translucent colour over an opaque background. */
    private function flatten(string $colour, string $background, string $css = ''): string
    {
        // `--color-white-off` is `#ffffffcb`, so a var() reference has to be
        // followed and the alpha has to be honoured - dropping either would
        // quietly measure the wrong colour.
        if (preg_match('/var\(\s*(--[\w-]+)\s*\)/', $colour, $reference)) {
            $colour = $this->cssColour($css, $reference[1]);
        }

        if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)/i', $colour, $m)) {
            $rgb = sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]);
            $alpha = isset($m[4]) && $m[4] !== '' ? (float) $m[4] : 1.0;

            return $this->blend($rgb, $background, $alpha);
        }

        $hex = ltrim($colour, '#');

        if (strlen($hex) === 8) {
            return $this->blend('#'.substr($hex, 0, 6), $background, hexdec(substr($hex, 6, 2)) / 255);
        }

        return $this->expandHex($colour);
    }

    /** $fg painted over $bg at $alpha, as hex. */
    private function blend(string $fg, string $bg, float $alpha): string
    {
        $out = '';

        foreach ([0, 2, 4] as $offset) {
            $f = hexdec(substr($fg, 1 + $offset, 2));
            $b = hexdec(substr($bg, 1 + $offset, 2));
            $out .= str_pad(dechex((int) round($f * $alpha + $b * (1 - $alpha))), 2, '0', STR_PAD_LEFT);
        }

        return '#'.$out;
    }

    /** WCAG 2.1 relative luminance. */
    private function luminance(string $hex): float
    {
        $channels = array_map(function (int $value) {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))]);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function contrast(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** `#abc` -> `#aabbcc`, so the luminance maths always gets six digits. */
    private function expandHex(string $hex): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#'.substr($hex, 0, 6);
    }
}
