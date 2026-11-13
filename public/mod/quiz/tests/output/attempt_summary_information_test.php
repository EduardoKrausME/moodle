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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_quiz\output;

use advanced_testcase;

/**
 * Tests for {@see attempt_summary_information}.
 *
 * @package   mod_quiz
 * @copyright The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_quiz\output\attempt_summary_information::class)]
final class attempt_summary_information_test extends advanced_testcase {

    public function test_add_item(): void {
        global $PAGE;

        $summary = new attempt_summary_information();
        $summary->add_item('test', 'Test name', 'Test value');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals([(object) ['title' => 'Test name', 'content' => 'Test value']], $data['items']);
    }

    public function test_add_item_before_start(): void {
        global $PAGE;

        $summary = new attempt_summary_information();
        $summary->add_item('test', 'Test name', 'Test value');

        $summary->add_item_before('newitem', 'New name', 'New value', 'test');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals(
            [
                (object) ['title' => 'New name', 'content' => 'New value'],
                (object) ['title' => 'Test name', 'content' => 'Test value'],
            ],
            $data['items'],
        );
    }

    public function test_add_item_before_middle(): void {
        global $PAGE;

        $summary = new attempt_summary_information();
        $summary->add_item('item1', 'Existing 1', 'One');
        $summary->add_item('item2', 'Existing 2', 'Two');

        $summary->add_item_before('newitem', 'New name', 'New value', 'item2');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals(
            [
                (object) ['title' => 'Existing 1', 'content' => 'One'],
                (object) ['title' => 'New name', 'content' => 'New value'],
                (object) ['title' => 'Existing 2', 'content' => 'Two'],
            ],
            $data['items'],
        );
    }

    public function test_add_item_before_no_match(): void {
        global $PAGE;

        $summary = new attempt_summary_information();
        $summary->add_item('test', 'Test name', 'Test value');

        $summary->add_item_before('newitem', 'New name', 'New value', 'unknown');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals(
            [
                (object) ['title' => 'New name', 'content' => 'New value'],
                (object) ['title' => 'Test name', 'content' => 'Test value'],
            ],
            $data['items'],
        );
    }

    public function test_add_item_before_empty(): void {
        global $PAGE;

        $summary = new attempt_summary_information();

        $summary->add_item_before('newitem', 'New name', 'New value', 'unknown');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals([(object) ['title' => 'New name', 'content' => 'New value']], $data['items']);
    }

    public function test_add_item_after_end(): void {
        global $PAGE;

        $summary = new attempt_summary_information();
        $summary->add_item('test', 'Test name', 'Test value');

        $summary->add_item_after('newitem', 'New name', 'New value', 'test');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals(
            [
                (object) ['title' => 'Test name', 'content' => 'Test value'],
                (object) ['title' => 'New name', 'content' => 'New value'],
            ],
            $data['items'],
        );
    }

    public function test_add_item_after_middle(): void {
        global $PAGE;

        $summary = new attempt_summary_information();
        $summary->add_item('item1', 'Existing 1', 'One');
        $summary->add_item('item2', 'Existing 2', 'Two');

        $summary->add_item_after('newitem', 'New name', 'New value', 'item1');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals(
            [
                (object) ['title' => 'Existing 1', 'content' => 'One'],
                (object) ['title' => 'New name', 'content' => 'New value'],
                (object) ['title' => 'Existing 2', 'content' => 'Two'],
            ],
            $data['items'],
        );
    }

    public function test_add_item_after_no_match(): void {
        global $PAGE;

        $summary = new attempt_summary_information();
        $summary->add_item('test', 'Test name', 'Test value');

        $summary->add_item_after('newitem', 'New name', 'New value', 'unknown');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals(
            [
                (object) ['title' => 'Test name', 'content' => 'Test value'],
                (object) ['title' => 'New name', 'content' => 'New value'],
            ],
            $data['items'],
        );
    }

    public function test_add_item_after_empty(): void {
        global $PAGE;

        $summary = new attempt_summary_information();

        $summary->add_item_after('newitem', 'New name', 'New value', 'unknown');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals([(object) ['title' => 'New name', 'content' => 'New value']], $data['items']);
    }

    public function test_remove_item(): void {
        global $PAGE;

        $summary = new attempt_summary_information();
        $summary->add_item('item1', 'Existing 1', 'One');
        $summary->add_item('item2', 'Existing 2', 'Two');

        $summary->remove_item('item1');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals([(object) ['title' => 'Existing 2', 'content' => 'Two']], $data['items']);
    }

    public function test_remove_item_not_present(): void {
        global $PAGE;

        $summary = new attempt_summary_information();
        $summary->add_item('item1', 'Existing 1', 'One');
        $summary->add_item('item2', 'Existing 2', 'Two');

        $summary->remove_item('item3');

        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $this->assertEquals(
            [
                (object) ['title' => 'Existing 1', 'content' => 'One'],
                (object) ['title' => 'Existing 2', 'content' => 'Two'],
            ],
            $data['items'],
        );
    }

    /**
     * A submitted attempt has a finish timestamp even when grading has not finished.
     * Its summary must not calculate or print hidden grade feedback.
     */
    public function test_submitted_attempt_has_completion_date_but_no_hidden_grade_or_feedback(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());

        $attemptdata = (object) [
            'state' => \mod_quiz\quiz_attempt::SUBMITTED,
            'timestart' => 10000,
            'timefinish' => 10600,
        ];
        $quiz = (object) [
            'showuserpicture' => true,
            'timelimit' => 0,
            'sumgrades' => 10,
            'grade' => 10,
        ];
        $attemptobj = $this->getMockBuilder(\mod_quiz\quiz_attempt::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'get_quiz',
                'get_attempt',
                'get_attempt_number',
                'get_sum_marks',
                'get_additional_summary_data',
                'get_overall_feedback',
            ])
            ->getMock();
        $attemptobj->method('get_quiz')->willReturn($quiz);
        $attemptobj->method('get_attempt')->willReturn($attemptdata);
        $attemptobj->method('get_attempt_number')->willReturn(1);
        $attemptobj->method('get_sum_marks')->willReturn(7.0);
        $attemptobj->method('get_additional_summary_data')->willReturn([]);
        $attemptobj->expects($this->never())->method('get_overall_feedback');

        $options = new \mod_quiz\question\display_options();
        $options->marks = \question_display_options::HIDDEN;
        $options->overallfeedback = \question_display_options::HIDDEN;

        $summary = attempt_summary_information::create_for_attempt($attemptobj, $options);
        $data = $summary->export_for_template($PAGE->get_renderer('mod_quiz'));
        $titles = array_column($data['items'], 'title');
        $this->assertContains(get_string('startedon', 'quiz'), $titles);
        $this->assertContains(get_string('completedon', 'quiz'), $titles);
        $this->assertContains(get_string('attemptduration', 'quiz'), $titles);
        $this->assertNotContains(get_string('gradenoun'), $titles);
        $this->assertNotContains(get_string('feedback', 'quiz'), $titles);
        $this->assertContains(userdate(10600), array_column($data['items'], 'content'));
    }
}
