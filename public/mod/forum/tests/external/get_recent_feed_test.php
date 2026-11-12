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
    private function call_feed(int $courseid = 0, bool $unread = false, string $cursor = '',
            bool $following = false): array {
        $result = get_recent_feed::execute($courseid, $unread, $cursor, $following);
        return external_api::clean_returnvalue(get_recent_feed::execute_returns(), $result);
    }

    /**
     * Star a discussion using the same core_favourites service as the native forum.
     *
     * @param \stdClass $discussion The discussion to star.
     * @param \stdClass $forum Native forum activity.
     * @param \stdClass $course Owning course.
     * @param \stdClass $user User owning this star.
     */
    private function star_discussion(\stdClass $discussion, \stdClass $forum,
            \stdClass $course, \stdClass $user): void {
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $service = \core_favourites\service_factory::get_service_for_user_context(
            \context_user::instance($user->id));
        $service->create_favourite('mod_forum', 'discussions', $discussion->id, $context);
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
        $this->assertContains((int)$course->id, $ids);
        $this->assertContains((int)$othercourse->id, $ids);

        $filtered = $this->call_feed($course->id);
        $this->assertCount(1, $filtered['items']);
        $this->assertSame((int)$course->id, $filtered['items'][0]['courseid']);
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
        // A hidden Q&A reply must never make a fully read discussion look unread.
        forum_tp_add_read_record($student->id, $discussion->firstpost);
        $this->assertFalse($this->call_feed()['items'][0]['unread']);
        $this->assertEmpty($this->call_feed(0, true)['items']);
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
        // Unread private replies to others must not affect the visible badge.
        forum_tp_add_read_record($student->id, $discussion->firstpost);
        $this->assertFalse($this->call_feed()['items'][0]['unread']);
        $this->assertEmpty($this->call_feed(0, true)['items']);
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

    public function test_recent_filter_orders_topics_by_latest_visible_reply(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $fg = $generator->get_plugin_generator('mod_forum');
        $older = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
            'timecreated' => time() - 600, 'name' => 'Older topic with a new reply',
        ]);
        $newer = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
            'timecreated' => time() - 300, 'name' => 'More recently created topic',
        ]);
        $reply = $fg->create_post((object)[
            'discussion' => $older->id, 'parent' => $older->firstpost,
            'userid' => $student->id, 'created' => time(), 'modified' => time(),
        ]);

        $page = $this->call_feed();
        $this->assertSame($older->id, $page['items'][0]['discussionid']);
        $this->assertSame($reply->id, $page['items'][0]['postid']);
        $this->assertSame($newer->id, $page['items'][1]['discussionid']);
    }

    public function test_recent_filter_marks_topic_unread_when_earlier_visible_post_is_unread(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $author = $generator->create_user();
        $generator->enrol_user($author->id, $course->id);
        $forum = $generator->create_module('forum', [
            'course' => $course->id, 'trackingtype' => FORUM_TRACKING_FORCED,
        ]);
        $fg = $generator->get_plugin_generator('mod_forum');
        $discussion = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
        ]);
        $reply = $fg->create_post((object)[
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost,
            'userid' => $student->id, 'created' => time() + 1, 'modified' => time() + 1,
        ]);
        forum_tp_add_read_record($student->id, $reply->id);

        $recent = $this->call_feed();
        $this->assertSame($reply->id, $recent['items'][0]['postid']);
        $this->assertTrue($recent['items'][0]['unread']);

        $unread = $this->call_feed(0, true);
        $this->assertCount(1, $unread['items']);
        $this->assertSame($discussion->firstpost, $unread['items'][0]['postid']);

        forum_tp_add_read_record($student->id, $discussion->firstpost);
        $this->assertFalse($this->call_feed()['items'][0]['unread']);
        $this->assertEmpty($this->call_feed(0, true)['items']);
    }

    public function test_cursor_cannot_be_reused_for_another_course_filter(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $fg = $generator->get_plugin_generator('mod_forum');
        for ($i = 0; $i < 21; $i++) {
            $fg->create_discussion((object)[
                'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
            ]);
        }
        $page = $this->call_feed(0);
        $this->assertNotEmpty($page['nextcursor']);
        $this->expectException(\invalid_parameter_exception::class);
        $this->call_feed($course->id, false, $page['nextcursor']);
    }

    public function test_following_uses_native_stars_without_changing_recent_feed(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $fg = $generator->get_plugin_generator('mod_forum');
        $selected = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
            'name' => 'Starred topic',
        ]);
        $ordinary = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
            'name' => 'Ordinary topic',
        ]);
        $this->star_discussion($selected, $forum, $course, $student);

        $all = $this->call_feed();
        $this->assertCount(2, $all['items']);
        $byid = array_column($all['items'], null, 'discussionid');
        $this->assertTrue($byid[$selected->id]['favourited']);
        $this->assertFalse($byid[$ordinary->id]['favourited']);
        $this->assertSame(
            has_capability('mod/forum:cantogglefavourite',
                \context_module::instance(
                get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST)->id), $student),
            $byid[$selected->id]['canfavourite']);

        $following = $this->call_feed(0, false, '', true);
        $this->assertCount(1, $following['items']);
        $this->assertSame($selected->id, $following['items'][0]['discussionid']);
        $this->assertTrue($following['items'][0]['favourited']);

        // Favourites and forum mail subscriptions have separate storage.
        $this->assertCount(2, $this->call_feed()['items']);
    }

    public function test_following_is_private_to_the_authenticated_user(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $other = $generator->create_user();
        $generator->enrol_user($other->id, $course->id);
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
        ]);

        $this->star_discussion($discussion, $forum, $course, $other);
        $this->assertEmpty($this->call_feed(0, false, '', true)['items']);

        $this->setUser($other);
        $page = $this->call_feed(0, false, '', true);
        $this->assertCount(1, $page['items']);
        $this->assertTrue($page['items'][0]['favourited']);
    }

    public function test_following_respects_course_filter_and_star_removal(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $othercourse = $generator->create_course();
        $generator->enrol_user($student->id, $othercourse->id);
        $fg = $generator->get_plugin_generator('mod_forum');
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $otherforum = $generator->create_module('forum', ['course' => $othercourse->id]);
        $a = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
        ]);
        $b = $fg->create_discussion((object)[
            'forum' => $otherforum->id, 'course' => $othercourse->id, 'userid' => $student->id,
        ]);
        $this->star_discussion($a, $forum, $course, $student);
        $this->star_discussion($b, $otherforum, $othercourse, $student);
        $this->assertCount(2, $this->call_feed(0, false, '', true)['items']);
        $first = $this->call_feed($course->id, false, '', true);
        $this->assertCount(1, $first['items']);
        $this->assertSame($a->id, $first['items'][0]['discussionid']);

        $context = \context_module::instance(
                get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST)->id);
        $service = \core_favourites\service_factory::get_service_for_user_context(
            \context_user::instance($student->id));
        $service->delete_favourite('mod_forum', 'discussions', $a->id, $context);
        $this->assertEmpty($this->call_feed($course->id, false, '', true)['items']);
        $this->assertCount(1, $this->call_feed(0, false, '', true)['items']);
    }

    public function test_following_cursor_is_scoped_to_the_selected_filter(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $fg = $generator->get_plugin_generator('mod_forum');
        for ($i = 0; $i < 23; $i++) {
            $discussion = $fg->create_discussion((object)[
                'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
            ]);
            $this->star_discussion($discussion, $forum, $course, $student);
        }

        $first = $this->call_feed(0, false, '', true);
        $this->assertCount(20, $first['items']);
        $this->assertTrue($first['hasmore']);
        $this->assertNotEmpty($first['nextcursor']);
        $second = $this->call_feed(0, false, $first['nextcursor'], true);
        $this->assertCount(3, $second['items']);
        $this->assertFalse($second['hasmore']);
        $ids = array_merge(array_column($first['items'], 'discussionid'),
            array_column($second['items'], 'discussionid'));
        $this->assertCount(23, array_unique($ids));

        $this->expectException(\invalid_parameter_exception::class);
        $this->call_feed(0, false, $first['nextcursor'], false);
    }

    public function test_recent_cursor_cannot_be_used_with_following(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $forum = $generator->create_module('forum', ['course' => $course->id]);
        $fg = $generator->get_plugin_generator('mod_forum');
        for ($i = 0; $i < 21; $i++) {
            $fg->create_discussion((object)[
                'forum' => $forum->id, 'course' => $course->id, 'userid' => $student->id,
            ]);
        }
        $page = $this->call_feed();
        $this->assertNotEmpty($page['nextcursor']);
        $this->expectException(\invalid_parameter_exception::class);
        $this->call_feed(0, false, $page['nextcursor'], true);
    }

    public function test_following_rejects_conflicting_unread_filter(): void {
        $this->resetAfterTest();
        $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        $this->call_feed(0, true, '', true);
    }

    public function test_following_does_not_bypass_course_access(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $privatecourse = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $privatecourse->id]);
        $author = $generator->create_user();
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $privatecourse->id, 'userid' => $author->id,
        ]);
        // Even an orphaned/stale favourite must not bypass course enrolment.
        $this->star_discussion($discussion, $forum, $privatecourse, $student);
        $this->assertEmpty($this->call_feed(0, false, '', true)['items']);
        $this->assertEmpty($this->call_feed($privatecourse->id, false, '', true)['items']);
    }

    public function test_following_does_not_disclose_separate_group_discussions(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $author = $generator->create_user();
        $generator->enrol_user($author->id, $course->id);
        $group = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group->id, 'userid' => $author->id]);
        $forum = $generator->create_module('forum', [
            'course' => $course->id, 'groupmode' => SEPARATEGROUPS,
        ]);
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id,
            'userid' => $author->id, 'groupid' => $group->id,
        ]);
        $this->star_discussion($discussion, $forum, $course, $student);
        $this->assertEmpty($this->call_feed(0, false, '', true)['items']);
    }

    public function test_following_respects_qanda_and_private_post_visibility(): void {
        $this->resetAfterTest();
        [$student, $course, $generator] = $this->fixture();
        $author = $generator->create_user();
        $recipient = $generator->create_user();
        $generator->enrol_user($author->id, $course->id);
        $generator->enrol_user($recipient->id, $course->id);
        $forum = $generator->create_module('forum', [
            'course' => $course->id, 'type' => 'qanda',
        ]);
        $fg = $generator->get_plugin_generator('mod_forum');
        $discussion = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id,
            'userid' => $author->id, 'name' => 'Public question',
        ]);
        $fg->create_post((object)[
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost,
            'userid' => $author->id, 'message' => '<p>Hidden teacher answer</p>',
        ]);
        $fg->create_post((object)[
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost,
            'userid' => $author->id, 'privatereplyto' => $recipient->id,
            'message' => '<p>Hidden private answer</p>',
        ]);
        $this->star_discussion($discussion, $forum, $course, $student);
        $page = $this->call_feed(0, false, '', true);
        $this->assertCount(1, $page['items']);
        $this->assertSame($discussion->firstpost, $page['items'][0]['postid']);
        $this->assertStringNotContainsString('Hidden teacher answer', $page['items'][0]['preview']);
        $this->assertStringNotContainsString('Hidden private answer', $page['items'][0]['preview']);
    }
}
