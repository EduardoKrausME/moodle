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
use mod_forum\local\feed_reply;

/**
 * Native posting, contextual citations and permission-scoped mention tests.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\external\reply_from_feed::class)]
final class feed_reply_test extends \core_external\tests\externallib_testcase {
    /**
     * Set up an enrolled learner and a discussion written by a different user.
     *
     * @param array $forumoptions Optional forum configuration.
     * @return array Student, author, course, forum, discussion and generator.
     */
    private function fixture(array $forumoptions = []): array {
        $generator = self::getDataGenerator();
        $student = $generator->create_user();
        $author = $generator->create_user([
            'firstname' => 'Alice',
            'lastname' => 'Visible',
        ]);
        $course = $generator->create_course();
        $generator->enrol_user($student->id, $course->id);
        $generator->enrol_user($author->id, $course->id);
        $forum = $generator->create_module('forum', array_merge(['course' => $course->id], $forumoptions));
        $discussion = $generator->get_plugin_generator('mod_forum')->create_discussion((object)[
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $author->id,
            'name' => 'Safe discussion',
            'message' => '<p>Visible source message</p>',
        ]);
        $this->setUser($student);
        return [$student, $author, $course, $forum, $discussion, $generator];
    }

    public function test_context_returns_plain_visible_quote_and_native_reply_permission(): void {
        $this->resetAfterTest();
        [, $author, , , $discussion] = $this->fixture();
        $result = get_feed_reply_context::execute($discussion->firstpost);
        $response = external_api::clean_returnvalue(get_feed_reply_context::execute_returns(), $result);

        $this->assertSame((int)$discussion->firstpost, $response['postid']);
        $this->assertTrue($response['canreply']);
        $this->assertStringContainsString('Visible source message', $response['quote']);
        $this->assertStringNotContainsString('<p>', $response['quote']);
        $this->assertSame(fullname($author), $response['author']);
    }

    public function test_native_reply_preserves_parent_and_validates_quote_on_server(): void {
        global $DB;

        $this->resetAfterTest();
        [, , , , $discussion] = $this->fixture();
        $result = reply_from_feed::execute($discussion->firstpost, 'My contextual response', true, []);
        $response = external_api::clean_returnvalue(reply_from_feed::execute_returns(), $result);

        $post = $DB->get_record('forum_posts', ['id' => $response['postid']], '*', MUST_EXIST);
        $this->assertSame((int)$discussion->firstpost, (int)$post->parent);
        $this->assertSame((int)$discussion->id, (int)$post->discussion);
        $this->assertStringContainsString('My contextual response', $post->message);
        $this->assertStringContainsString('Visible source message', $post->message);
        $this->assertStringContainsString('targetpost=' . $post->id, $response['url']);
        $this->assertSame(0, $response['notified']);
    }

    public function test_simple_reply_does_not_invent_quote(): void {
        global $DB;

        $this->resetAfterTest();
        [, , , , $discussion] = $this->fixture();
        $response = reply_from_feed::execute($discussion->firstpost, 'Only my reply', false, []);
        $post = $DB->get_record('forum_posts', ['id' => $response['postid']], '*', MUST_EXIST);
        $this->assertStringContainsString('Only my reply', $post->message);
        $this->assertStringNotContainsString('Visible source message', $post->message);
    }

    public function test_empty_body_is_rejected_without_creating_post(): void {
        global $DB;

        $this->resetAfterTest();
        [, , , , $discussion] = $this->fixture();
        $before = $DB->count_records('forum_posts', ['discussion' => $discussion->id]);
        try {
            reply_from_feed::execute($discussion->firstpost, " \n ", false, []);
            $this->fail('An empty reply must not be accepted.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame($before, $DB->count_records('forum_posts',
                ['discussion' => $discussion->id]));
        }
    }

    public function test_mention_search_only_returns_visible_participants(): void {
        $this->resetAfterTest();
        [, $author, , , $discussion, $generator] = $this->fixture();
        $outsider = $generator->create_user(['firstname' => 'Alice', 'lastname' => 'Outsider']);
        $result = search_feed_mentions::execute($discussion->firstpost, 'Alice');
        $response = external_api::clean_returnvalue(search_feed_mentions::execute_returns(), $result);

        $this->assertCount(1, $response['users']);
        $this->assertSame((int)$author->id, $response['users'][0]['id']);
        $this->assertStringNotContainsString(fullname($outsider), $response['users'][0]['name']);
        $this->assertEmpty(search_feed_mentions::execute($discussion->firstpost, 'A')['users']);
    }

    public function test_native_mention_sends_only_to_validated_visible_recipient(): void {
        global $DB;

        $this->resetAfterTest();
        [, $author, , , $discussion] = $this->fixture();
        $result = reply_from_feed::execute(
            $discussion->firstpost, 'Thanks @' . fullname($author) . ', that worked.', false, [$author->id]);

        $this->assertSame(1, $result['notified']);
        $post = $DB->get_record('forum_posts', ['id' => $result['postid']], '*', MUST_EXIST);
        // Plain display names are intentionally portable between course backups.
        $this->assertStringContainsString('@' . fullname($author), $post->message);
        $this->assertStringNotContainsString('user/view.php?id=', $post->message);
    }

    public function test_mention_id_not_present_in_message_is_not_notified(): void {
        $this->resetAfterTest();
        [, $author, , , $discussion] = $this->fixture();
        $result = reply_from_feed::execute(
            $discussion->firstpost, 'A normal reply without the mention marker.', false, [$author->id]);
        $this->assertSame(0, $result['notified']);
    }

    public function test_mention_id_from_another_course_is_not_notified(): void {
        $this->resetAfterTest();
        [, , , , $discussion, $generator] = $this->fixture();
        $outsider = $generator->create_user(['firstname' => 'Outside', 'lastname' => 'Course']);
        $result = reply_from_feed::execute(
            $discussion->firstpost, 'Hello @' . fullname($outsider), false, [$outsider->id]);
        $this->assertSame(0, $result['notified']);
    }

    public function test_mention_count_limit_is_checked_before_native_write(): void {
        global $DB;

        $this->resetAfterTest();
        [, , , , $discussion] = $this->fixture();
        $before = $DB->count_records('forum_posts', ['discussion' => $discussion->id]);
        $this->expectException(\invalid_parameter_exception::class);
        try {
            reply_from_feed::execute($discussion->firstpost, 'Hello', false, [1, 2, 3, 4, 5, 6]);
        } finally {
            $this->assertSame($before, $DB->count_records('forum_posts',
                ['discussion' => $discussion->id]));
        }
    }

    public function test_unenrolled_user_cannot_read_reply_context(): void {
        $this->resetAfterTest();
        [, , , , $discussion, $generator] = $this->fixture();
        $outsider = $generator->create_user();
        $this->setUser($outsider);
        $this->expectException(\required_capability_exception::class);
        get_feed_reply_context::execute($discussion->firstpost);
    }

    public function test_qanda_replies_hidden_from_reader_cannot_be_quoted(): void {
        $this->resetAfterTest();
        [, $author, , , $discussion, $generator] = $this->fixture(['type' => 'qanda']);
        $post = $generator->get_plugin_generator('mod_forum')->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $author->id,
            'message' => '<p>Protected teacher answer</p>',
        ]);
        $this->expectException(\required_capability_exception::class);
        get_feed_reply_context::execute($post->id);
    }

    public function test_private_reply_cannot_be_replied_to_from_feed(): void {
        $this->resetAfterTest();
        [, $author, , , $discussion, $generator] = $this->fixture();
        $post = $generator->get_plugin_generator('mod_forum')->create_post((object)[
            'discussion' => $discussion->id,
            'parent' => $discussion->firstpost,
            'userid' => $author->id,
            'privatereplyto' => $author->id,
            'message' => '<p>Private reply</p>',
        ]);
        $this->expectException(\required_capability_exception::class);
        reply_from_feed::execute($post->id, 'Invalid reply', false, []);
    }
}
