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
 * Presentation-only tests: permission and grade visibility decisions are native.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(feedback_overview::class)]
final class feedback_overview_test extends \basic_testcase {
    public function test_unavailable_feedback_has_no_visible_values(): void {
        $data = feedback_overview::describe(null, 0, [], '', '');
        $this->assertFalse($data['hascontent']);
        $this->assertFalse($data['hasgrade']);
        $this->assertFalse($data['hasplugins']);
        $this->assertFalse($data['hascriteria']);
        $this->assertSame('', $data['gradehtml']);
    }

    public function test_grade_zero_is_a_real_visible_grade(): void {
        $data = feedback_overview::describe('0', 100, [], '', '');
        $this->assertTrue($data['hascontent']);
        $this->assertTrue($data['hasgrade']);
        $this->assertTrue($data['hasgradeddate']);
        $this->assertSame('0', $data['gradehtml']);
    }

    public function test_gradebook_html_is_preserved_without_recalculation(): void {
        $native = '<span class="penalty-indicator-icon">-2</span>8 / 10';
        $data = feedback_overview::describe($native, 0, [], '', '');
        $this->assertSame($native, $data['gradehtml']);
        $this->assertFalse($data['hasgradeddate']);
    }

    public function test_feedback_only_without_numeric_grade_is_valid(): void {
        $plugin = ['label' => 'Feedback comments', 'html' => '<p>Comments</p>'];
        $data = feedback_overview::describe(null, 0, [$plugin], '', '');
        $this->assertTrue($data['hascontent']);
        $this->assertFalse($data['hasgrade']);
        $this->assertTrue($data['hasplugins']);
        $this->assertSame([$plugin], $data['plugins']);
    }

    public function test_advanced_grading_without_grade_still_has_content(): void {
        $data = feedback_overview::describe(null, 0, [], '<div>Rubric</div>', '');
        $this->assertTrue($data['hascontent']);
        $this->assertTrue($data['hascriteria']);
        $this->assertSame('<div>Rubric</div>', $data['criteriahtml']);
    }

    public function test_grader_identity_is_not_displayed_without_visible_grade(): void {
        $data = feedback_overview::describe(null, 0, [], '', '<span>Teacher</span>');
        $this->assertFalse($data['hasgrader']);
        $this->assertSame('', $data['graderhtml']);
        $this->assertFalse($data['hascontent']);
    }

    public function test_visible_grader_and_multiple_plugins_are_preserved(): void {
        $plugins = [
            ['label' => 'Comments', 'html' => '<p>Comment</p>'],
            ['label' => 'Annotated PDF', 'html' => '<a href="/file.php">PDF</a>'],
        ];
        $data = feedback_overview::describe('10 / 10', 100, $plugins, '', '<span>Teacher</span>');
        $this->assertTrue($data['hasgrader']);
        $this->assertSame('<span>Teacher</span>', $data['graderhtml']);
        $this->assertSame($plugins, $data['plugins']);
    }

    public function test_empty_grade_and_empty_criteria_are_not_displayed(): void {
        $data = feedback_overview::describe('  ', -1, [], '   ', '');
        $this->assertFalse($data['hascontent']);
        $this->assertFalse($data['hasgrade']);
        $this->assertFalse($data['hasgradeddate']);
        $this->assertFalse($data['hascriteria']);
    }
}
