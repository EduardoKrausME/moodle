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

/**
 * Regression tests for the course summary endpoint.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\external\get_course_recent_discussions::class)]
final class get_course_recent_discussions_test extends \core_external\tests\externallib_testcase {
    public function test_opted_out_forums_are_not_returned(): void {
        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $generator->enrol_user($user->id, $course->id);
        $enabled = $generator->create_module('forum', [
            'course' => $course->id,
            'showinmycourses' => 1,
        ]);
        $disabled = $generator->create_module('forum', [
            'course' => $course->id,
            'showinmycourses' => 0,
        ]);
        $fg = $generator->get_plugin_generator('mod_forum');
        $discussion = $fg->create_discussion((object)[
            'forum' => $enabled->id,
            'course' => $course->id,
            'userid' => $user->id,
        ]);
        $reply = $fg->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $user->id,
        ]);
        $fg->create_discussion((object)[
            'forum' => $disabled->id,
            'course' => $course->id,
            'userid' => $user->id,
        ]);

        $this->setUser($user);
        $result = get_course_recent_discussions::execute($course->id);
        $result = \core_external\external_api::clean_returnvalue(
            get_course_recent_discussions::execute_returns(), $result);

        $this->assertCount(1, $result['discussions']);
        $this->assertSame($reply->id, $result['discussions'][0]['postid']);
        $this->assertStringContainsString('#p' . $reply->id, $result['discussions'][0]['url']);
    }

    public function test_hidden_forum_is_not_returned_to_student(): void {
        $this->resetAfterTest();

        $generator = self::getDataGenerator();
        $user = $generator->create_user();
        $course = $generator->create_course();
        $generator->enrol_user($user->id, $course->id);
        $forum = $generator->create_module('forum', [
            'course' => $course->id,
            'showinmycourses' => 1,
            'visible' => 0,
        ]);
        $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id,
            'course' => $course->id,
            'userid' => $user->id,
        ]);

        $this->setUser($user);
        $this->assertEmpty(get_course_recent_discussions::execute($course->id)['discussions']);
    }
}
