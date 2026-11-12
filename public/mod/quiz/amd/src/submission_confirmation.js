// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A javascript module to handle submission confirmation for quiz.
 *
 * @module    mod_quiz/submission_confirmation
 * @copyright 2022 Huong Nguyen <huongnv13@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     4.1
 */

import {saveCancelPromise} from 'core/notification';
import Prefetch from 'core/prefetch';
import Templates from 'core/templates';
import {getString} from 'core/str';

const SELECTOR = {
    attemptSubmitButton: '.path-mod-quiz .btn-finishattempt button',
    attemptSubmitForm: 'form#frm-finishattempt',
};

const TEMPLATES = {
    submissionConfirmation: 'mod_quiz/submission_confirmation',
};

/**
 * Prepare a compact, read-only confirmation context. Accept a legacy numeric
 * unanswered count for themes or integrations that call init directly.
 *
 * @param {Object|number} progress Server-side summary or historical count.
 * @returns {Object} Mustache context.
 */
export const buildContext = (progress) => {
    if (Number.isSafeInteger(progress) && progress >= 0) {
        return {
            hasprogress: false,
            hasunanswered: progress > 0,
            totalunanswered: progress,
            haspending: false,
            issequential: false,
        };
    }

    if (!progress || typeof progress !== 'object' ||
            !['total', 'answered', 'pending', 'flagged'].every(key =>
                Number.isSafeInteger(progress[key]) && progress[key] >= 0) ||
            progress.answered + progress.pending !== progress.total ||
            progress.flagged > progress.total) {
        // No trusted numbers: show the original irreversible-action message only.
        return {hasprogress: false, hasunanswered: false, haspending: false, issequential: false};
    }

    const isfree = progress.navmethod === 'free';
    return {
        hasprogress: true,
        total: progress.total,
        answered: progress.answered,
        pending: progress.pending,
        flagged: progress.flagged,
        haspending: progress.pending > 0,
        // Preserve the native "Questions without a response" warning in free navigation.
        // Sequential attempts must not receive a misleading suggestion to go back.
        hasunanswered: isfree && progress.pending > 0,
        totalunanswered: progress.pending,
        issequential: progress.navmethod === 'sequential',
    };
};

/**
 * Register the confirmation once, before any attempt to submit the native form.
 *
 * Both the button and a keyboard-triggered form submit pass through the same
 * state machine. form.submit() deliberately bypasses submit events after the
 * learner confirms. Timer-driven timeup uses that same native direct submit and
 * must never be blocked by this UX enhancement.
 *
 * @param {Object|number} progress Server-confirmed progress, or legacy count.
 */
const registerEventListeners = (progress) => {
    const submitAction = document.querySelector(SELECTOR.attemptSubmitButton);
    const form = document.querySelector(SELECTOR.attemptSubmitForm);
    if (!submitAction || !form || form.dataset.quizSubmissionConfirmationReady === '1') {
        return;
    }
    form.dataset.quizSubmissionConfirmationReady = '1';
    const context = buildContext(progress);
    let phase = 'idle';

    const confirmSubmission = async() => {
        if (phase !== 'idle') {
            return;
        }

        // Acquire the UI lock synchronously, before template rendering/modal I/O.
        phase = 'confirming';
        try {
            await saveCancelPromise(
                getString('submission_confirmation', 'quiz'),
                Templates.render(TEMPLATES.submissionConfirmation, context),
                getString('submitallandfinish', 'quiz')
            );

            // One confirmed user action -> one native submission.
            phase = 'submitting';
            submitAction.disabled = true;
            form.setAttribute('aria-busy', 'true');
            form.submit();
        } catch {
            // Cancel or a failed modal leaves the attempt editable, never submitted.
            phase = 'idle';
            submitAction.disabled = false;
            form.removeAttribute('aria-busy');
            submitAction.focus();
        }
    };

    submitAction.addEventListener('click', (e) => {
        e.preventDefault();
        void confirmSubmission();
    });

    form.addEventListener('submit', (e) => {
        // Enter/requestSubmit must not bypass confirmation; direct form.submit()
        // from the timer or this confirmed path does not fire this event.
        e.preventDefault();
        void confirmSubmission();
    });
};

/**
 * Initialise the native final-submit confirmation.
 *
 * @param {Object|number} progress Server-confirmed question counts, or legacy number.
 */
export const init = (progress) => {
    Prefetch.prefetchStrings('core', ['submit']);
    Prefetch.prefetchStrings('core_admin', ['confirmation']);
    Prefetch.prefetchStrings('quiz', ['submitallandfinish', 'submission_confirmation']);
    Prefetch.prefetchTemplate(TEMPLATES.submissionConfirmation);
    registerEventListeners(progress);
};
