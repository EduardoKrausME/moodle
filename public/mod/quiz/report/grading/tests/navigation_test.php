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

/**
 * Security and presentation tests for native manual quiz grading.
 *
 * @package quiz_grading
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/report/grading/report.php');

/**
 * Test native grading navigation and server-side validation of posted usage ids.
 */
final class quiz_grading_navigation_test extends advanced_testcase {
    /**
     * Construct a report with the same context as the quiz under test.
     *
     * @param stdClass $quiz Quiz record.
     * @param stdClass $cm Module record.
     * @param stdClass $course Course record.
     * @return quiz_grading_report
     */
    private function report_for_quiz(stdClass $quiz, stdClass $cm, stdClass $course): quiz_grading_report {
        $report = new class extends quiz_grading_report {
            public function set_test_scope(stdClass $quiz, stdClass $cm, stdClass $course): void {
                $this->quiz = $quiz;
                $this->cm = $cm;
                $this->course = $course;
                $this->context = context_module::instance($cm->id);
            }

            public function check_submitted_ids(array $ids, int $slot, int $qid, string $state): bool {
                return $this->submitted_usages_are_authorised($ids, $slot, $qid, $state);
            }
        };
        $report->set_test_scope($quiz, $cm, $course);
        return $report;
    }

    public function test_empty_or_repeated_usage_ids_are_not_authorised(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
        ]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $report = $this->report_for_quiz($quiz, $cm, $course);

        $this->assertFalse($report->check_submitted_ids([], 1, 1, 'all'));
        $this->assertFalse($report->check_submitted_ids([42, 42], 1, 1, 'all'));
    }

    public function test_only_finished_attempts_in_the_same_quiz_and_question_can_be_graded(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('essay', null, ['category' => $category->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $quiz = $quizgenerator->create_instance(['course' => $course->id]);
        quiz_add_quiz_question($question->id, $quiz, 1);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        $report = $this->report_for_quiz($quiz, $cm, $course);

        $unfinished = $quizgenerator->create_attempt($quiz->id, $student->id);
        $this->assertFalse($report->check_submitted_ids([$unfinished->uniqueid], 1, $question->id, 'all'));

        $attempt = \mod_quiz\quiz_attempt::create($unfinished->id);
        $attempt->process_submit(time(), true);
        $attempt->process_grade_submission(time());
        $this->assertTrue($report->check_submitted_ids([$unfinished->uniqueid], 1, $question->id, 'all'));
        $this->assertFalse($report->check_submitted_ids([$unfinished->uniqueid], 2, $question->id, 'all'));
        $this->assertFalse($report->check_submitted_ids([$unfinished->uniqueid], 1, $question->id + 10000, 'all'));
        $this->assertFalse($report->check_submitted_ids([$unfinished->uniqueid, 99999999],
                1, $question->id, 'all'));

        $anotherquiz = $quizgenerator->create_instance(['course' => $course->id]);
        quiz_add_quiz_question($question->id, $anotherquiz, 1);
        \mod_quiz\quiz_settings::create($anotherquiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        $anotherattempt = $quizgenerator->create_attempt($anotherquiz->id, $student->id);
        $other = \mod_quiz\quiz_attempt::create($anotherattempt->id);
        $other->process_submit(time(), true);
        $other->process_grade_submission(time());
        $this->assertFalse($report->check_submitted_ids([$anotherattempt->uniqueid], 1, $question->id, 'all'));
    }

    public function test_native_response_is_wrapped_without_changing_question_fields(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/mod/quiz/report.php');
        $PAGE->set_context(context_system::instance());
        $renderer = $PAGE->get_renderer('quiz_grading');

        $usage = new class {
            public function render_question($slot, $displayoptions, $number): string {
                return '<div class="que"><input name="q1:1_-mark" value="1"></div>';
            }
        };
        $html = $renderer->render_grade_question($usage, 1, new question_display_options(), 1, '', 1, 2);
        $this->assertStringContainsString('data-region="manual-grading-card"', $html);
        $this->assertStringContainsString('aria-label="Response 1 in this batch"', $html);
        $this->assertStringContainsString('href="#mod-quiz-grading-response-2"', $html);
        $this->assertStringContainsString('data-region="manual-grading-card-status"', $html);
        $this->assertStringContainsString('name="q1:1_-mark"', $html);
        $this->assertStringNotContainsString('Previous response', $html);

        // Preserving the old 5-argument signature keeps existing renderers working.
        $legacy = $renderer->render_grade_question($usage, 1, new question_display_options(), 1, '');
        $this->assertStringNotContainsString('manual-grading-card', $legacy);
        $this->assertStringContainsString('name="q1:1_-mark"', $legacy);
    }

    public function test_last_response_keeps_authorised_heading_and_back_navigation(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_url('/mod/quiz/report.php');
        $PAGE->set_context(context_system::instance());
        $renderer = $PAGE->get_renderer('quiz_grading');

        $usage = new class {
            public function render_question($slot, $displayoptions, $number): string {
                return '<div class="que">Student answer</div>';
            }
        };
        $html = $renderer->render_grade_question($usage, 1, new question_display_options(),
                1, 'Attempt number 1 for authorised student', 2, 2);
        $this->assertStringContainsString('Attempt number 1 for authorised student', $html);
        $this->assertStringContainsString('href="#mod-quiz-grading-response-1"', $html);
        $this->assertStringNotContainsString('href="#mod-quiz-grading-response-3"', $html);
        $this->assertStringContainsString('Student answer', $html);
    }
}
