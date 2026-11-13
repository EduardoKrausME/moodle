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
 * Pure presentation state must preserve the native assessment and marking rules.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(grading_assessment_view::class)]
final class grading_assessment_view_test extends \basic_testcase {
    private const COMPLETED = 'readyforreview';

    public function test_simple_grading_mode(): void {
        $this->assertSame('simple', grading_assessment_view::mode(null, false, false));
        $this->assertSame('simple', grading_assessment_view::mode('', false, false));
    }

    public function test_advanced_modes_are_not_replaced_by_simple_grading(): void {
        $this->assertSame('rubric', grading_assessment_view::mode('rubric', false, false));
        $this->assertSame('guide', grading_assessment_view::mode('guide', false, false));
        $this->assertSame('advanced', grading_assessment_view::mode('thirdparty', false, false));
    }

    public function test_individual_marking_has_distinct_mode(): void {
        $this->assertSame('marker', grading_assessment_view::mode(null, true, true));
        $this->assertSame('marker', grading_assessment_view::mode(null, true, false));
        $this->assertSame('multiple', grading_assessment_view::mode(null, false, true));
    }

    public function test_multi_marker_mode_is_not_silently_combined_with_rubrics(): void {
        $this->assertSame('rubric', grading_assessment_view::mode('rubric', false, true));
        $this->assertSame('guide', grading_assessment_view::mode('guide', false, true));
    }

    public function test_required_and_enabled_optional_markers_count_towards_expected_marks(): void {
        $allocations = [
            1 => (object)['marker' => 11, 'optional' => false, 'enabled' => 1],
            2 => (object)['marker' => 12, 'optional' => false, 'enabled' => 1],
            3 => (object)['marker' => 13, 'optional' => true, 'enabled' => 1],
        ];
        $marks = [
            11 => (object)['mark' => 85, 'workflowstate' => self::COMPLETED],
            12 => (object)['mark' => 0, 'workflowstate' => self::COMPLETED],
            13 => (object)['mark' => 75, 'workflowstate' => 'inmarking'],
        ];
        $progress = grading_assessment_view::marker_progress($allocations, $marks, 2, 1, self::COMPLETED);
        $this->assertSame(3, $progress['expected']);
        $this->assertSame(2, $progress['completed']);
        $this->assertTrue($progress['slots'][0]['completed']);
        $this->assertTrue($progress['slots'][1]['completed']);
        $this->assertFalse($progress['slots'][2]['completed']);
    }

    public function test_zero_mark_is_complete_when_native_workflow_is_complete(): void {
        $progress = grading_assessment_view::marker_progress(
            [1 => (object)['marker' => 19, 'optional' => false]],
            [19 => (object)['mark' => 0, 'workflowstate' => self::COMPLETED]],
            1, 0, self::COMPLETED
        );
        $this->assertSame(1, $progress['completed']);
    }

    public function test_disabled_optional_position_does_not_count(): void {
        $progress = grading_assessment_view::marker_progress(
            [1 => (object)['marker' => 11], 2 => (object)['marker' => 12, 'optional' => true, 'enabled' => 0]],
            [11 => (object)['mark' => 65, 'workflowstate' => self::COMPLETED],
                12 => (object)['mark' => 92, 'workflowstate' => self::COMPLETED]],
            1, 1, self::COMPLETED
        );
        $this->assertSame(1, $progress['expected']);
        $this->assertSame(1, $progress['completed']);
        $this->assertFalse($progress['slots'][1]['enabled']);
        $this->assertFalse($progress['slots'][1]['completed']);
    }

    public function test_unallocated_required_position_is_not_complete(): void {
        $progress = grading_assessment_view::marker_progress([], [], 2, 0, self::COMPLETED);
        $this->assertSame(2, $progress['expected']);
        $this->assertSame(0, $progress['completed']);
        $this->assertFalse($progress['slots'][0]['assigned']);
    }

    public function test_missing_or_negative_mark_does_not_complete(): void {
        foreach ([null, -1] as $mark) {
            $progress = grading_assessment_view::marker_progress(
                [1 => (object)['marker' => 11]],
                [11 => (object)['mark' => $mark, 'workflowstate' => self::COMPLETED]],
                1, 0, self::COMPLETED
            );
            $this->assertSame(0, $progress['completed']);
        }
    }

    public function test_non_completed_workflow_does_not_complete_a_valid_mark(): void {
        $progress = grading_assessment_view::marker_progress(
            [1 => (object)['marker' => 11]],
            [11 => (object)['mark' => 100, 'workflowstate' => 'inmarking']],
            1, 0, self::COMPLETED
        );
        $this->assertSame(0, $progress['completed']);
    }

    public function test_orphaned_marks_are_not_counted(): void {
        $progress = grading_assessment_view::marker_progress(
            [1 => (object)['marker' => 22]],
            [11 => (object)['mark' => 100, 'workflowstate' => self::COMPLETED]],
            1, 0, self::COMPLETED
        );
        $this->assertSame(0, $progress['completed']);
    }

    public function test_presenter_never_exposes_raw_marks_or_marker_identifiers(): void {
        $progress = grading_assessment_view::marker_progress(
            [1 => (object)['marker' => 111, 'name' => 'Confidential evaluator']],
            [111 => (object)['mark' => 96.5, 'workflowstate' => self::COMPLETED]],
            1, 0, self::COMPLETED
        );
        $this->assertSame(['position', 'optional', 'enabled', 'assigned', 'completed'],
            array_keys($progress['slots'][0]));
        $this->assertArrayNotHasKey('marker', $progress['slots'][0]);
        $this->assertArrayNotHasKey('mark', $progress['slots'][0]);
        $this->assertArrayNotHasKey('name', $progress['slots'][0]);
    }
}
