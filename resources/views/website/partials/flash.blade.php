{{--
    Session feedback, as a floating message.

    This used to be a bar of alert markup in a container at the top of the
    page, which put it directly under the header where it read as part of the
    navigation, and made it part of the document flow, so it shoved the page
    content down by its own height for as long as it was on screen.

    Fixed to the corner instead: it overlays the page, so the layout underneath
    is exactly where it was, and it carries a z-index above the sticky header
    so nothing can slide over the top of it and hide the message.

    Bottom right rather than top right, because the top of this page belongs to
    the header and the sticky header follows you down the page. A message up
    there is fighting the navigation for the same few hundred pixels.

    Only the messages that merely confirm something take themselves off the
    screen. A validation summary does not: a list of errors that vanishes
    before it has been read is worse than no list at all, and there is no safe
    amount of time to pick. So do warnings and errors, which carry the same
    sort of thing to do. The close button is always there either way, and
    hovering or tabbing into a self-dismissing message holds it open and
    restarts the countdown, so nobody is timed out mid-sentence.
--}}

@php
    $siteNotices = array_filter([
        'success' => session('success'),
        'info' => session('info'),
        'warning' => session('warning'),
        'error' => session('error'),
    ]);

    /*
     | Milliseconds, and only for the levels that are safe to lose. A success
     | message says what already happened and is not a list of instructions,
     | so six seconds is long enough to notice and short enough that it is not
     | left over the page. Info tends to be longer than a confirmation, so it
     | gets longer.
     */
    $siteAutoClose = ['success' => 6000, 'info' => 9000];

    $hasValidationErrors = $errors->any();
@endphp

@if ($siteNotices || $hasValidationErrors)
    <div class="site-toasts" role="region" aria-label="Notifications">
        @foreach ($siteNotices as $siteNoticeLevel => $siteNoticeText)
            {{--
                data-site-toast-autoclose is what makes the message leave on its
                own. It is read by custom.js, which is the only place that
                dismisses anything, so removing it here takes the behaviour with
                it - and the number is in milliseconds.

                Absent on warning, error and the validation summary, which is
                the whole point: no attribute, no timer.
            --}}
            <div class="alert alert-{{ $siteNoticeLevel }} site-toast" role="alert"
                @if (isset($siteAutoClose[$siteNoticeLevel]))
                    data-site-toast-autoclose="{{ $siteAutoClose[$siteNoticeLevel] }}"
                @endif>
                <button type="button" class="site-toast__close" data-site-toast-dismiss
                    aria-label="Dismiss this message">
                    <i class="feather-x" aria-hidden="true"></i>
                </button>
                <div class="site-toast__body">{{ $siteNoticeText }}</div>
            </div>
        @endforeach

        {{-- No autoclose here, deliberately. See the note above. --}}
        @if ($hasValidationErrors)
            <div class="alert alert-danger site-toast" role="alert">
                <button type="button" class="site-toast__close" data-site-toast-dismiss
                    aria-label="Dismiss this message">
                    <i class="feather-x" aria-hidden="true"></i>
                </button>
                <div class="site-toast__body">
                    <strong>Please check the form:</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
@endif
