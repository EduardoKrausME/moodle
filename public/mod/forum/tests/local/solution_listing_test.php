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

/**
 * Visibility and pre-pagination regression tests for accepted solutions.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\local\solution_listing::class)]
final class solution_listing_test extends \advanced_testcase {
    /**
     * @return array Teacher, discussion author, answer author, course, forum, forum entity.
     */
    private function fixture(string $type = 'general'): array {
        $generator = self::getDataGenerator();
        $teacher = $generator->create_user();
        $author = $generator->create_user();
        $replier = $generator->create_user();
        $course = $generator->create_course();
        foreach ([$teacher, $author, $replier] as $user) {
            $role = $user->id === $teacher->id ? 'editingteacher' : 'student';
            $generator->enrol_user($user->id, $course->id, $role);
        }
        $forum = $generator->create_module('forum', [
            'course' => $course->id,
            'allowsolutions' => 1,
            'type' => $type,
        ]);
        $entity = container::get_vault_factory()->get_forum_vault()->get_from_id($forum->id);
        $this->setUser($teacher);
        return [$teacher, $author, $replier, $course, $forum, $entity];
    }

    /**
     * @param \stdClass $author Starter.
     * @param \stdClass $forum Module.
     * @param \stdClass $course Course.
     * @param string $name Name.
     * @return \stdClass Native discussion.
     */
    private function discussion(\stdClass $author, \stdClass $forum,
            \stdClass $course, string $name): \stdClass {
        return self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion((object)[
            'forum' => $forum->id, 'course' => $course->id,
            'userid' => $author->id, 'name' => $name,
        ]);
    }

    private function reply(\stdClass $author, \stdClass $discussion): \stdClass {
        return self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post((object)[
            'discussion' => $discussion->id, 'parent' => $discussion->firstpost,
            'userid' => $author->id, 'message' => 'Accepted answer',
        ]);
    }

    public function test_native_filters_are_applied_before_pagination(): void {
        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum, $entity] = $this->fixture();
        $all = [];
        for ($i = 0; $i < 14; $i++) {
            $discussion = $this->discussion($author, $forum, $course, 'Topic ' . $i);
            $all[] = $discussion;
            if ($i % 2 === 0) {
                $reply = $this->reply($replier, $discussion);
                solution_manager::set_solution($discussion->id, $reply->id);
            }
        }
        $first = solution_listing::get_filtered_page($entity, $teacher, null, null, 0, 3, 'accepted');
        $next = solution_listing::get_filtered_page($entity, $teacher, null, null, 1, 3, 'accepted');
        $last = solution_listing::get_filtered_page($entity, $teacher, null, null, 2, 3, 'accepted');
        $this->assertSame(7, $first['total']);
        $this->assertSame(7, $next['total']);
        $this->assertCount(3, $first['discussions']);
        $this->assertCount(3, $next['discussions']);
        $this->assertCount(1, $last['discussions']);

        $ids = [];
        foreach ([$first, $next, $last] as $page) {
            foreach ($page['discussions'] as $summary) {
                $ids[] = $summary->get_discussion()->get_id();
            }
        }
        $this->assertCount(7, array_unique($ids));
        $remaining = solution_listing::get_filtered_page($entity, $teacher, null, null, 0, 20, 'unaccepted');
        $this->assertSame(7, $remaining['total']);
        $this->assertCount(7, $remaining['discussions']);
    }

    public function test_qanda_answer_is_not_exposed_to_a_student_without_visibility(): void {
        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum, $entity] = $this->fixture('qanda');
        $discussion = $this->discussion($author, $forum, $course, 'Q and A');
        $post = $this->reply($replier, $discussion);
        solution_manager::set_solution($discussion->id, $post->id);
        $summaries = mod_forum_get_discussion_summaries($entity, $teacher, null, null, 0, 10);
        $this->assertArrayHasKey($discussion->id, solution_listing::visible_for_summaries(
            $entity, $teacher, $summaries));

        $student = self::getDataGenerator()->create_user();
        self::getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);
        $summaries = mod_forum_get_discussion_summaries($entity, $student, null, null, 0, 10);
        $this->assertArrayNotHasKey($discussion->id, solution_listing::visible_for_summaries(
            $entity, $student, $summaries));
        $filtered = solution_listing::get_filtered_page($entity, $student, null, null, 0, 10, 'accepted');
        $this->assertSame(0, $filtered['total']);
        $filtered = solution_listing::get_filtered_page($entity, $student, null, null, 0, 10, 'unaccepted');
        $this->assertSame(1, $filtered['total']);
    }

    public function test_deleted_accepted_reply_is_never_announced(): void {
        global $DB;

        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum, $entity] = $this->fixture();
        $discussion = $this->discussion($author, $forum, $course, 'Answer deleted');
        $post = $this->reply($replier, $discussion);
        solution_manager::set_solution($discussion->id, $post->id);
        $DB->set_field('forum_posts', 'deleted', 1, ['id' => $post->id]);
        $summaries = mod_forum_get_discussion_summaries($entity, $teacher, null, null, 0, 10);
        $this->assertArrayNotHasKey($discussion->id, solution_listing::visible_for_summaries(
            $entity, $teacher, $summaries));
        $this->assertSame(0, solution_listing::get_filtered_page(
            $entity, $teacher, null, null, 0, 10, 'accepted')['total']);
        $this->assertSame(1, solution_listing::get_filtered_page(
            $entity, $teacher, null, null, 0, 10, 'unaccepted')['total']);
    }

    public function test_hidden_or_disabled_forum_does_not_expose_solution_state(): void {
        $this->resetAfterTest();
        [$teacher, $author, $replier, $course, $forum, $entity] = $this->fixture();
        $discussion = $this->discussion($author, $forum, $course, 'Hidden discussion');
        $post = $this->reply($replier, $discussion);
        solution_manager::set_solution($discussion->id, $post->id);
        $outsider = self::getDataGenerator()->create_user();
        $this->setUser($outsider);
        $summaries = [];
        $this->assertSame([], solution_listing::visible_for_summaries($entity, $outsider, $summaries));
        $this->expectException(\invalid_parameter_exception::class);
        solution_listing::get_filtered_page($entity, $outsider, null, null, 0, 10, 'unknown');
    }
}
