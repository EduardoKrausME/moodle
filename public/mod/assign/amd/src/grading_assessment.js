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
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Progressive presentation for native rubrics and marking guides in mod_assign.
 *
 * Never creates grading controls, calculates marks, serialises a form or
 * changes the core gradingform_* evaluation/validation contract.
 *
 * @module mod_assign/grading_assessment
 */
define([], function() {
    'use strict';

    const rubricRows = '.gradingform_rubric.evaluate.editable tr.criterion';
    const guideRows = '.gradingform_guide.evaluate.editable tr.criterion';
    const rubricChoice = 'input[type="radio"][name*="[levelid]"]';
    const rubricSelected = 'input[type="radio"][name*="[levelid]"]:checked';
    const guideScore = 'input[name*="[criteria]"][name$="[score]"]';

    /**
     * Count only editable native criteria. An empty selection is NOT a grade
     * calculation or a substitute for Moodle's validation.
     *
     * @param {HTMLElement} form Native grade form.
     * @param {String} mode Assessment mode produced by PHP.
     * @returns {Object} Count and per-row state, without any score values.
     */
    const scan = (form, mode) => {
        if (!form || !['rubric', 'guide'].includes(mode)) {
            return {total: 0, completed: 0, rows: []};
        }
        const selector = mode === 'rubric' ? rubricRows : guideRows;
        const criteria = Array.from(form.querySelectorAll(selector));
        const rows = [];
        for (const row of criteria) {
            if (mode === 'rubric') {
                if (!row.querySelector(rubricChoice)) {
                    continue;
                }
                rows.push({element: row, completed: Boolean(row.querySelector(rubricSelected))});
            } else {
                const field = row.querySelector(guideScore);
                if (!field || field.disabled) {
                    continue;
                }
                // A non-empty score is reported as entered, not validated.
                rows.push({element: row, completed: String(field.value).trim() !== ''});
            }
        }
        return {total: rows.length, completed: rows.filter(row => row.completed).length, rows};
    };

    /**
     * Refresh text and styles from the controls actually present in the form.
     *
     * @param {HTMLElement} form Native grade form.
     */
    const update = form => {
        if (!form) {
            return;
        }
        const overview = form.querySelector('[data-region="assign-assessment-summary"]');
        if (!overview) {
            return;
        }
        const mode = overview.dataset.assessmentMode;
        const progress = overview.querySelector('[data-region="assign-assessment-criteria-progress"]');
        if (!progress) {
            return;
        }

        const counts = scan(form, mode);
        progress.hidden = counts.total === 0;
        if (!counts.total) {
            progress.textContent = '';
            return;
        }
        const template = overview.dataset.progressTemplate || '';
        progress.textContent = template.replace('%completed%', String(counts.completed))
            .replace('%total%', String(counts.total));
        for (const criterion of counts.rows) {
            criterion.element.classList.toggle('assign-assessment-criterion-complete', criterion.completed);
            criterion.element.classList.toggle('assign-assessment-criterion-pending', !criterion.completed);
        }
    };

    /**
     * Bind once to this particular native form instance. The grading panel
     * recreates the form for each participant or attempt via the core fragment.
     *
     * @param {HTMLElement} form Existing MoodleQuickForm, never a copied form.
     */
    const init = form => {
        if (!form || !form.querySelector('[data-region="assign-assessment-summary"]')) {
            return;
        }
        if (form.dataset.assignAssessmentInitialised === 'true') {
            update(form);
            return;
        }
        form.dataset.assignAssessmentInitialised = 'true';
        const changed = () => {
            // Rubric's own JS updates its checked state on the same click.
            // Read after its handler, without intercepting its events.
            window.requestAnimationFrame(() => update(form));
        };
        form.addEventListener('change', changed);
        form.addEventListener('input', changed);
        form.addEventListener('click', changed);
        update(form);
    };

    return {init, update, scan};
});
