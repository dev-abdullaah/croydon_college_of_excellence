<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TutorMailController;
use App\Http\Controllers\ContactMailController;
use App\Http\Controllers\EnrollMailController;
use App\Http\Controllers\AssesmentMailController;


Route::post('/enroll/send', [EnrollMailController::class, 'sendMail'])->name('enroll.send');

Route::post('/assesment/send', [AssesmentMailController::class, 'sendMail'])->name('assesment.send');

Route::post('/contact/send', [ContactMailController::class, 'sendMail'])->name('contact.send');

Route::post('/tutor/send', [TutorMailController::class, 'sendMail'])->name('tutor.send');


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('website.pages.home');
});

Route::get('/director-message', function () {
    return view('website.pages.directors_msg');
});

Route::get('/our-team', function () {
    return view('website.pages.our_team');
});

Route::get('/our-mission', function () {
    return view('website.pages.our_mission');
});

Route::get('/our-policy', function () {
    return view('website.pages.our_policy');
});

Route::get('/become-tutor', function () {
    return view('website.pages.become_tutor');
});

Route::get('/courses-regular', function () {
    return view('website.pages.courses_regular');
});

Route::get('/courses-send', function () {
    return view('website.pages.courses_send');
});

Route::get('/contact-us', function () {
    return view('website.pages.contact_us');
});

Route::get('/gallery', function () {
    return view('website.pages.gallery');
});

Route::get('/enroll-now', function () {
    return view('website.pages.enroll_now');
});

Route::get('/free-assesment', function () {
    return view('website.pages.free_assesment');
});

Route::get('/regular-english', function () {
    return view('website.pages.courses_regular.regular_english');
});

Route::get('/regular-math', function () {
    return view('website.pages.courses_regular.regular_math');
});

Route::get('/regular-science', function () {
    return view('website.pages.courses_regular.regular_science');
});

Route::get('/regular-exam', function () {
    return view('website.pages.courses_regular.regular_exam');
});

Route::get('/regular-sat', function () {
    return view('website.pages.courses_regular.regular_sat');
});

Route::get('/regular-skills', function () {
    return view('website.pages.courses_regular.regular_skills');
});

Route::get('/regular-esol', function () {
    return view('website.pages.courses_regular.regular_esol');
});

Route::get('/regular-ielts', function () {
    return view('website.pages.courses_regular.regular_ielts');
});

Route::get('/regular-ukvi', function () {
    return view('website.pages.courses_regular.regular_ukvi');
});

Route::get('/regular-uk-life', function () {
    return view('website.pages.courses_regular.regular_uk_life');
});


Route::get('/send-english', function () {
    return view('website.pages.courses_send.send_english');
});

Route::get('/send-math', function () {
    return view('website.pages.courses_send.send_math');
});

Route::get('/send-science', function () {
    return view('website.pages.courses_send.send_science');
});

Route::get('/send-exam', function () {
    return view('website.pages.courses_send.send_exam');
});

Route::get('/send-sat', function () {
    return view('website.pages.courses_send.send_sat');
});

Route::get('/send-skills', function () {
    return view('website.pages.courses_send.send_skills');
});

Route::get('/send-esol', function () {
    return view('website.pages.courses_send.send_esol');
});

Route::get('/send-literacy', function () {
    return view('website.pages.courses_send.send_literacy');
});

Route::get('/send-humanities', function () {
    return view('website.pages.courses_send.send_humanities');
});

Route::get('/send-business', function () {
    return view('website.pages.courses_send.send_business');
});

Route::get('/send-ict', function () {
    return view('website.pages.courses_send.send_ict');
});

Route::get('/send-life-skills', function () {
    return view('website.pages.courses_send.send_life_skills');
});

Route::get('/send-music', function () {
    return view('website.pages.courses_send.send_music');
});

