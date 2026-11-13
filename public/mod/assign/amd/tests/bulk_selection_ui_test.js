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
 * Regression tests for a page-scoped selection around native core/bulkactions.
 *
 * @module mod_assign/bulk_selection_ui_test
 */
define(['mod_assign/bulkactions/grading/selection_ui'], function(SelectionUI) {
    'use strict';

    let fixture;

    describe('mod_assign/bulkactions/grading/selection_ui', function() {
        beforeEach(function() {
            fixture = document.createElement('div');
            fixture.innerHTML = [
                '<table id="submissions"><thead><tr><th>',
                '<input type="checkbox" name="selectall" aria-label="Select all">',
                '</th></tr></thead><tbody>',
                '<tr><td><input type="checkbox" name="selectedusers" value="12"></td></tr>',
                '<tr><td><input type="checkbox" name="selectedusers" value="34"></td></tr>',
                '<tr><td><input type="checkbox" name="selectedusers" value="56"></td></tr>',
                '</tbody></table>',
                '<input type="checkbox" name="selectedusers" value="999" checked>',
            ].join('');
            document.body.append(fixture);
        });

        afterEach(function() {
            fixture.remove();
        });

        it('starts with no selected participants', function() {
            chai.assert.deepEqual(SelectionUI.getSelectedIds(), []);
        });

        it('includes only currently selected rows inside #submissions', function() {
            const checkboxes = fixture.querySelectorAll('#submissions [name="selectedusers"]');
            checkboxes[0].checked = true;
            checkboxes[2].checked = true;
            chai.assert.deepEqual(SelectionUI.getSelectedIds(), ['12', '56']);
        });

        it('ignores checkboxes from other forms and outside the grading page', function() {
            chai.assert.deepEqual(SelectionUI.getSelectedIds(), []);
        });

        it('deduplicates participant IDs without changing their order', function() {
            const checkboxes = fixture.querySelectorAll('#submissions [name="selectedusers"]');
            checkboxes[0].checked = true;
            checkboxes[1].value = '12';
            checkboxes[1].checked = true;
            checkboxes[2].checked = true;
            chai.assert.deepEqual(SelectionUI.getSelectedIds(), ['12', '56']);
        });

        it('marks partial selection as mixed for screen readers', function() {
            const selected = fixture.querySelector('#submissions [name="selectedusers"]');
            const header = fixture.querySelector('#submissions [name="selectall"]');
            selected.checked = true;
            SelectionUI.syncSelection();
            chai.assert.isTrue(header.indeterminate);
            chai.assert.equal(header.getAttribute('aria-checked'), 'mixed');
            chai.assert.isFalse(header.checked);
        });

        it('checks the header when every row is selected', function() {
            const header = fixture.querySelector('#submissions [name="selectall"]');
            fixture.querySelectorAll('#submissions [name="selectedusers"]').forEach(box => {
                box.checked = true;
            });
            SelectionUI.syncSelection();
            chai.assert.isTrue(header.checked);
            chai.assert.isFalse(header.indeterminate);
            chai.assert.equal(header.getAttribute('aria-checked'), 'true');
        });

        it('clears the header when no row is selected', function() {
            const header = fixture.querySelector('#submissions [name="selectall"]');
            header.checked = true;
            SelectionUI.syncSelection();
            chai.assert.isFalse(header.checked);
            chai.assert.isFalse(header.indeterminate);
            chai.assert.equal(header.getAttribute('aria-checked'), 'false');
        });

        it('preserves native selection classes for each table row', function() {
            const boxes = fixture.querySelectorAll('#submissions [name="selectedusers"]');
            boxes[1].checked = true;
            SelectionUI.syncSelection();
            chai.assert.isTrue(boxes[1].closest('tr').classList.contains('selectedrow'));
            chai.assert.isTrue(boxes[0].closest('tr').classList.contains('unselectedrow'));
        });
    });
});
