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

    The close button is there, and nothing dismisses itself. A validation
    summary that vanished before it had been read is worse than no summary,
    and there is no safe amount of time to pick.
--}}

@php
    $siteNotices = array_filter([
        'success' => session('success'),
        'info' => session('info'),
        'warning' => session('warning'),
        'error' => session('error'),
    ]);

    $hasValidationErrors = $errors->any();
@endphp

@if ($siteNotices || $hasValidationErrors)
    <div class="site-toasts" role="region" aria-label="Notifications">
        @foreach ($siteNotices as $siteNoticeLevel => $siteNoticeText)
            <div class="alert alert-{{ $siteNoticeLevel }} site-toast" role="alert">
                <button type="button" class="site-toast__close" data-site-toast-dismiss
                    aria-label="Dismiss this message">
                    <i class="feather-x" aria-hidden="true"></i>
                </button>
                <div class="site-toast__body">{{ $siteNoticeText }}</div>
            </div>
        @endforeach

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
