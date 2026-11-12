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
 * Tests for course-level unread forum message totals.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\external\get_course_unread_counts::class)]
final class get_course_unread_counts_test extends \core_external\tests\externallib_testcase {
    /**
     * Create an enrolled student with forum tracking enabled.
     *
     * @return array User and course.
     */
    private function fixture(): array {
        global $CFG;
        $CFG->forum_trackreadposts = 1;
        $CFG->forum_allowforcedreadtracking = 1;
        $generator = self::getDataGenerator();
        $user = $generator->create_user(['trackforums' => 1]);
        $course = $generator->create_course();
        $generator->enrol_user($user->id, $course->id);
        $this->setUser($user);
        return [$user, $course];
    }

    /**
     * @param int $courseid Course ID.
     * @return array Service entry.
     */
    private function get_result(int $courseid): array {
        $output = get_course_unread_counts::execute([$courseid]);
        $output = external_api::clean_returnvalue(get_course_unread_counts::execute_returns(), $output);
        return $output['courses'][0];
    }

    public function test_only_opted_in_unread_messages_are_counted(): void {
        $this->resetAfterTest();
        [$user, $course] = $this->fixture();
        $generator = self::getDataGenerator();
        $enabled = $generator->create_module('forum', [
            'course' => $course->id,
            'showinmycourses' => 1,
            'trackingtype' => FORUM_TRACKING_FORCED,
        ]);
        $disabled = $generator->create_module('forum', [
            'course' => $course->id,
            'showinmycourses' => 0,
            'trackingtype' => FORUM_TRACKING_FORCED,
        ]);
        $forumgenerator = $generator->get_plugin_generator('mod_forum');
        $discussion = $forumgenerator->create_discussion((object)[
            'course' => $course->id,
            'forum' => $enabled->id,
            'userid' => $user->id,
        ]);
        $reply = $forumgenerator->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $user->id,
        ]);
        $forumgenerator->create_discussion((object)[
            'course' => $course->id,
            'forum' => $disabled->id,
            'userid' => $user->id,
        ]);

        $before = $this->get_result($course->id);
        $this->assertSame('unread', $before['state']);
        $this->assertSame(2, $before['count']);
        $this->assertSame(1, $before['forumcount']);

        forum_tp_add_read_record($user->id, $reply->id);
        $after = $this->get_result($course->id);
        $this->assertSame('unread', $after['state']);
        $this->assertSame(1, $after['count']);
    }

    public function test_partial_count_when_another_forum_is_untracked(): void {
        $this->resetAfterTest();
        [$user, $course] = $this->fixture();
        $generator = self::getDataGenerator();
        $tracked = $generator->create_module('forum', [
            'course' => $course->id,
            'showinmycourses' => 1,
            'trackingtype' => FORUM_TRACKING_FORCED,
        ]);
        $generator->create_module('forum', [
            'course' => $course->id,
            'showinmycourses' => 1,
            'trackingtype' => FORUM_TRACKING_OFF,
        ]);
        $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'course' => $course->id,
            'forum' => $tracked->id,
            'userid' => $user->id,
        ]);

        $result = $this->get_result($course->id);
        $this->assertSame(1, $result['count']);
        $this->assertSame('partial', $result['state']);
    }

    public function test_untracked_forum_returns_non_numeric_status(): void {
        $this->resetAfterTest();
        [$user, $course] = $this->fixture();
        self::getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'showinmycourses' => 1,
            'trackingtype' => FORUM_TRACKING_OFF,
        ]);

        $result = $this->get_result($course->id);
        $this->assertSame(0, $result['count']);
        $this->assertSame('activity', $result['state']);
    }

    public function test_hidden_forums_do_not_contribute_to_count(): void {
        $this->resetAfterTest();
        [$user, $course] = $this->fixture();
        $generator = self::getDataGenerator();
        $forum = $generator->create_module('forum', [
            'course' => $course->id,
            'showinmycourses' => 1,
            'trackingtype' => FORUM_TRACKING_FORCED,
            'visible' => 0,
        ]);
        $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $user->id,
        ]);
        $this->assertSame('none', $this->get_result($course->id)['state']);
    }

    public function test_other_users_courses_are_not_returned(): void {
        $this->resetAfterTest();
        [$user, $course] = $this->fixture();
        $othercourse = self::getDataGenerator()->create_course();
        $output = get_course_unread_counts::execute([$course->id, $othercourse->id]);
        $output = external_api::clean_returnvalue(get_course_unread_counts::execute_returns(), $output);
        $this->assertCount(1, $output['courses']);
        $this->assertSame($course->id, $output['courses'][0]['courseid']);
    }
}
