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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

namespace mod_forum\local;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use mod_forum\external\get_participation_report;

/**
 * Participation statistics, visibility and permissions regression tests.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\local\participation_report::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\local\participation_metrics::class)]
final class participation_report_test extends \advanced_testcase {
    /**
     * @param string $type Forum type.
     * @return array Teacher, author, replier, course and module.
     */
    private function fixture(string $type = 'general'): array {
        $generator = self::getDataGenerator();
        $teacher = $generator->create_user();
        $author = $generator->create_user();
        $replier = $generator->create_user();
        $course = $generator->create_course();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($author->id, $course->id, 'student');
        $generator->enrol_user($replier->id, $course->id, 'student');
        $forum = $generator->create_module('forum', [
            'course' => $course->id, 'type' => $type,
            'allowsolutions' => 1,
        ]);
        $this->setUser($teacher);
        return [$teacher, $author, $replier, $course, $forum];
    }

    private function current_date(): string {
        global $USER;

        return (new \DateTimeImmutable('now',
            new \DateTimeZone(\core_date::get_user_timezone($USER))))->format('Y-m-d');
    }

    private function discussion(\stdClass $course, \stdClass $forum,
            \stdClass $author, string $name): \stdClass {
        return self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id,
            'userid' => $author->id, 'name' => $name,
        ]);
    }

    private function reply(\stdClass $discussion, \stdClass $author,
            array $other = []): \stdClass {
        return self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post((object)array_merge([
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $author->id,
            'message' => 'Visible explanation',
        ], $other));
    }

    public function test_exact_period_metrics_and_current_accepted_answers(): void {
        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum] = $this->fixture();
        $answered = $this->discussion($course, $forum, $author, 'Answered');
        $this->discussion($course, $forum, $author, 'Waiting');
        $answer = $this->reply($answered, $replier);
        solution_manager::set_solution($answered->id, $answer->id);
        $today = $this->current_date();

        $report = participation_report::get_page($course->id, $forum->id, 0, $today, $today);
        $metrics = $report['metrics'];
        $this->assertSame(2, $metrics['discussions']);
        $this->assertSame(1, $metrics['replies']);
        $this->assertSame(2, $metrics['participants']);
        $this->assertSame(1, $metrics['responded']);
        $this->assertEquals(50.0, $metrics['coverage']);
        $this->assertSame(1, $metrics['accepted']);
        $this->assertCount(1, $report['forums']);
        $this->assertCount(1, $report['series']);
        $this->assertSame(2, $report['series'][0]['discussions']);
        $this->assertSame(1, $report['series'][0]['replies']);
        $this->assertStringContainsString('/mod/forum/report/summary/', $report['forums'][0]['summaryurl']);
        $this->assertStringContainsString('unanswered.php', $report['forums'][0]['queueurl']);
    }

    public function test_private_deleted_and_self_replies_do_not_change_response_coverage(): void {
        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum] = $this->fixture();
        $discussion = $this->discussion($course, $forum, $author, 'Private and self');
        $this->reply($discussion, $author);
        $this->reply($discussion, $replier, ['privatereplyto' => $teacher->id]);
        $this->reply($discussion, $replier, ['deleted' => 1]);
        $today = $this->current_date();

        $report = participation_report::get_page($course->id, $forum->id, 0, $today, $today);
        $this->assertSame(1, $report['metrics']['discussions']);
        $this->assertSame(1, $report['metrics']['replies']);
        $this->assertSame(1, $report['metrics']['participants']);
        $this->assertSame(0, $report['metrics']['responded']);
        $this->assertEquals(0.0, $report['metrics']['coverage']);
    }

    public function test_report_aggregates_courses_without_counting_repeat_authors_twice(): void {
        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum] = $this->fixture();
        $secondforum = self::getDataGenerator()->create_module('forum', [
            'course' => $course->id, 'allowsolutions' => 0,
        ]);
        $this->discussion($course, $forum, $author, 'First forum');
        $this->discussion($course, $secondforum, $author, 'Second forum');
        $today = $this->current_date();

        $report = participation_report::get_page($course->id, 0, 0, $today, $today);
        $this->assertSame(2, $report['metrics']['discussions']);
        $this->assertSame(1, $report['metrics']['participants']);
        $this->assertCount(2, $report['forums']);
    }

    public function test_invalid_ranges_and_csv_formula_cells_are_safe(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->assertSame("'=SUM(A1:A2)", participation_report::csv_cell('=SUM(A1:A2)'));
        $this->assertSame("'+cmd", participation_report::csv_cell('+cmd'));
        $this->assertSame('Ordinary forum', participation_report::csv_cell('<b>Ordinary forum</b>'));
        $this->assertSame('', participation_report::csv_cell(''));
        $this->expectException(\invalid_parameter_exception::class);
        participation_report::date_range('2026-01-01', '2026-04-01');
    }

    public function test_participation_is_not_available_to_student_or_course_outsider(): void {
        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum] = $this->fixture();
        $this->setUser($author);
        $this->assertSame([], participation_report::get_course_options());
        $today = $this->current_date();
        $this->expectException(\required_capability_exception::class);
        participation_report::get_page($course->id, $forum->id, 0, $today, $today);
    }

    public function test_qanda_metrics_keep_inaccessible_replies_out_of_aggregates(): void {
        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum] = $this->fixture('qanda');
        $discussion = $this->discussion($course, $forum, $author, 'Question and answer');
        $this->reply($discussion, $replier);
        $today = $this->current_date();
        $teacherreport = participation_report::get_page($course->id, $forum->id, 0, $today, $today);
        $this->assertSame(1, $teacherreport['metrics']['discussions']);
        $this->assertSame(1, $teacherreport['metrics']['replies']);

        $this->setUser($author);
        $this->expectException(\required_capability_exception::class);
        participation_report::get_page($course->id, $forum->id, 0, $today, $today);
    }

    public function test_ajax_returns_the_same_aggregates_as_the_page(): void {
        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum] = $this->fixture();
        $this->discussion($course, $forum, $author, 'API forum');
        $today = $this->current_date();
        $result = get_participation_report::execute($course->id, $forum->id, 0, $today, $today);
        $clean = external_api::clean_returnvalue(get_participation_report::execute_returns(), $result);
        $this->assertSame(1, $clean['metrics']['discussions']);
        $this->assertSame($course->id, $clean['courseid']);
        $this->assertCount(1, $clean['forums']);
    }
}
