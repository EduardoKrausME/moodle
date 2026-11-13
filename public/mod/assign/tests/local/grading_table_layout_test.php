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
 * The column priority is presentation-only and must not change data contracts.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(grading_table_layout::class)]
final class grading_table_layout_test extends \basic_testcase {
    /**
     * @return array{0: array, 1: array}
     */
    private function columns(): array {
        $columns = [
            'select', 'fullname', 'email', 'status', 'allowsubmissionsfromdate',
            'duedate', 'cutoffdate', 'team', 'marker1', 'grade', 'timesubmitted',
            'plugin0', 'plugin1', 'timemarked', 'finalgrade', 'outcomes',
        ];
        return [$columns, array_map(static fn($column) => strtoupper($column), $columns)];
    }

    public function test_primary_columns_are_first_in_html(): void {
        [$columns, $headers] = $this->columns();
        [$actual, $labels] = grading_table_layout::prioritise($columns, $headers, false);
        $this->assertSame(
            ['select', 'fullname', 'status', 'team', 'duedate', 'timesubmitted', 'grade'],
            array_slice($actual, 0, 7)
        );
        $this->assertSame('FULLNAME', $labels[1]);
        $this->assertSame('GRADE', $labels[6]);
    }

    public function test_every_plugin_field_and_header_is_preserved(): void {
        [$columns, $headers] = $this->columns();
        [$result, $labels] = grading_table_layout::prioritise($columns, $headers, false);
        $this->assertEqualsCanonicalizing($columns, $result);
        $this->assertCount(count($columns), $labels);
        $this->assertSame('PLUGIN0', $labels[array_search('plugin0', $result, true)]);
        $this->assertSame('PLUGIN1', $labels[array_search('plugin1', $result, true)]);
        $this->assertSame(
            ['email', 'allowsubmissionsfromdate', 'cutoffdate', 'marker1', 'plugin0', 'plugin1',
                'timemarked', 'finalgrade', 'outcomes'],
            array_slice($result, 7)
        );
    }

    public function test_csv_download_columns_remain_completely_unchanged(): void {
        [$columns, $headers] = $this->columns();
        $this->assertSame([$columns, $headers],
            grading_table_layout::prioritise($columns, $headers, true));
    }

    public function test_anonymous_record_id_remains_sole_identity_column(): void {
        $columns = ['select', 'recordid', 'status', 'grade', 'plugin0'];
        [$result] = grading_table_layout::prioritise($columns, $columns, false);
        $this->assertSame($columns, $result);
        $this->assertNotContains('fullname', $result);
        $this->assertNotContains('email', $result);
        $this->assertSame('assign-grading-identity',
            grading_table_layout::roles($result)['recordid']);
    }

    public function test_anonymous_column_is_prioritised_even_when_plugins_precede_it(): void {
        $columns = ['plugin0', 'recordid', 'status', 'grade'];
        [$result] = grading_table_layout::prioritise($columns, $columns, false);
        $this->assertSame(['recordid', 'status', 'grade', 'plugin0'], $result);
    }

    public function test_table_without_selection_column_keeps_its_identity(): void {
        $columns = ['plugin0', 'fullname', 'status', 'grade'];
        [$result] = grading_table_layout::prioritise($columns, $columns, false);
        $this->assertSame(['fullname', 'status', 'grade', 'plugin0'], $result);
        $this->assertNotContains('select', $result);
    }

    public function test_unavailable_optional_columns_are_not_invented(): void {
        $columns = ['select', 'fullname', 'status', 'plugin0'];
        [$result] = grading_table_layout::prioritise($columns, $columns, false);
        $this->assertSame(['select', 'fullname', 'status', 'plugin0'], $result);
        $this->assertNotContains('duedate', $result);
    }

    public function test_mismatched_header_input_is_not_partially_reordered(): void {
        $columns = ['select', 'fullname', 'status'];
        $headers = ['Select', 'Name'];
        $this->assertSame([$columns, $headers],
            grading_table_layout::prioritise($columns, $headers, false));
    }

    public function test_empty_table_schema_is_supported(): void {
        $this->assertSame([[], []], grading_table_layout::prioritise([], [], false));
        $this->assertSame([], grading_table_layout::roles([]));
    }

    public function test_column_roles_are_independent_of_column_offsets(): void {
        $roles = grading_table_layout::roles(['plugin4', 'status', 'fullname', 'select', 'grade']);
        $this->assertSame('assign-grading-extra', $roles['plugin4']);
        $this->assertSame('status assign-grading-status', $roles['status']);
        $this->assertSame('assign-grading-identity', $roles['fullname']);
        $this->assertSame('assign-grading-select', $roles['select']);
        $this->assertSame('grade assign-grading-grade', $roles['grade']);
    }

    public function test_markers_outcomes_and_feedback_keep_relative_order(): void {
        $columns = ['feedbackplugin0', 'marker1', 'feedbackplugin1', 'outcomes', 'status', 'fullname'];
        [$result] = grading_table_layout::prioritise($columns, $columns, false);
        $this->assertSame(
            ['fullname', 'status', 'feedbackplugin0', 'marker1', 'feedbackplugin1', 'outcomes'],
            $result
        );
    }
}
