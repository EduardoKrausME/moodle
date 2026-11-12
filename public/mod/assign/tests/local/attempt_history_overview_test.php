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
 * Pure presentation data tests for the assignment attempt history.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attempt_history_overview::class)]
final class attempt_history_overview_test extends \basic_testcase {
    private function submission(int $number, string $status = 'submitted', int $modified = 100): \stdClass {
        return (object) [
            'attemptnumber' => $number,
            'status' => $status,
            'timemodified' => $modified,
        ];
    }

    private function grade(int $number, float $value): \stdClass {
        return (object) [
            'attemptnumber' => $number,
            'grade' => $value,
            'gradefordisplay' => (string) $value,
        ];
    }

    public function test_empty_history_does_not_invent_attempts(): void {
        $this->assertSame([], attempt_history_overview::describe([], [], true));
    }

    public function test_single_attempt_is_always_marked_current(): void {
        $history = attempt_history_overview::describe([$this->submission(0)], [], false);
        $this->assertCount(1, $history);
        $this->assertSame(0, $history[0]['attemptnumber']);
        $this->assertTrue($history[0]['iscurrent']);
    }

    public function test_attempts_are_descending_regardless_of_query_order(): void {
        $history = attempt_history_overview::describe([
            $this->submission(1), $this->submission(3), $this->submission(0), $this->submission(2),
        ], [], false);
        $this->assertSame([3, 2, 1, 0], array_column($history, 'attemptnumber'));
    }

    public function test_only_highest_attempt_is_current(): void {
        $history = attempt_history_overview::describe([
            $this->submission(4), $this->submission(1), $this->submission(2),
        ], [], false);
        $this->assertSame([true, false, false], array_column($history, 'iscurrent'));
    }

    public function test_reopened_status_is_not_assumed_submitted(): void {
        $history = attempt_history_overview::describe([
            $this->submission(0), $this->submission(1, 'reopened'),
        ], [], false);
        $this->assertSame('reopened', $history[0]['status']);
        $this->assertSame('submitted', $history[1]['status']);
    }

    public function test_draft_is_not_mislabeled_as_final_submission(): void {
        $history = attempt_history_overview::describe([$this->submission(2, 'draft')], [], false);
        $this->assertSame('draft', $history[0]['status']);
    }

    public function test_grade_is_matched_by_attempt_number_not_array_position(): void {
        $history = attempt_history_overview::describe([
            $this->submission(0), $this->submission(2), $this->submission(1),
        ], [
            $this->grade(1, 10), $this->grade(0, 20), $this->grade(2, 30),
        ], true);
        $this->assertSame(30.0, $history[0]['grade']->grade);
        $this->assertSame(10.0, $history[1]['grade']->grade);
        $this->assertSame(20.0, $history[2]['grade']->grade);
    }

    public function test_gradebook_hidden_grade_is_not_in_timeline_context(): void {
        $history = attempt_history_overview::describe(
            [$this->submission(0)], [$this->grade(0, 95)], false
        );
        $this->assertNull($history[0]['grade']);
    }

    public function test_grade_not_set_is_not_presented_as_a_real_grade(): void {
        $history = attempt_history_overview::describe(
            [$this->submission(0)], [$this->grade(0, (float) ASSIGN_GRADE_NOT_SET)], true
        );
        $this->assertNull($history[0]['grade']);
    }

    public function test_zero_grade_is_a_valid_grade_when_visible(): void {
        $history = attempt_history_overview::describe(
            [$this->submission(0)], [$this->grade(0, 0)], true
        );
        $this->assertSame(0.0, $history[0]['grade']->grade);
    }

    public function test_grade_for_other_attempt_is_never_reused(): void {
        $history = attempt_history_overview::describe(
            [$this->submission(2)], [$this->grade(1, 80)], true
        );
        $this->assertNull($history[0]['grade']);
    }

    public function test_last_modified_is_preserved_without_claiming_submission_time(): void {
        $history = attempt_history_overview::describe([$this->submission(1, 'draft', 123456)], [], false);
        $this->assertSame(123456, $history[0]['modified']);
    }

    public function test_invalid_negative_timestamp_is_clamped(): void {
        $history = attempt_history_overview::describe([$this->submission(0, 'new', -20)], [], false);
        $this->assertSame(0, $history[0]['modified']);
    }

    public function test_malformed_or_negative_attempt_numbers_are_ignored(): void {
        $history = attempt_history_overview::describe([
            (object) ['status' => 'submitted'],
            $this->submission(-1),
            $this->submission(0),
        ], [], false);
        $this->assertCount(1, $history);
    }

    public function test_source_objects_are_not_mutated_by_presentation(): void {
        $submission = $this->submission(0);
        $grade = $this->grade(0, 80);
        attempt_history_overview::describe([$submission], [$grade], true);
        $this->assertFalse(property_exists($submission, 'grade'));
        $this->assertFalse(property_exists($grade, 'iscurrent'));
    }
}
