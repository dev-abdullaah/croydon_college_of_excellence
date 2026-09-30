//-------------------Footer Year Start---------------------

const currentYear = new Date().getFullYear(); // Get the current year
document.getElementById('year').textContent = currentYear; // Insert the year into the span with id="year"

//-------------------Footer Year End---------------------




// Get the current date
const today = new Date();

// Define the review month (March)
const reviewMonth = 2; // March (0-based index)

// Determine last and next review years
const lastReviewYear = today.getMonth() < reviewMonth ? currentYear - 1 : currentYear;
const nextReviewYear = lastReviewYear + 1;

// Insert dates into the table
//
// Guarded because these two ids only exist on the pages that carry the review
// dates table. Reaching for them unconditionally threw a TypeError on every
// other page in the site, which stopped the rest of this file from running.
const lastReviewCell = document.getElementById("lastReview");
const nextReviewCell = document.getElementById("nextReview");

if (lastReviewCell) {
    lastReviewCell.textContent = `March ${lastReviewYear}`;
}

if (nextReviewCell) {
    nextReviewCell.textContent = `March ${nextReviewYear}`;
}





// Define the global discount value in one place
const discountValue = 40; // Change this value when needed

// Update all instances of discount badges
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".discountValue").forEach(span => {
        span.textContent = `\u00A0${discountValue}%`; // \u00A0 is a non-breaking space
    });
});







//-------------------Session message toasts---------------------

/**
 * Let a message be dismissed by hand.
 *
 * Nothing here dismisses a message on a timer. A validation summary that
 * vanishes before it has been read is worse than no summary at all, and
 * there is no interval that is long enough to be helpful and short enough to
 * be unobtrusive. The trade-off is that a message left alone sits in the
 * corner until the next page load, which is the intended behaviour.
 */
(function () {
    "use strict";

    /**
     * Publish the real header height as a CSS custom property.
     *
     * The message is positioned a fixed distance below the header, and the
     * header is not a fixed height: it is 134px on a desktop and 239px on a
     * phone, because the contact bar above the navigation stacks onto two
     * lines. The stylesheet carries rough per-breakpoint fallbacks for when
     * scripting is off, but measuring it is the only way to be right on every
     * width, and on the handful of pages that swap the header for a taller or
     * transparent variant.
     *
     * Setting it on the root element as an inline style is deliberate: an
     * inline style outranks the stylesheet, so this wins over the media query
     * defaults instead of fighting them.
     */
    function syncHeaderHeight() {
        var header = document.querySelector(".rbt-header");

        if (!header) {
            return;
        }

        var height = Math.round(header.getBoundingClientRect().height);

        if (height > 0) {
            document.documentElement.style.setProperty("--site-header-height", height + "px");
        }
    }

    syncHeaderHeight();
    window.addEventListener("load", syncHeaderHeight);
    window.addEventListener("resize", syncHeaderHeight);
    window.addEventListener("orientationchange", syncHeaderHeight);

    function dismiss(toast) {
        if (!toast || toast.classList.contains("is-leaving")) {
            return;
        }

        toast.classList.add("is-leaving");

        // The wrapper has to be found before the toast is detached. Once the
        // toast is on its own, `closest` walks a detached tree and finds
        // nothing, so asking afterwards would silently never remove it.
        var stack = toast.parentNode ? toast.closest(".site-toasts") : null;
        var removed = false;

        // Wait for the fade before removing it, but never wait forever: if the
        // animation is switched off for reduced motion there is no transition
        // to wait for, and the timeout below is what removes the node then.
        var remove = function () {
            // Both the animationend handler and the timeout can arrive, so
            // this has to be safe to run twice.
            if (removed) {
                return;
            }

            removed = true;
            toast.removeEventListener("animationend", remove);

            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }

            // Once the last message has gone, drop the wrapper too rather than
            // leaving an invisible fixed element sitting over the corner of
            // the page.
            if (stack && stack.parentNode && stack.querySelectorAll(".site-toast").length === 0) {
                stack.parentNode.removeChild(stack);
            }
        };

        toast.addEventListener("animationend", remove);
        window.setTimeout(remove, 400);
    }

    document.addEventListener("click", function (event) {
        var button = event.target.closest("[data-site-toast-dismiss]");

        if (button) {
            event.preventDefault();
            dismiss(button.closest(".site-toast"));
        }
    });

    // Escape closes the topmost message, which is the one being read.
    document.addEventListener("keydown", function (event) {
        if (event.key !== "Escape") {
            return;
        }

        var stack = document.querySelector(".site-toasts");

        if (stack) {
            var toasts = stack.querySelectorAll(".site-toast");
            dismiss(toasts[toasts.length - 1]);
        }
    });
})();
