{{--
    The "returning student" banner that sits above the course cards.

    Three states, not two. This used to print "Already enrolled? Sign in to
    jump straight to your study cards" for everybody, including people who
    were already signed in - asking somebody who is signed in to sign in.
    Telling somebody who has bought nothing that their mock test papers are
    waiting was the same mistake in the other direction: it sent them to an
    account with nothing in it.

    So the sentence has to follow the account:
      - a guest is told how to get back in;
      - a signed in learner with a paid course is sent to it;
      - a signed in learner with no paid course is pointed at the courses.

    What counts as a paid course is not decided here. It is asked of the
    model, which asks User::hasPurchased(), so this banner can never disagree
    with the buttons further down the page or with the checkout flow.

    @param bool $compact  Tighter spacing, used by the homepage promo.
--}}
@php
    $learner = auth()->user();
    $ownedCourses = $learner?->purchasedCourses() ?? collect();
    $courseCount = $ownedCourses->count();
    $firstName = $learner?->name
        ? \Illuminate\Support\Str::before($learner->name, ' ')
        : null;
@endphp

{{--
    Colours live in the `.enrollment-banner` class rather than here. An inline
    style outranks every stylesheet rule bar an `!important` one, so while the
    palette was written here there was nothing a dark-mode rule could do
    without `!important` - which is why the banner stayed a pale blue box in
    dark mode. Only the compact max-width stays inline, being conditional.
--}}
<div class="enrollment-banner alert alert-info d-flex align-items-center justify-content-between flex-wrap text-start {{ $compact ?? false ? 'gap-2' : 'gap-3' }} {{ $compact ?? false ? 'mt--25 mb--10 mx-auto' : 'p-3 radius-10' }}"
    style="{{ $compact ?? false ? 'max-width: 820px;' : '' }}">
    <div class="d-flex align-items-center">
        <i class="feather-{{ $learner && $courseCount ? 'book-open' : 'user-check' }} {{ $compact ?? false ? 'fs-4' : 'fs-3' }} me-3 text-primary"></i>

        @if (! $learner)
            <div>
                <strong class="d-block">Already enrolled?</strong>
                <span class="small">Sign in to jump straight to your study cards, lessons, and mock test papers.</span>
            </div>
        @elseif ($courseCount)
            <div>
                <strong class="d-block">Welcome back{{ $firstName ? ', '.$firstName : '' }}.</strong>
                <span class="small">
                    Your {{ \Illuminate\Support\Str::plural('course', $courseCount) }} {{ $courseCount === 1 ? 'is' : 'are' }} ready &mdash;
                    pick up your study cards, lessons, and mock test papers.
                </span>
            </div>
        @else
            <div>
                <strong class="d-block">You're signed in{{ $firstName ? ', '.$firstName : '' }}.</strong>
                <span class="small">You don't have a course yet. Choose one below to start learning.</span>
            </div>
        @endif
    </div>

    @if (! $learner)
        <a href="{{ route('login') }}" class="btn btn-sm btn-primary text-nowrap">Sign In To Your Account</a>
    @elseif ($courseCount)
        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-primary text-nowrap">Go to My Account</a>
    @else
        <a href="#course-list" class="btn btn-sm btn-primary text-nowrap">See The Courses</a>
    @endif
</div>