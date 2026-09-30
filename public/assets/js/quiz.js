/**
 * The quiz player.
 *
 * A paper is one form holding every question. Working through it is done here,
 * in the browser, so moving from a question to the next costs no request: the
 * answers are kept in local storage as the learner picks them and are posted
 * once, as one map, when they finish.
 *
 * Nothing here decides anything. The state is only a record of what the
 * learner has clicked; the paper is marked on the server from the content
 * file, and the correct answers are never sent here.
 */
(function () {
    'use strict';

    var form = document.getElementById('paper-form');

    if (!form) {
        return;
    }

    var panels = Array.prototype.slice.call(form.querySelectorAll('.lz-question'));
    var jumpButtons = Array.prototype.slice.call(document.querySelectorAll('[data-goto]'));
    var progressBar = document.querySelector('[data-progress-bar]');
    var progressTrack = progressBar ? progressBar.parentNode : null;
    var progressText = document.querySelector('[data-progress-text]');
    var back = form.querySelector('[data-move="back"]');
    var next = form.querySelector('[data-move="next"]');

    var total = parseInt(form.dataset.total, 10) || panels.length;
    var current = 1;
    var answers = {};
    var key = 'cce.quiz.attempt.' + form.dataset.attempt;

    /* --- Storage -------------------------------------------------------
     * Wrapped because a browser in private mode, or with storage turned off,
     * throws on write rather than failing quietly. A paper must still be
     * sit-able in that case; it just cannot be resumed.
     */

    function read() {
        try {
            var raw = window.localStorage.getItem(key);

            return raw ? JSON.parse(raw) : null;
        } catch (error) {
            return null;
        }
    }

    function write(state) {
        try {
            window.localStorage.setItem(key, JSON.stringify(state));
        } catch (error) {
            /* Nothing to do: the answers are still in the form. */
        }
    }

    /* --- Reading the form --------------------------------------------- */

    function syncFromForm() {
        answers = {};

        panels.forEach(function (panel) {
            var picked = panel.querySelector('input[type="radio"]:checked');

            if (picked) {
                answers[panel.dataset.position] = picked.value;
            }
        });
    }

    function answeredCount() {
        return Object.keys(answers).length;
    }

    /* --- Drawing ------------------------------------------------------- */

    function show(position) {
        current = Math.min(Math.max(position, 1), total);

        panels.forEach(function (panel) {
            panel.classList.toggle('is-current', panel.dataset.position === String(current));
        });

        jumpButtons.forEach(function (button) {
            var isHere = button.dataset.goto === String(current);

            button.classList.toggle('here', isHere);
            button.classList.toggle('done', answers[button.dataset.goto] !== undefined);

            if (isHere) {
                button.setAttribute('aria-current', 'true');
            } else {
                button.removeAttribute('aria-current');
            }

            button.setAttribute(
                'aria-label',
                'Question ' + button.dataset.goto + (answers[button.dataset.goto] ? ', answered' : ', not answered')
            );
        });

        if (back) {
            back.disabled = current === 1;
        }

        if (next) {
            next.textContent = current === total ? 'Finish' : 'Next →';
        }

        drawProgress();
    }

    function drawProgress() {
        var answered = answeredCount();
        var percent = total > 0 ? Math.round((answered / total) * 100) : 0;

        if (progressBar) {
            progressBar.style.width = percent + '%';
        }

        if (progressTrack) {
            progressTrack.setAttribute('aria-valuenow', String(percent));
        }

        if (progressText) {
            progressText.textContent = answered === 0
                ? 'Nothing answered yet.'
                : answered + ' of ' + total + ' answered.';
        }
    }

    function save() {
        write({ position: current, answers: answers });
    }

    /* --- Wiring -------------------------------------------------------- */

    // The whole paper is one-at-a-time only once we are steering it, so a
    // browser with scripting off is left showing every question and posting
    // them together. The class goes on the document, because the jump nav and
    // the progress bar live outside the form.
    document.body.classList.add('is-playing');

    var saved = read();

    if (saved && saved.answers) {
        // A paper that was shortened since the last visit leaves entries for
        // questions that no longer exist. They are dropped rather than posted.
        panels.forEach(function (panel) {
            var letter = saved.answers[panel.dataset.position];

            if (!letter) {
                return;
            }

            var input = panel.querySelector('input[type="radio"][value="' + letter + '"]');

            if (input) {
                input.checked = true;
            }
        });
    }

    syncFromForm();

    var start = saved && saved.position ? saved.position : parseInt(form.dataset.start, 10);

    show(start || 1);

    form.addEventListener('change', function (event) {
        if (event.target.type !== 'radio') {
            return;
        }

        syncFromForm();
        drawProgress();
        show(current);
        save();
    });

    // The controls that steer a paper are not all in one place: Next and Back
    // live inside the form, the jump nav sits beside it. So the click is
    // watched on the document and the buttons are recognised by what they
    // carry, rather than by where they are.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('button[data-move], button[data-goto]');

        if (!button) {
            return;
        }

        syncFromForm();
        save();

        if (button.dataset.goto) {
            show(parseInt(button.dataset.goto, 10));

            return;
        }

        if (button.dataset.move === 'back') {
            show(current - 1);

            return;
        }

        if (button.dataset.move === 'next') {
            if (current === total) {
                form.submit();
            } else {
                show(current + 1);
            }
        }
    });

    // Submitting sends every answer at once, so whatever is on the form is
    // the whole sitting. This is belt and braces: the radio buttons are
    // already named, so the browser posts them unaided.
    form.addEventListener('submit', function (event) {
        var submitter = event.submitter;

        if (submitter && submitter.tagName === 'BUTTON' && !submitter.hasAttribute('data-move')) {
            var unanswered = total - answeredCount();

            if (unanswered > 0) {
                var message = unanswered === 1
                    ? '1 question is unanswered. Anything you leave blank counts as wrong. Finish anyway?'
                    : unanswered + ' questions are unanswered. Anything you leave blank counts as wrong. Finish anyway?';

                if (!window.confirm(message)) {
                    event.preventDefault();
                    return;
                }
            }
        }

        syncFromForm();
        save();
    });
})();
