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

use core_external\external_api;
use mod_forum\external\get_unanswered_discussions;

defined('MOODLE_INTERNAL') || die();

/**
 * Access and state regression tests for the read-only teacher queue.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\local\unanswered_discussions::class)]
final class unanswered_discussions_test extends \advanced_testcase {
    /**
     * @return array Generator, course, teacher, discussion author, replier, forum.
     */
    private function fixture(bool $allowsolutions = true): array {
        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $author = $generator->create_user();
        $replier = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($author->id, $course->id, 'student');
        $generator->enrol_user($replier->id, $course->id, 'student');
        $forum = $generator->create_module('forum', [
            'course' => $course->id,
            'name' => 'Question forum',
            'allowsolutions' => (int)$allowsolutions,
        ]);
        $this->setUser($teacher);
        return [$generator, $course, $teacher, $author, $replier, $forum];
    }

    /**
     * @param \stdClass $course Course.
     * @param \stdClass $forum Forum module.
     * @param \stdClass $author Discussion creator.
     * @param string $name Name.
     * @return \stdClass New discussion.
     */
    private function discussion(\stdClass $course, \stdClass $forum, \stdClass $author,
            string $name): \stdClass {
        return self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id,
            'course' => $course->id,
            'userid' => $author->id,
            'name' => $name,
        ]);
    }

    /**
     * @param \stdClass $discussion Discussion.
     * @param \stdClass $author Post author.
     * @param array $extra Optional fields.
     * @return \stdClass Reply.
     */
    private function reply(\stdClass $discussion, \stdClass $author, array $extra = []): \stdClass {
        return self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post((object)array_merge([
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $author->id,
            'message' => 'Useful answer',
        ], $extra));
    }

    public function test_classifies_unanswered_self_reply_public_reply_and_accepted_reply(): void {
        $this->resetAfterTest();
        [$generator, $course, $teacher, $author, $replier, $forum] = $this->fixture();
        $noanswer = $this->discussion($course, $forum, $author, 'Still waiting');
        $selfreply = $this->discussion($course, $forum, $author, 'Additional details');
        $this->reply($selfreply, $author);
        $answered = $this->discussion($course, $forum, $author, 'Answered question');
        $this->reply($answered, $replier);
        $solved = $this->discussion($course, $forum, $author, 'Solved question');
        $acceptedreply = $this->reply($solved, $replier);
        solution_manager::set_solution($solved->id, $acceptedreply->id);

        $page = unanswered_discussions::get_page($course->id, $forum->id);
        $this->assertCount(4, $page['items']);
        $statuses = array_column($page['items'], 'status', 'discussionid');
        $this->assertSame('unanswered', $statuses[$noanswer->id]);
        $this->assertSame('unanswered', $statuses[$selfreply->id]);
        $this->assertSame('awaiting', $statuses[$answered->id]);
        $this->assertSame('accepted', $statuses[$solved->id]);
        $this->assertSame(2, $page['counts']['unanswered']);
        $this->assertSame(1, $page['counts']['awaiting']);
        $this->assertSame(1, $page['counts']['accepted']);
        $this->assertSame(0, $page['counts']['answered']);
        $this->assertCount(2, unanswered_discussions::get_page(
            $course->id, $forum->id, 0, 'unanswered')['items']);
        $this->assertCount(1, unanswered_discussions::get_page(
            $course->id, $forum->id, 0, 'accepted')['items']);
    }

    public function test_forums_without_accepted_answers_remain_distinct_from_unanswered(): void {
        $this->resetAfterTest();
        [, $course, , $author, $replier, $forum] = $this->fixture(false);
        $discussion = $this->discussion($course, $forum, $author, 'Regular answer');
        $this->reply($discussion, $replier);
        $page = unanswered_discussions::get_page($course->id);
        $this->assertSame('answered', $page['items'][0]['status']);
        $this->assertCount(1, unanswered_discussions::get_page(
            $course->id, $forum->id, 0, 'answered')['items']);
        $this->assertEmpty(unanswered_discussions::get_page(
            $course->id, $forum->id, 0, 'awaiting')['items']);
    }

    public function test_private_and_deleted_replies_are_not_public_answers(): void {
        global $DB;

        $this->resetAfterTest();
        [, $course, , $author, $replier, $forum] = $this->fixture();
        $discussion = $this->discussion($course, $forum, $author, 'Public answers only');
        $this->reply($discussion, $replier, ['privatereplyto' => $author->id]);
        $deleted = $this->reply($discussion, $replier);
        $DB->set_field('forum_posts', 'deleted', 1, ['id' => $deleted->id]);
        $page = unanswered_discussions::get_page($course->id);
        $this->assertSame('unanswered', $page['items'][0]['status']);
    }

    public function test_inaccessible_course_and_student_are_not_enumerated(): void {
        $this->resetAfterTest();
        [$generator, $course, $teacher, $author, , $forum] = $this->fixture();
        $discussion = $this->discussion($course, $forum, $author, 'Restricted question');
        $othercourse = $generator->create_course();
        $otherforum = $generator->create_module('forum', ['course' => $othercourse->id]);
        $otherdiscussion = $this->discussion($othercourse, $otherforum, $author, 'Hidden elsewhere');

        $page = unanswered_discussions::get_page();
        $this->assertSame([$discussion->id], array_column($page['items'], 'discussionid'));
        $this->assertEmpty(unanswered_discussions::get_page($othercourse->id)['items']);
        $this->assertEmpty(unanswered_discussions::get_page(0, $otherforum->id)['items']);
        $this->assertNotContains($otherdiscussion->id, array_column($page['items'], 'discussionid'));

        $this->setUser($author);
        $this->assertEmpty(unanswered_discussions::get_course_options());
        $this->assertEmpty(unanswered_discussions::get_page()['items']);
    }

    public function test_course_forum_and_status_filters_and_native_urls(): void {
        $this->resetAfterTest();
        [$generator, $course, $teacher, $author, , $forum] = $this->fixture();
        $otherforum = $generator->create_module('forum', ['course' => $course->id]);
        $first = $this->discussion($course, $forum, $author, 'First help topic');
        $second = $this->discussion($course, $otherforum, $author, 'Second help topic');
        $this->assertCount(2, unanswered_discussions::get_page($course->id)['items']);
        $page = unanswered_discussions::get_page($course->id, $forum->id);
        $this->assertSame((int)$first->id, $page['items'][0]['discussionid']);
        $this->assertStringContainsString('discuss.php?d=', $page['items'][0]['url']);
        $this->assertNotContains((int)$second->id, array_column($page['items'], 'discussionid'));
        $this->assertCount(2, unanswered_discussions::get_forum_options($course->id));
        $this->assertCount(1, unanswered_discussions::get_course_options());
    }

    public function test_cursor_is_stable_and_bound_to_user_and_filter_settings(): void {
        $this->resetAfterTest();
        [, $course, , $author, , $forum] = $this->fixture();
        for ($i = 0; $i < 23; $i++) {
            $this->discussion($course, $forum, $author, 'Question ' . $i);
        }
        $first = unanswered_discussions::get_page($course->id);
        $this->assertCount(20, $first['items']);
        $this->assertTrue($first['hasmore']);
        $this->assertNotEmpty($first['nextcursor']);
        $second = unanswered_discussions::get_page($course->id, 0, 0, 'all', 'oldest', $first['nextcursor']);
        $this->assertCount(3, $second['items']);
        $this->assertFalse($second['hasmore']);
        $ids = array_merge(array_column($first['items'], 'discussionid'),
            array_column($second['items'], 'discussionid'));
        $this->assertCount(23, array_unique($ids));

        $this->expectException(\invalid_parameter_exception::class);
        unanswered_discussions::get_page($course->id, 0, 0, 'unanswered', 'oldest', $first['nextcursor']);
    }

    public function test_ajax_endpoint_returns_the_same_authorised_projection(): void {
        $this->resetAfterTest();
        [, $course, , $author, , $forum] = $this->fixture();
        $discussion = $this->discussion($course, $forum, $author, 'API question');
        $result = get_unanswered_discussions::execute($course->id, $forum->id, 0, 'all', 'oldest', '');
        $result = external_api::clean_returnvalue(get_unanswered_discussions::execute_returns(), $result);
        $this->assertCount(1, $result['items']);
        $this->assertSame((int)$discussion->id, $result['items'][0]['discussionid']);
        $this->assertSame('unanswered', $result['items'][0]['status']);
    }

    public function test_group_filter_never_returns_another_group(): void {
        $this->resetAfterTest();
        [$generator, $course, , $author, , $forum] = $this->fixture();
        $group1 = $generator->create_group(['courseid' => $course->id]);
        $group2 = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $author->id]);
        $generator->create_group_member(['groupid' => $group2->id, 'userid' => $author->id]);
        // The forum is switched to visible groups to permit observation by all teachers.
        set_coursemodule_groupmode($forum->cmid, VISIBLEGROUPS);
        rebuild_course_cache($course->id, true);
        $first = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
            'name' => 'Group one question', 'groupid' => $group1->id,
        ]);
        $second = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
            'name' => 'Group two question', 'groupid' => $group2->id,
        ]);
        $page = unanswered_discussions::get_page($course->id, $forum->id, $group1->id);
        $this->assertContains($first->id, array_column($page['items'], 'discussionid'));
        $this->assertNotContains($second->id, array_column($page['items'], 'discussionid'));
    }
}
