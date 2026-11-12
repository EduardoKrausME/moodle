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
 * Accepted-solution state is intentionally separate from ratings and grades.
 * The unique discussionid index allows at most one accepted reply.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class solution_manager {
    /**
     * Validate a discussion and the current user's access to its activity.
     *
     * @param int $discussionid Discussion ID.
     * @return array Trusted forum, course, cm, context, user and discussion.
     */
    private static function access(int $discussionid): array {
        global $DB, $USER, $CFG;

        require_once($CFG->dirroot . '/mod/forum/lib.php');
        require_login();
        if ($discussionid <= 0 || isguestuser()) {
            throw new \invalid_parameter_exception('Invalid forum discussion.');
        }
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid], '*', MUST_EXIST);
        $forum = $DB->get_record('forum', ['id' => $discussion->forum], '*', MUST_EXIST);
        $course = get_course($forum->course);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $coursecontext = \context_course::instance($course->id);
        if (!is_enrolled($coursecontext, $USER, '', true) && !is_viewing($coursecontext, $USER)) {
            throw new \required_capability_exception($context, 'mod/forum:viewdiscussion', 'nopermissions', '');
        }
        require_login($course, false, $cm);
        $visiblecm = get_fast_modinfo($course, $USER->id)->get_cm($cm->id);
        if (!$visiblecm->uservisible ||
                !has_capability('mod/forum:viewdiscussion', $context, $USER) ||
                !forum_user_can_see_discussion($forum, $discussion, $context, $USER)) {
            throw new \required_capability_exception($context, 'mod/forum:viewdiscussion', 'nopermissions', '');
        }
        return compact('discussion', 'forum', 'course', 'cm', 'context', 'visiblecm');
    }

    /**
     * Only authorised staff or the discussion starter may change its solution.
     *
     * @param array $data Checked context.
     * @return bool
     */
    public static function can_manage(array $data): bool {
        global $USER;
        if (empty($data['forum']->allowsolutions)) {
            return false;
        }
        if (has_capability('mod/forum:managesolutions', $data['context'], $USER)) {
            return true;
        }
        return (int)$data['discussion']->userid === (int)$USER->id &&
            has_capability('mod/forum:markownsolution', $data['context'], $USER);
    }

    /**
     * A solution cannot be the opening post, deleted, private or invisible.
     *
     * @param array $data Checked context.
     * @param \stdClass $post Candidate post.
     * @return bool
     */
    private static function can_see_candidate(array $data, \stdClass $post): bool {
        global $USER;
        return (int)$post->discussion === (int)$data['discussion']->id &&
            (int)$post->id !== (int)$data['discussion']->firstpost &&
            empty($post->deleted) && empty($post->privatereplyto) &&
            forum_user_can_see_post($data['forum'], $data['discussion'], $post,
                $USER, $data['visiblecm']);
    }

    /**
     * Return only the solution that the current user may actually read.
     * A stale or protected accepted post must never be disclosed by its ID.
     *
     * @param int $discussionid Discussion ID.
     * @return array State suitable for AJAX and for the feed.
     */
    public static function get_state(int $discussionid): array {
        global $DB;

        $data = self::access($discussionid);
        $canmanage = self::can_manage($data);
        $result = [
            'enabled' => !empty($data['forum']->allowsolutions),
            'canmanage' => $canmanage,
            'postid' => 0,
            'url' => '',
        ];
        if (!$result['enabled']) {
            return $result;
        }
        $solution = $DB->get_record('forum_solutions', ['discussionid' => $discussionid]);
        if (!$solution) {
            return $result;
        }
        $post = $DB->get_record('forum_posts', ['id' => $solution->postid]);
        if (!$post || !self::can_see_candidate($data, $post)) {
            return $result;
        }
        $result['postid'] = (int)$post->id;
        $result['url'] = self::post_url($discussionid, (int)$post->id);
        return $result;
    }

    /**
     * Resolve visible solutions for one page without per-card SQL lookups.
     *
     * @param array $items Map of discussion ID to discussion, forum, cm and context.
     * @return array Map of discussion ID to public solution state.
     */
    public static function visible_for_page(array $items): array {
        global $DB, $USER;

        if (!$items) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($items), SQL_PARAMS_NAMED, 'solutiondiscussion');
        $solutions = $DB->get_records_sql(
            "SELECT s.discussionid, s.postid, p.*
               FROM {forum_solutions} s
               JOIN {forum_posts} p ON p.id = s.postid
              WHERE s.discussionid {$insql}",
            $params);
        $result = [];
        foreach ($solutions as $record) {
            $id = (int)$record->discussionid;
            if (!isset($items[$id])) {
                continue;
            }
            $data = $items[$id];
            if (empty($data['forum']->allowsolutions) || (int)$record->discussion !== $id ||
                    (int)$record->postid === (int)$data['discussion']->firstpost ||
                    !empty($record->deleted) || !empty($record->privatereplyto)) {
                continue;
            }
            // SQL already provides the complete post; do not introduce
            // an additional query per discussion when rendering a feed page.
            if (forum_user_can_see_post($data['forum'], $data['discussion'],
                    $record, $USER, $data['cm'])) {
                $post = $record;
                $result[$id] = [
                    'postid' => (int)$post->id,
                    'url' => self::post_url($id, (int)$post->id),
                ];
            }
        }
        return $result;
    }

    /**
     * Mutate a solution serially; never publish a new forum post or grade.
     *
     * @param int $discussionid Discussion ID.
     * @param int $postid Reply to accept, or zero to remove the solution.
     * @return array Current visible state.
     */
    public static function set_solution(int $discussionid, int $postid): array {
        global $DB, $USER;

        $data = self::access($discussionid);
        if (!self::can_manage($data)) {
            throw new \required_capability_exception(
                $data['context'], 'mod/forum:managesolutions', 'nopermissions', '');
        }
        if ($postid < 0) {
            throw new \invalid_parameter_exception('Invalid accepted solution.');
        }
        if ($postid) {
            $candidate = $DB->get_record('forum_posts', ['id' => $postid], '*', MUST_EXIST);
            if (!self::can_see_candidate($data, $candidate)) {
                throw new \invalid_parameter_exception('Ineligible forum solution.');
            }
        }

        // Lock before checking the unique discussion row, preventing duplicate
        // first inserts and lost updates on concurrent requests.
        $factory = \core\lock\lock_config::get_lock_factory('mod_forum_solutions');
        $lock = $factory->get_lock('discussion-' . $discussionid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'error');
        }
        $action = '';
        $previous = 0;
        $recordid = 0;
        try {
            $transaction = $DB->start_delegated_transaction();
            $current = $DB->get_record('forum_solutions', ['discussionid' => $discussionid]);
            $previous = $current ? (int)$current->postid : 0;
            if ($previous !== $postid) {
                if (!$postid) {
                    if ($current) {
                        $recordid = (int)$current->id;
                        $DB->delete_records('forum_solutions', ['id' => $current->id]);
                        $action = 'removed';
                    }
                } else if ($current) {
                    $recordid = (int)$current->id;
                    $current->postid = $postid;
                    $current->userid = $USER->id;
                    $current->timemodified = time();
                    $DB->update_record('forum_solutions', $current);
                    $action = 'replaced';
                } else {
                    $recordid = $DB->insert_record('forum_solutions', (object)[
                        'discussionid' => $discussionid,
                        'postid' => $postid,
                        'userid' => $USER->id,
                        'timecreated' => time(),
                        'timemodified' => time(),
                    ]);
                    $action = 'marked';
                }
            }
            $transaction->allow_commit();
        } finally {
            $lock->release();
        }

        if ($action) {
            \mod_forum\event\solution_updated::create([
                'objectid' => $recordid,
                'context' => $data['context'],
                'other' => [
                    'forumid' => (int)$data['forum']->id,
                    'discussionid' => $discussionid,
                    'postid' => $postid,
                    'previouspostid' => $previous,
                    'action' => $action,
                ],
            ])->trigger();
        }
        return self::get_state($discussionid);
    }

    /**
     * @param int $discussionid Native discussion ID.
     * @param int $postid Native visible post ID.
     * @return string Deep link to the visible accepted reply.
     */
    private static function post_url(int $discussionid, int $postid): string {
        $url = new \moodle_url('/mod/forum/discuss.php', [
            'd' => $discussionid, 'targetpost' => $postid,
        ]);
        $url->set_anchor('p' . $postid);
        return $url->out(false);
    }
}
