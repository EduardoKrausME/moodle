// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

/**
 * Presentation-only progress for the native quiz manual grading report.
 *
 * Never writes grades, changes form inputs or replaces core_form/changechecker.
 * The actual question processing and successful save belong exclusively to PHP.
 *
 * @module mod_quiz/manual_grading_navigation
 */

const CARD_SELECTOR = '[data-region="manual-grading-card"]';
const STATUS_SELECTOR = '[data-region="manual-grading-card-status"]';
const PROGRESS_SELECTOR = '[data-region="manual-grading-progress"]';
const CONTROL_SELECTOR = 'input, textarea, select';

/**
 * Capture native field state without modifying any form values.
 *
 * @param {HTMLElement} element Native form element.
 * @returns {string} Comparable snapshot.
 */
export const controlSnapshot = element => {
    if (element.type === 'checkbox' || element.type === 'radio') {
        return String(element.checked);
    }
    if (element.type === 'select-multiple') {
        return JSON.stringify(Array.from(element.options).map(option => Boolean(option.selected)));
    }
    if (element.type === 'file') {
        return String(element.files?.length ?? 0);
    }
    return String(element.value ?? '');
};

/**
 * Whether a response has field changes relative to the current rendered form.
 *
 * @param {HTMLElement} card Native response section.
 * @param {WeakMap} initial Native form control baselines.
 * @returns {boolean}
 */
export const responseChanged = (card, initial) => Array.from(card.querySelectorAll(CONTROL_SELECTOR))
    .some(element => initial.has(element) && initial.get(element) !== controlSnapshot(element));

/**
 * Update the explanatory labels only. This does not mark grades as saved.
 *
 * @param {HTMLFormElement} form Native form.
 * @param {HTMLElement[]} cards Visible response cards.
 * @param {WeakMap} initial State captured when the form was first rendered.
 * @returns {number} Number of responses with detected changes.
 */
export const updateProgress = (form, cards, initial) => {
    let edited = 0;
    cards.forEach(card => {
        const dirty = responseChanged(card, initial);
        if (dirty) {
            edited++;
        }
        const status = card.querySelector(STATUS_SELECTOR);
        if (status) {
            status.textContent = dirty ? status.dataset.dirtyMessage : status.dataset.cleanMessage;
            status.dataset.dirty = String(dirty);
        }
    });
    const progress = form.querySelector(PROGRESS_SELECTOR);
    if (progress) {
        progress.textContent = progress.dataset.progressTemplate
            .replace('%EDITED%', String(edited))
            .replace('%TOTAL%', String(cards.length));
    }
    return edited;
};

/**
 * Enhance only the native grading form, leaving data submission to the server.
 *
 * @returns {void}
 */
export const init = () => {
    const form = document.getElementById('manualgradingform');
    if (!form || form.dataset.manualGradingUxInitialised === '1') {
        return;
    }
    form.dataset.manualGradingUxInitialised = '1';

    const cards = Array.from(form.querySelectorAll(CARD_SELECTOR));
    const initial = new WeakMap();
    cards.forEach(card => {
        card.querySelectorAll(CONTROL_SELECTOR).forEach(element => {
            initial.set(element, controlSnapshot(element));
        });
    });

    const update = () => updateProgress(form, cards, initial);
    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();

    // A second submit event must not create a second grading transaction.
    form.addEventListener('submit', event => {
        if (form.dataset.manualGradingSubmitting === '1') {
            event.preventDefault();
            return;
        }
        form.dataset.manualGradingSubmitting = '1';
        const submit = form.querySelector('input[type="submit"]');
        if (submit && form.dataset.savingLabel) {
            submit.value = form.dataset.savingLabel;
        }
    });
};
