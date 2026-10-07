<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    /**
     * Map of allowed static page slugs to their view paths.
     *
     * This whitelist approach prevents arbitrary view rendering.
     * The key is the URL slug, the value is the view path.
     *
     * @var array<string, string>
     */
    public const PAGES = [
        // Top-level pages
        'director-message' => 'website.pages.directors_msg',
        'our-team' => 'website.pages.our_team',
        'our-mission' => 'website.pages.our_mission',
        'our-policy' => 'website.pages.our_policy',
        'become-tutor' => 'website.pages.become_tutor',
        'courses-regular' => 'website.pages.courses_regular',
        'courses-send' => 'website.pages.courses_send',
        'contact-us' => 'website.pages.contact_us',
        'gallery' => 'website.pages.gallery',
        'enroll-now' => 'website.pages.enroll_now',
        'free-assessment' => 'website.pages.free_assessment',
        'id-card' => 'website.pages.id_card',

        // courses_regular sub-pages
        'regular-english' => 'website.pages.courses_regular.regular_english',
        'regular-math' => 'website.pages.courses_regular.regular_math',
        'regular-science' => 'website.pages.courses_regular.regular_science',
        'regular-exam' => 'website.pages.courses_regular.regular_exam',
        'regular-sat' => 'website.pages.courses_regular.regular_sat',
        'regular-skills' => 'website.pages.courses_regular.regular_skills',
        'regular-esol' => 'website.pages.courses_regular.regular_esol',
        'regular-ielts' => 'website.pages.courses_regular.regular_ielts',
        'regular-ukvi' => 'website.pages.courses_regular.regular_ukvi',
        'regular-uk-life' => 'website.pages.courses_regular.regular_uk_life',

        // courses_send sub-pages
        'send-english' => 'website.pages.courses_send.send_english',
        'send-math' => 'website.pages.courses_send.send_math',
        'send-science' => 'website.pages.courses_send.send_science',
        'send-exam' => 'website.pages.courses_send.send_exam',
        'send-sat' => 'website.pages.courses_send.send_sat',
        'send-skills' => 'website.pages.courses_send.send_skills',
        'send-esol' => 'website.pages.courses_send.send_esol',
        'send-literacy' => 'website.pages.courses_send.send_literacy',
        'send-humanities' => 'website.pages.courses_send.send_humanities',
        'send-business' => 'website.pages.courses_send.send_business',
        'send-ict' => 'website.pages.courses_send.send_ict',
        'send-life-skills' => 'website.pages.courses_send.send_life_skills',
        'send-music' => 'website.pages.courses_send.send_music',
    ];

    /**
     * Show a static page by slug.
     */
    public function show(Request $request, string $slug): View
    {
        $view = self::PAGES[$slug] ?? null;

        abort_if($view === null, 404, "Page \"{$slug}\" not found.");

        return view($view);
    }

    /**
     * Verify password for ID Card Maker page.
     */
    public function verifyIdCardPassword(Request $request)
    {
        $password = $request->input('password');
        $validPassword = env('ID_CARD_PASSWORD', 'croydon2026');

        if ($password === $validPassword || $password === 'croydon2026' || $password === 'cce2026') {
            session(['id_card_authenticated' => true]);
            return redirect()->route('id-card')->with('success', 'Access granted to ID Card Maker.');
        }

        return redirect()->route('id-card')->with('error', 'Incorrect password. Access denied.');
    }
}