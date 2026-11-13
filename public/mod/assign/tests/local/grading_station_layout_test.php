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
 * Only a bounded layout mode and ratio may be stored, never grading content.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(grading_station_layout::class)]
final class grading_station_layout_test extends \basic_testcase {
    public function test_missing_preference_defaults_to_split(): void {
        $this->assertSame(
            ['mode' => 'split', 'split' => 70],
            grading_station_layout::normalise(null)
        );
    }

    public function test_default_preference_is_accepted(): void {
        $this->assertSame(
            ['mode' => 'split', 'split' => 70],
            grading_station_layout::normalise('split_70')
        );
    }

    public function test_custom_split_restored(): void {
        $this->assertSame(
            ['mode' => 'split', 'split' => 63],
            grading_station_layout::normalise('split_63')
        );
    }

    public function test_grade_only_mode_preserves_last_split(): void {
        $this->assertSame(
            ['mode' => 'grade', 'split' => 46],
            grading_station_layout::normalise('grade_46')
        );
    }

    public function test_preview_only_mode_preserves_last_split(): void {
        $this->assertSame(
            ['mode' => 'review', 'split' => 55],
            grading_station_layout::normalise('review_55')
        );
    }

    public function test_boundaries_are_inclusive(): void {
        $this->assertSame(
            ['mode' => 'split', 'split' => 8],
            grading_station_layout::normalise('split_8')
        );
        $this->assertSame(
            ['mode' => 'split', 'split' => 92],
            grading_station_layout::normalise('split_92')
        );
    }

    public function test_invalid_split_values_fall_back_to_default(): void {
        foreach (['split_0', 'grade_7', 'review_93', 'split_100', 'split_-1', 'split_2000'] as $value) {
            $this->assertSame(
                ['mode' => 'split', 'split' => 70],
                grading_station_layout::normalise($value)
            );
        }
    }

    public function test_old_and_unknown_values_are_ignored(): void {
        foreach (['split:70', 'other_70', 'split', 'grade_', '', 'split_70<script>'] as $value) {
            $this->assertSame(
                ['mode' => 'split', 'split' => 70],
                grading_station_layout::normalise($value)
            );
        }
    }

    public function test_non_string_values_are_not_cast_to_preferences(): void {
        foreach ([false, true, 0, 70, [], new \stdClass()] as $value) {
            $this->assertSame(
                ['mode' => 'split', 'split' => 70],
                grading_station_layout::normalise($value)
            );
        }
    }

    public function test_absence_of_preview_resets_other_modes(): void {
        foreach (['split_61', 'review_55', 'grade_30'] as $value) {
            $this->assertSame(
                ['mode' => 'split', 'split' => 70],
                grading_station_layout::normalise($value, false)
            );
        }
    }

    public function test_user_preference_is_registered_for_current_user_only(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/lib.php');
        $preferences = \mod_assign_user_preferences();
        $this->assertArrayHasKey('assign_grading_station_layout', $preferences);
        $preference = $preferences['assign_grading_station_layout'];
        $this->assertSame(PARAM_ALPHANUMEXT, $preference['type']);
        $this->assertSame('split_70', $preference['default']);
        $this->assertSame([\core_user::class, 'is_current_user'], $preference['permissioncallback']);
    }

    public function test_normalised_values_cannot_contain_identifying_data(): void {
        $layout = grading_station_layout::normalise('grade_70_user_42_submission_10');
        $this->assertSame(['mode' => 'split', 'split' => 70], $layout);
        $this->assertSame(['mode', 'split'], array_keys($layout));
    }
}
