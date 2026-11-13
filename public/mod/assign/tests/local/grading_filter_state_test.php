<?php
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

namespace mod_assign\local;

defined('MOODLE_INTERNAL') || die();

/**
 * URL state for teacher filters must never be confused with grading writes.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(grading_filter_state::class)]
final class grading_filter_state_test extends \basic_testcase {
    private function filters(): array {
        return [
            'group' => 7,
            'userid' => null,
            'search' => 'Silva',
            'status' => 'submitted',
            'tifirst' => 'M',
            'tilast' => 'S',
            'workflowfilter' => 'inmarking',
            'markingallocationfilter' => '-1',
            'suspendedparticipantsfilter' => 1,
        ];
    }

    public function test_navigation_only_carries_whitelisted_fields(): void {
        $filters = $this->filters() + [
            'sesskey' => 'do-not-copy',
            'action' => 'deletesubmission',
            'download' => 'csv',
            'page' => 99,
            'plugin' => 'other',
        ];
        $params = grading_filter_state::parameters(42, $filters);
        $this->assertSame(42, $params['id']);
        $this->assertSame('grading', $params['action']);
        $this->assertSame(7, $params['group']);
        $this->assertSame('Silva', $params['search']);
        $this->assertSame('submitted', $params['status']);
        $this->assertSame('inmarking', $params['workflowfilter']);
        $this->assertSame('-1', $params['markingallocationfilter']);
        $this->assertSame(1, $params['suspendedparticipantsfilter']);
        $this->assertArrayNotHasKey('sesskey', $params);
        $this->assertArrayNotHasKey('download', $params);
        $this->assertArrayNotHasKey('page', $params);
        $this->assertArrayNotHasKey('plugin', $params);
    }

    public function test_status_removal_keeps_other_criteria(): void {
        $params = grading_filter_state::without(42, $this->filters(), 'status');
        $this->assertSame('', $params['status']);
        $this->assertSame(7, $params['group']);
        $this->assertSame('Silva', $params['search']);
        $this->assertSame('inmarking', $params['workflowfilter']);
        $this->assertSame('-1', $params['markingallocationfilter']);
    }

    public function test_free_text_removal_clears_userid_and_search(): void {
        $filters = $this->filters();
        $filters['userid'] = 21;
        $params = grading_filter_state::without(42, $filters, 'user');
        $this->assertArrayNotHasKey('userid', $params);
        $this->assertSame('', $params['search']);
        $this->assertSame('submitted', $params['status']);
        $this->assertSame(7, $params['group']);
    }

    public function test_selected_user_id_takes_precedence_over_text(): void {
        $filters = $this->filters();
        $filters['userid'] = 21;
        $params = grading_filter_state::parameters(42, $filters);
        $this->assertSame(21, $params['userid']);
        $this->assertArrayNotHasKey('search', $params);
        $this->assertSame('submitted', $params['status']);
    }

    public function test_group_removal_preserves_selection_and_marker_filter(): void {
        $params = grading_filter_state::without(42, $this->filters(), 'group');
        $this->assertSame(0, $params['group']);
        $this->assertSame('Silva', $params['search']);
        $this->assertSame('-1', $params['markingallocationfilter']);
    }

    public function test_workflow_removal_does_not_clear_marker(): void {
        $params = grading_filter_state::without(42, $this->filters(), 'workflowfilter');
        $this->assertSame('', $params['workflowfilter']);
        $this->assertSame('-1', $params['markingallocationfilter']);
    }

    public function test_marker_removal_does_not_clear_workflow(): void {
        $params = grading_filter_state::without(42, $this->filters(), 'markingallocationfilter');
        $this->assertSame('', $params['markingallocationfilter']);
        $this->assertSame('inmarking', $params['workflowfilter']);
    }

    public function test_suspended_filter_removal_resets_only_its_flag(): void {
        $params = grading_filter_state::without(42, $this->filters(), 'suspendedparticipantsfilter');
        $this->assertSame(0, $params['suspendedparticipantsfilter']);
        $this->assertSame('submitted', $params['status']);
        $this->assertSame('Silva', $params['search']);
    }

    public function test_initials_are_cleared_separately(): void {
        $params = grading_filter_state::without(42, $this->filters(), 'tifirst');
        $this->assertSame('', $params['tifirst']);
        $this->assertSame('S', $params['tilast']);
        $params = grading_filter_state::without(42, $this->filters(), 'tilast');
        $this->assertSame('M', $params['tifirst']);
        $this->assertSame('', $params['tilast']);
    }

    public function test_full_reset_does_not_reset_display_preferences(): void {
        $params = grading_filter_state::reset(42);
        $this->assertSame('', $params['status']);
        $this->assertSame('', $params['search']);
        $this->assertSame(0, $params['group']);
        $this->assertSame('', $params['workflowfilter']);
        $this->assertSame('', $params['markingallocationfilter']);
        $this->assertSame(0, $params['suspendedparticipantsfilter']);
        $this->assertSame('', $params['tifirst']);
        $this->assertSame('', $params['tilast']);
        $this->assertArrayNotHasKey('userid', $params);
        $this->assertArrayNotHasKey('quickgrading', $params);
        $this->assertArrayNotHasKey('perpage', $params);
    }

    public function test_no_empty_values_or_unselected_groups_are_added(): void {
        $this->assertSame(
            ['id' => 42, 'action' => 'grading'],
            grading_filter_state::parameters(42, ['group' => 0, 'userid' => 0, 'status' => '', 'search' => ''])
        );
    }

    public function test_chip_list_matches_only_authorised_labels(): void {
        $filters = $this->filters();
        $labels = [
            'user' => 'Search: Silva',
            'group' => 'Group: A',
            'status' => 'Status: Submitted',
            'workflowfilter' => 'Marking: In marking',
            'markingallocationfilter' => 'Marker: No marker',
            'suspendedparticipantsfilter' => 'Including suspended participants',
            'notapproved' => 'Hidden user data',
        ];
        $chips = grading_filter_state::chips(42, $filters, $labels);
        $keys = array_column($chips, 'key');
        $this->assertSame(
            ['user', 'group', 'status', 'workflowfilter', 'markingallocationfilter', 'suspendedparticipantsfilter'],
            $keys
        );
        $this->assertSame('Status: Submitted', $chips[2]['label']);
        $this->assertSame('', $chips[2]['params']['status']);
        $this->assertNotContains('notapproved', $keys);
    }

    public function test_empty_or_missing_labels_do_not_generate_chips(): void {
        $this->assertSame([], grading_filter_state::chips(42, $this->filters(), []));
        $this->assertSame([], grading_filter_state::chips(42, $this->filters(), ['status' => '']));
    }

    public function test_unknown_chip_name_is_rejected(): void {
        $this->expectException(\InvalidArgumentException::class);
        grading_filter_state::without(42, $this->filters(), 'sesskey');
    }

    public function test_optional_status_and_workflow_values_are_not_invented(): void {
        $params = grading_filter_state::parameters(42, ['group' => null, 'userid' => null, 'status' => null]);
        $this->assertSame(['id' => 42, 'action' => 'grading'], $params);
    }

    public function test_search_string_is_not_removed_when_just_workflow_is_cleared(): void {
        $params = grading_filter_state::without(42, ['search' => 'Student', 'workflowfilter' => 'inmarking'], 'workflowfilter');
        $this->assertSame('Student', $params['search']);
        $this->assertSame('', $params['workflowfilter']);
    }
}
