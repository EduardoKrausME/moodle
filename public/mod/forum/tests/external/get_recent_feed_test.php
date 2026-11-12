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

namespace mod_forum\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;

/**
 * Access, sorting and pagination tests for the cross-course forum feed.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\external\get_recent_feed::class)]
final class get_recent_feed_test extends \core_external\tests\externallib_testcase {
    /**
     * @return array Enrolled student, course and generator.
     */
    private function fixture(): array {
        global $CFG;

        $CFG->forum_trackreadposts = 1;
        $CFG->forum_allowforcedreadtracking = 1;
        $generator = self::getDataGenerator();
        $student = $generator->create_user(['trackforums' => 1]);
        $course = $generator->create_course();
        $generator->enrol_user($student->id, $course->id);
        $this->setUser($student);
        return [$student, $course, $generator];
    }

    /**
     * Call the real AJAX endpoint and clean its return format.
     *
     * @param int $courseid Course ID or zero.
     * @param bool $unread Whether to filter unread messages.
     * @param string $cursor Cursor string.
     * @return array
     */
    private function call_feed(int $courseid = 0, bool $unread = false, string $cursor = ''): array {
        $result = get_recent_feed::execute($courseid, $unread, $cursor);
        return external_api::clean_returnvalue(get_recent_feed::execute_returns(), $result);
    }

    public function test_includes_accessible_courses_even_if_forum_is_opted_out_of_my_courses(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $othercourse = $generator->create_course();
        $generator->enrol_user($student->id, $othercourse->id);
        $fg = $generator->get_plugin_generator('mod_forum');

        $first = $generator->create_module('forum', [
            'course' => $course->id, 'showinmycourses' => 0,
        ]);
        $second = $generator->create_module('forum', [
            'course' => $othercourse->id, 'showinmycourses' => 1,
        ]);
        $fg->create_discussion((object)[
            'forum' => $first->id, 'course' => $course->id, 'userid' => $student->id,
            'name' => 'First course topic',
        ]);
        $fg->create_discussion((object)[
            'forum' => $second->id, 'course' => $othercourse->id, 'userid' => $student->id,
            'name' => 'Second course topic',
        ]);

        $all = $this->call_feed();
        $this->assertCount(2, $all['items']);
        $ids = array_column($all['items'], 'courseid');
        $this->assertContains($course->id, $ids);
        $this->assertContains($othercourse->id, $ids);

        $filtered = $this->call_feed($course->id);
        $this->assertCount(1, $filtered['items']);
        $this->assertSame($course->id, $filtered['items'][0]['courseid']);
    }

    public function test_does_not_return_posts_from_courses_outside_enrolment_or_viewing_role(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $othercourse = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $othercourse->id]);
        $author = $generator->create_user();
        $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $othercourse->id, 'userid' => $author->id,
        ]);

        $this->assertEmpty($this->call_feed()['items']);
        $this->assertEmpty($this->call_feed($othercourse->id)['items']);
    }

    public function test_cursor_returns_each_discussion_only_once_across_pages(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $fg = $generator->get_plugin_generator('mod_forum');

        for ($i = 0; $i < 25; $i++) {
            $fg->create_discussion((object)[
                'forum' => $forum->id,
                'course' => $course->id,
                'userid' => $student->id,
                'name' => 'Discussion ' . $i,
            ]);
        }

        $first = $this->call_feed();
        $this->assertCount(20, $first['items']);
        $this->assertTrue($first['hasmore']);
        $this->assertNotEmpty($first['nextcursor']);
        $next = $this->call_feed(0, false, $first['nextcursor']);
        $this->assertCount(5, $next['items']);
        $this->assertFalse($next['hasmore']);
        $allids = array_merge(
            array_column($first['items'], 'discussionid'),
            array_column($next['items'], 'discussionid'));
        $this->assertCount(25, array_unique($allids));
    }

    public function test_later_replies_keep_one_entry_at_the_latest_visible_post(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $fg = $generator->get_plugin_generator('mod_forum');
        $discussion = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
        ]);
        $reply = $fg->create_post((object)[
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost,
            'userid' => $student->id, 'message' => '<p>Public excerpt</p>',
        ]);
        $page = $this->call_feed();
        $this->assertCount(1, $page['items']);
        $this->assertSame($reply->id, $page['items'][0]['postid']);
        $this->assertStringContainsString('targetpost=' . $reply->id, $page['items'][0]['url']);
        $this->assertStringContainsString('#p' . $reply->id, $page['items'][0]['url']);
        $this->assertStringContainsString('Public excerpt', $page['items'][0]['preview']);
    }

    public function test_unread_filter_uses_native_read_tracking(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $author = $generator->create_user();
        $generator->enrol_user($author->id, $course->id);
        $forum = $generator->create_module('forum', [
            'course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED,
        ]);
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
        ]);

        $unread = $this->call_feed(0, true);
        $this->assertCount(1, $unread['items']);
        $this->assertTrue($unread['items'][0]['unread']);

        forum_tp_add_read_record($student->id, $discussion->firstpost);
        $this->assertEmpty($this->call_feed(0, true)['items']);
        $this->assertCount(1, $this->call_feed()['items']);
    }

    public function test_qanda_hides_replies_before_student_posts(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $author = $generator->create_user();
        $generator->enrol_user($author->id, $course->id);
        $forum = $generator->create_module('forum', [
            'course' => $course->id, 'type' => 'qanda',
        ]);
        $fg = $generator->get_plugin_generator('mod_forum');
        $discussion = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
        ]);
        $fg->create_post((object)[
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost,
            'userid' => $author->id, 'message' => '<p>Hidden answer</p>',
        ]);

        $page = $this->call_feed();
        $this->assertCount(1, $page['items']);
        $this->assertSame($discussion->firstpost, $page['items'][0]['postid']);
        $this->assertStringNotContainsString('Hidden answer', $page['items'][0]['preview']);
    }

    public function test_private_reply_is_not_leaked_to_other_students(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $author = $generator->create_user();
        $recipient = $generator->create_user();
        $generator->enrol_user($author->id, $course->id);
        $generator->enrol_user($recipient->id, $course->id);
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $fg = $generator->get_plugin_generator('mod_forum');
        $discussion = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
        ]);
        $fg->create_post((object)[
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost,
            'userid' => $author->id, 'privatereplyto' => $recipient->id,
            'message' => '<p>Private material</p>',
        ]);
        $page = $this->call_feed();
        $this->assertCount(1, $page['items']);
        $this->assertSame($discussion->firstpost, $page['items'][0]['postid']);
        $this->assertStringNotContainsString('Private material', $page['items'][0]['preview']);
    }

    public function test_separate_groups_are_enforced(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $author = $generator->create_user();
        $generator->enrol_user($author->id, $course->id);
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $author->id]);
        $forum = $generator->create_module('forum', [
            'course' => $course->id, 'groupmode' => SEPARATEGROUPS,
        ]);
        $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
            'groupid' => $group->id,
        ]);
        $this->assertEmpty($this->call_feed()['items']);
    }

    public function test_invalid_or_wrong_filter_cursor_is_rejected(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $fg = $generator->get_plugin_generator('mod_forum');
        for ($i = 0; $i < 21; $i++) {
            $fg->create_discussion((object)[
                'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
            ]);
        }

        $first = $this->call_feed();
        $this->assertNotEmpty($first['nextcursor']);
        $this->expectException(\invalid_parameter_exception::class);
        $this->call_feed(0, true, $first['nextcursor']);
    }

    public function test_untracked_forums_are_not_treated_as_unread(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $author = $generator->create_user();
        $generator->enrol_user($author->id, $course->id);
        $forum = $generator->create_module('forum', [
            'course' => $course->id, 'trackingtype' => FORUM_TRACKING_OFF,
        ]);
        $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
        ]);

        $this->assertCount(1, $this->call_feed()['items']);
        $this->assertEmpty($this->call_feed(0, true)['items']);
    }

    public function test_future_discussion_is_not_disclosed(): void {
        global $CFG;
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $CFG->forum_enabletimedposts = 1;
        $author = $generator->create_user();
        $generator->enrol_user($author->id, $course->id);
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
            'timestart' => time() + DAYSECS, 'timeend' => 0,
        ]);
        $this->assertEmpty($this->call_feed()['items']);
    }
}
