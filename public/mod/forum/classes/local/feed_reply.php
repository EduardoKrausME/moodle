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
 * Permission-checked context and mention candidates for the cross-course feed.
 *
 * Nothing is persisted here: the native forum owns posts and the core message
 * system owns mention notifications. Mention names remain plain readable text
 * in course backups, without storing user IDs in the post body.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class feed_reply {
    public const MAX_MENTIONS = 5;
    public const MAX_MESSAGE_LENGTH = 12000;
    private const MAX_QUOTE_LENGTH = 450;
    private const MAX_PARTICIPANT_POSTS = 400;
    private const MAX_MENTION_RESULTS = 8;

    /**
     * Validate access to the exact post, not just its course or discussion.
     *
     * @param int $postid Native parent post ID.
     * @return array All trusted records and native capability manager.
     */
    public static function context(int $postid): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        if ($postid <= 0 || !isloggedin() || isguestuser()) {
            throw new \invalid_parameter_exception('Invalid forum reply target.');
        }
        $post = $DB->get_record('forum_posts', ['id' => $postid, 'deleted' => 0], '*', MUST_EXIST);
        $discussion = $DB->get_record('forum_discussions', ['id' => $post->discussion], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        $course = get_course($forum->course);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);

        // Match the global feed's enrolment boundary. Course visibility alone
        // must never make posts from unenrolled courses accessible.
        if (!isset(recent_feed::get_accessible_courses((int)$course->id)[$course->id])) {
            throw new \required_capability_exception(
                \context_course::instance($course->id), 'moodle/course:view', 'nopermissions', '');
        }
        require_login($course, false, $cm);
        $visiblecm = get_fast_modinfo($course, $USER->id)->get_cm($cm->id);
        $context = \context_module::instance($cm->id);
        if (!$visiblecm->uservisible ||
                !has_capability('mod/forum:viewdiscussion', $context, $USER) ||
                !forum_user_can_see_post($forum, $discussion, $post, $USER, $visiblecm)) {
            throw new \required_capability_exception($context, 'mod/forum:viewdiscussion', 'nopermissions', '');
        }

        $vaultfactory = container::get_vault_factory();
        $forumentity = $vaultfactory->get_forum_vault()->get_from_id($forum->id);
        $discussionentity = $vaultfactory->get_discussion_vault()->get_from_id($discussion->id);
        $postentity = $vaultfactory->get_post_vault()->get_from_id($post->id);
        $manager = container::get_manager_factory()->get_capability_manager($forumentity);
        $canreply = $manager->can_reply_to_post($USER, $discussionentity, $postentity);

        return compact('post', 'discussion', 'forum', 'course', 'cm', 'context', 'visiblecm',
            'canreply');
    }

    /**
     * Return a safe, short citation of a post which has passed visibility checks.
     *
     * @param array $data Trusted reply context.
     * @return array Plain text context for the editor.
     */
    public static function describe(array $data): array {
        global $DB;

        $post = $data['post'];
        $author = $DB->get_record('user', ['id' => $post->userid], 'id,firstname,lastname,middlename,alternatename,firstnamephonetic,lastnamephonetic');
        $name = $author ? fullname($author) : '';
        $quote = html_to_text($post->message, 0, false);
        $quote = preg_replace('/\s+/u', ' ', trim($quote));
        return [
            'postid' => (int)$post->id,
            'discussionid' => (int)$data['discussion']->id,
            'canreply' => (bool)$data['canreply'],
            'subject' => (string)$post->subject,
            'author' => $name,
            'quote' => \core_text::substr($quote ?? '', 0, self::MAX_QUOTE_LENGTH),
        ];
    }

    /**
     * Participants are drawn from the *visible* posts of this discussion only.
     * Never enumerate an unrelated course user or leak hidden group/Q&A authors.
     *
     * @param array $data Trusted reply context.
     * @return array User records keyed by ID, with safe names.
     */
    private static function participants(array $data): array {
        global $DB, $USER;

        $posts = $DB->get_records_sql(
            "SELECT p.*
               FROM {forum_posts} p
              WHERE p.discussion = :discussionid AND p.deleted = 0
           ORDER BY p.id DESC",
            ['discussionid' => $data['discussion']->id], 0, self::MAX_PARTICIPANT_POSTS);
        $userids = [];
        foreach ($posts as $post) {
            if ($post->userid != $USER->id &&
                    forum_user_can_see_post($data['forum'], $data['discussion'],
                        $post, $USER, $data['visiblecm'])) {
                $userids[(int)$post->userid] = true;
            }
        }
        if (!$userids) {
            return [];
        }

        $users = $DB->get_records_list('user', 'id', array_keys($userids));
        $coursecontext = \context_course::instance($data['course']->id);
        foreach ($users as $id => $user) {
            if ($user->deleted || $user->suspended ||
                    (!is_enrolled($coursecontext, $user, '', true) && !is_viewing($coursecontext, $user))) {
                unset($users[$id]);
            }
        }
        return $users;
    }

    /**
     * Search only participants already visible in this discussion.
     *
     * @param array $data Trusted reply context.
     * @param string $query Non-sensitive name prefix/substring.
     * @return array Results capped at eight participants.
     */
    public static function search_mentions(array $data, string $query): array {
        if (!$data['canreply']) {
            return [];
        }
        $query = trim($query);
        if (\core_text::strlen($query) < 2 || \core_text::strlen($query) > 70) {
            return [];
        }
        $result = [];
        foreach (self::participants($data) as $user) {
            $name = fullname($user);
            if (\core_text::strpos(\core_text::strtolower($name), \core_text::strtolower($query)) === false) {
                continue;
            }
            $result[] = ['id' => (int)$user->id, 'name' => $name];
            if (count($result) >= self::MAX_MENTION_RESULTS) {
                break;
            }
        }
        return $result;
    }

    /**
     * Build the quote on the server so the client never supplies protected post text.
     *
     * @param array $data Validated context.
     * @param string $body User-authored plain text.
     * @param bool $quote Whether to include the source post.
     * @return string Native forum message.
     */
    public static function message(array $data, string $body, bool $quote): string {
        $body = trim($body);
        if ($body === '' || \core_text::strlen($body) > self::MAX_MESSAGE_LENGTH) {
            throw new \invalid_parameter_exception('Invalid forum reply length.');
        }
        if (!$quote) {
            return $body;
        }
        $details = self::describe($data);
        $text = str_replace(["\n", "\r"], ' ', $details['quote']);
        return '> ' . $details['author'] . ': "' . $text . '"' . "\n\n" . $body;
    }

    /**
     * Notify validated visible participants after a native post has been created.
     * Core messaging stores its own notification data; forum posts retain names
     * rather than foreign-user-ID markup that could break course restoration.
     *
     * @param array $data Parent reply context.
     * @param int $newpostid Native new post ID.
     * @param string $body Raw authored message.
     * @param array $mentionids Explicit choices from the autocomplete.
     * @return int Number of messages attempted successfully.
     */
    public static function notify_mentions(array $data, int $newpostid, string $body,
            array $mentionids): int {
        global $DB, $USER;

        $mentionids = array_unique(array_map('intval', $mentionids));
        if (count($mentionids) > self::MAX_MENTIONS) {
            throw new \invalid_parameter_exception('Too many forum mentions.');
        }
        if (!$mentionids) {
            return 0;
        }
        $participants = self::participants($data);
        $newpost = $DB->get_record('forum_posts', ['id' => $newpostid, 'discussion' => $data['discussion']->id],
            '*', MUST_EXIST);
        $url = new \moodle_url('/mod/forum/discuss.php', [
            'd' => $data['discussion']->id, 'targetpost' => $newpostid,
        ]);
        $url->set_anchor('p' . $newpostid);

        $sent = 0;
        foreach ($mentionids as $id) {
            if (!isset($participants[$id])) {
                continue;
            }
            $recipient = $participants[$id];
            if (strpos($body, '@' . fullname($recipient)) === false) {
                continue;
            }
            $recipientcm = get_fast_modinfo($data['course'], $recipient->id)->get_cm($data['cm']->id);
            if (!$recipientcm->uservisible ||
                    !has_capability('mod/forum:viewdiscussion', $data['context'], $recipient) ||
                    !forum_user_can_see_post($data['forum'], $data['discussion'], $newpost, $recipient, $recipientcm)) {
                continue;
            }

            $message = new \core\message\message();
            $message->component = 'mod_forum';
            $message->name = 'mentions';
            $message->userfrom = $USER;
            $message->userto = $recipient;
            $message->subject = get_string('recentfeedmentionnotification', 'mod_forum');
            $message->fullmessage = get_string('recentfeedmentionnotificationbody', 'mod_forum', fullname($USER));
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = $message->fullmessage;
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('recentfeedviewreply', 'mod_forum');
            message_send($message);
            $sent++;
        }
        return $sent;
    }
}
