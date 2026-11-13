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

use mod_forum\external\get_solution_state;
use mod_forum\external\set_accepted_solution;

/**
 * Accepted-answer integration and access regression tests.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\local\solution_manager::class)]
final class solution_manager_test extends \advanced_testcase {
    /**
     * @param int $enabled Whether the forum supports accepted replies.
     * @return array The author, another learner, course, forum, discussion, reply and generator.
     */
    private function fixture(int $enabled = 1): array {
        $generator = self::getDataGenerator();
        $author = $generator->create_user();
        $student = $generator->create_user();
        $course = $generator->create_course();
        $generator->enrol_user($author->id, $course->id);
        $generator->enrol_user($student->id, $course->id);
        $forum = $generator->create_module('forum', [
            'course' => $course->id,
            'allowsolutions' => $enabled,
        ]);
        $fg = $generator->get_plugin_generator('mod_forum');
        $discussion = $fg->create_discussion((object)[
            'forum' => $forum->id,
            'course' => $course->id,
            'userid' => $author->id,
            'name' => 'How to solve this issue?',
        ]);
        $post = $fg->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $student->id,
            'message' => '<p>Verified solution</p>',
        ]);
        $this->setUser($author);
        return [$author, $student, $course, $forum, $discussion, $post, $generator];
    }

    public function test_one_solution_per_discussion_is_enforced_by_unique_index(): void {
        global $DB;

        $this->resetAfterTest();
        // Moodle's index_exists() checks only the field list, not uniqueness.
        $unique = false;
        foreach ($DB->get_indexes('forum_solutions') as $index) {
            if (!empty($index['unique']) && array_values($index['columns']) === ['discussionid']) {
                $unique = true;
                break;
            }
        }
        $this->assertTrue($unique, 'An actual unique database index must protect discussionid.');
    }

    public function test_existing_forums_remain_opted_out(): void {
        $this->resetAfterTest();
        [, , , , $discussion, $post] = $this->fixture(0);
        $state = solution_manager::get_state((int)$discussion->id);
        $this->assertFalse($state['enabled']);
        $this->assertFalse($state['canmanage']);
        $this->assertSame(0, $state['postid']);
        $this->expectException(\required_capability_exception::class);
        solution_manager::set_solution((int)$discussion->id, (int)$post->id);
    }

    public function test_starter_can_accept_replace_and_remove_with_one_row(): void {
        global $DB;

        $this->resetAfterTest();
        [, $student, $course, $forum, $discussion, $post, $generator] = $this->fixture();
        $first = set_accepted_solution::execute($discussion->id, $post->id);
        $this->assertTrue($first['enabled']);
        $this->assertTrue($first['canmanage']);
        $this->assertSame((int)$post->id, $first['postid']);
        $this->assertStringContainsString('targetpost=' . $post->id, $first['url']);
        $this->assertSame(1, $DB->count_records('forum_solutions', ['discussionid' => $discussion->id]));

        $idempotent = set_accepted_solution::execute($discussion->id, $post->id);
        $this->assertSame($first['postid'], $idempotent['postid']);
        $this->assertSame(1, $DB->count_records('forum_solutions', ['discussionid' => $discussion->id]));

        $another = $generator->get_plugin_generator('mod_forum')->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $student->id,
            'message' => '<p>Alternative solution</p>',
        ]);
        $replaced = set_accepted_solution::execute($discussion->id, $another->id);
        $this->assertSame((int)$another->id, $replaced['postid']);
        $this->assertSame(1, $DB->count_records('forum_solutions', ['discussionid' => $discussion->id]));
        $this->assertSame((int)$another->id,
            (int)$DB->get_field('forum_solutions', 'postid', ['discussionid' => $discussion->id]));

        $removed = set_accepted_solution::execute($discussion->id, 0);
        $this->assertSame(0, $removed['postid']);
        $this->assertSame('', $removed['url']);
        $this->assertFalse($DB->record_exists('forum_solutions', ['discussionid' => $discussion->id]));
    }

    public function test_first_post_cannot_be_selected_as_solution(): void {
        $this->resetAfterTest();
        [, , , , $discussion] = $this->fixture();
        $this->expectException(\invalid_parameter_exception::class);
        solution_manager::set_solution($discussion->id, $discussion->firstpost);
    }

    public function test_reply_from_another_discussion_is_rejected(): void {
        $this->resetAfterTest();
        [$author, $student, $course, $forum, $discussion, , $generator] = $this->fixture();
        $fg = $generator->get_plugin_generator('mod_forum');
        $other = $fg->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id, 'userid' => $author->id,
        ]);
        $reply = $fg->create_post((object)[
            'discussion' => $other->id,
            'parent' => $other->firstpost,
            'userid' => $student->id,
        ]);
        $this->expectException(\invalid_parameter_exception::class);
        solution_manager::set_solution($discussion->id, $reply->id);
    }

    public function test_another_student_cannot_manage_someone_elses_discussion(): void {
        $this->resetAfterTest();
        [, $student, , , $discussion, $post] = $this->fixture();
        $this->setUser($student);
        $this->assertFalse(solution_manager::get_state($discussion->id)['canmanage']);
        $this->expectException(\required_capability_exception::class);
        set_accepted_solution::execute($discussion->id, $post->id);
    }

    public function test_teacher_can_manage_a_visible_solution(): void {
        $this->resetAfterTest();
        [, , $course, , $discussion, $post, $generator] = $this->fixture();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $state = set_accepted_solution::execute($discussion->id, $post->id);
        $this->assertSame((int)$post->id, $state['postid']);
        $this->assertTrue($state['canmanage']);
    }

    public function test_outsider_cannot_get_solution_state(): void {
        $this->resetAfterTest();
        [, , , , $discussion, , $generator] = $this->fixture();
        $outsider = $generator->create_user();
        $this->setUser($outsider);
        $this->expectException(\required_capability_exception::class);
        get_solution_state::execute($discussion->id);
    }

    public function test_private_reply_cannot_be_accepted(): void {
        $this->resetAfterTest();
        [$author, $student, $course, $forum, $discussion, , $generator] = $this->fixture();
        $private = $generator->get_plugin_generator('mod_forum')->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $author->id,
            'privatereplyto' => $student->id,
            'message' => 'Private message',
        ]);
        $this->expectException(\invalid_parameter_exception::class);
        solution_manager::set_solution($discussion->id, $private->id);
    }

    public function test_deleting_accepted_post_removes_orphaned_solution(): void {
        global $DB, $CFG;

        $this->resetAfterTest();
        [, , $course, $forum, $discussion, $post] = $this->fixture();
        solution_manager::set_solution($discussion->id, $post->id);
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        $this->assertTrue(forum_delete_post($post, true, $course, $cm, $forum));
        $this->assertFalse($DB->record_exists('forum_solutions', ['discussionid' => $discussion->id]));
    }

    public function test_feed_reports_only_visible_accepted_post(): void {
        $this->resetAfterTest();
        [$author, , , , $discussion, $post] = $this->fixture();
        solution_manager::set_solution($discussion->id, $post->id);
        $page = \mod_forum\local\recent_feed::get_page();
        $this->assertNotEmpty($page['items']);
        $item = reset($page['items']);
        $this->assertSame((int)$post->id, $item['solutionpostid']);
        $this->assertTrue($item['solved']);
        $this->assertStringContainsString('targetpost=' . $post->id, $item['solutionurl']);
    }

    public function test_decision_does_not_create_extra_post(): void {
        global $DB;

        $this->resetAfterTest();
        [, , , , $discussion, $post] = $this->fixture();
        $count = $DB->count_records('forum_posts', ['discussion' => $discussion->id]);
        solution_manager::set_solution($discussion->id, $post->id);
        $this->assertSame($count, $DB->count_records('forum_posts', ['discussion' => $discussion->id]));
    }
}
