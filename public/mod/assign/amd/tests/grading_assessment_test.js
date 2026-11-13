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
 * Tests for the presentation-only native grading assessment controller.
 *
 * @module mod_assign/grading_assessment_test
 */
define(['mod_assign/grading_assessment'], function(GradingAssessment) {
    'use strict';

    const assert = (condition, message) => {
        if (!condition) {
            throw new Error(message);
        }
    };

    const fakeRow = (type, selected, disabled = false) => ({
        classList: {toggle: () => {}},
        querySelector: selector => {
            if (type === 'rubric') {
                if (selector.includes(':checked')) {
                    return selected ? {} : null;
                }
                return selector.includes('radio') ? {} : null;
            }
            return selector.includes('[score]') ? {value: selected, disabled} : null;
        },
    });

    const fakeForm = (rubric, guide) => ({
        querySelectorAll: selector => selector.includes('rubric') ? rubric : guide,
    });

    describe('mod_assign/grading_assessment', function() {
        it('does not invent progress for simple grading or marker forms', function() {
            const form = fakeForm([fakeRow('rubric', true)], []);
            assert(GradingAssessment.scan(form, 'simple').total === 0, 'simple grading is not rubric');
            assert(GradingAssessment.scan(form, 'marker').total === 0, 'marker progress is native');
        });

        it('counts actual selected rubric radios, not their numeric score', function() {
            const form = fakeForm([fakeRow('rubric', true), fakeRow('rubric', false)], []);
            const progress = GradingAssessment.scan(form, 'rubric');
            assert(progress.total === 2 && progress.completed === 1, 'one native level selected');
        });

        it('does not count frozen rubric controls as editable criteria', function() {
            const form = fakeForm([], []);
            assert(GradingAssessment.scan(form, 'rubric').total === 0, 'readonly form has no editable radios');
        });

        it('counts entered marking-guide scores without calculating a grade', function() {
            const form = fakeForm([], [fakeRow('guide', '75'), fakeRow('guide', '')]);
            const progress = GradingAssessment.scan(form, 'guide');
            assert(progress.total === 2 && progress.completed === 1, 'one guide score entered');
            assert(!('score' in progress.rows[0]), 'numeric values must not be exposed in progress');
        });

        it('does not treat whitespace as a guide score', function() {
            const form = fakeForm([], [fakeRow('guide', '  ')]);
            assert(GradingAssessment.scan(form, 'guide').completed === 0, 'blank input remains pending');
        });

        it('does not count disabled guide inputs', function() {
            const form = fakeForm([], [fakeRow('guide', '83', true)]);
            assert(GradingAssessment.scan(form, 'guide').total === 0, 'disabled input is not editable');
        });

        it('handles a missing native form without throwing', function() {
            const value = GradingAssessment.scan(null, 'rubric');
            assert(value.total === 0 && value.completed === 0, 'no fake progress');
        });

        it('uses the server-translated progress message and real input states', function() {
            const row = fakeRow('rubric', true);
            const classes = {};
            row.classList.toggle = (name, enabled) => {classes[name] = enabled;};
            const text = {hidden: true, textContent: ''};
            const overview = {
                dataset: {assessmentMode: 'rubric', progressTemplate: '%completed% of %total% criteria selected'},
                querySelector: () => text,
            };
            const form = {
                querySelector: () => overview,
                querySelectorAll: () => [row],
            };
            GradingAssessment.update(form);
            assert(text.textContent === '1 of 1 criteria selected', 'server locale template used');
            assert(!text.hidden, 'progress is visible');
            assert(classes['assign-assessment-criterion-complete'], 'row is marked as entered');
        });

        it('initialises once per existing form and does not duplicate inputs or listeners', function() {
            const handlers = [];
            const overview = {dataset: {assessmentMode: 'simple'}, querySelector: () => null};
            const form = {
                dataset: {},
                querySelector: () => overview,
                addEventListener: (name, fn) => handlers.push([name, fn]),
            };
            GradingAssessment.init(form);
            GradingAssessment.init(form);
            assert(handlers.length === 3, 'only one listener for each event');
            assert(form.dataset.assignAssessmentInitialised === 'true', 'per-form guard installed');
        });
    });
});
