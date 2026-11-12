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
 * Recent forum discussions for one course; no duplicated post storage.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recent_discussions {
    private const MAX_DISCUSSIONS = 3;
    private const BATCH_SIZE = 100;
    private const MAX_CANDIDATES = 400;

    /**
     * Return recent conversations only when their latest post is visible.
     *
     * @param \stdClass $course Course already checked by the web service.
     * @return array Safe-to-display discussion details.
     */
    public static function get_for_course(\stdClass $course): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $urlfactory = \mod_forum\local\container::get_url_factory();

        // Use modinfo so activity visibility and access restrictions are applied.
        $modinfo = get_fast_modinfo($course, $USER->id);
        $modulemap = [];
        foreach ($modinfo->get_instances_of('forum') as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            $context = \context_module::instance($cm->id);
            if (!has_capability('mod/forum:viewdiscussion', $context, $USER)) {
                continue;
            }
            $modulemap[$cm->instance] = $cm;
        }
        if (!$modulemap) {
            return [];
        }

        $forums = $DB->get_records_list('forum', 'id', array_keys($modulemap));
        foreach ($forums as $forumid => $forum) {
            if (empty($forum->showinmycourses)) {
                unset($forums[$forumid]);
            }
        }
        if (!$forums) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($forums), SQL_PARAMS_NAMED, 'recentforum');
        $sql = "SELECT p.id, p.discussion, p.userid, p.parent, p.created, p.modified,
                       p.privatereplyto, p.deleted, d.forum AS forumid
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                 WHERE d.forum {$insql} AND p.deleted = 0";

        $visible = [];
        $seen = [];
        $lastcreated = 0;
        $lastpostid = 0;
        // Use a bounded keyset rather than increasingly costly OFFSET scans.
        // Only three visible discussions are returned, regardless of forum count.
        for ($scanned = 0; $scanned < self::MAX_CANDIDATES; $scanned += self::BATCH_SIZE) {
            $seek = '';
            $seekparams = $params;
            if ($lastpostid > 0) {
                $seek = ' AND (p.created < :recentbefore OR ' .
                    '(p.created = :recentequal AND p.id < :recentpost))';
                $seekparams['recentbefore'] = $lastcreated;
                $seekparams['recentequal'] = $lastcreated;
                $seekparams['recentpost'] = $lastpostid;
            }
            $posts = $DB->get_records_sql(
                $sql . $seek . ' ORDER BY p.created DESC, p.id DESC', $seekparams, 0, self::BATCH_SIZE);
            if (!$posts) {
                break;
            }

            $discussionids = array_unique(array_column(array_values($posts), 'discussion'));
            $discussions = $DB->get_records_list('forum_discussions', 'id', $discussionids);
            foreach ($posts as $post) {
                $discussionid = (int)$post->discussion;
                if (isset($seen[$discussionid]) || empty($discussions[$discussionid]) ||
                        empty($forums[$post->forumid])) {
                    continue;
                }
                $discussion = $discussions[$discussionid];
                $forum = $forums[$post->forumid];
                $cm = $modulemap[$post->forumid];

                // Native checks cover groups, Q&A, timed discussions and private replies.
                if (!forum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
                    continue;
                }

                $context = \context_module::instance($cm->id);
                $seen[$discussionid] = true;
                // Reuse the native forum permalink and add a server-visible target.
                // The #pID fragment is kept for compatibility with existing links.
                $posturl = $urlfactory->get_view_post_url_from_post_id($discussionid, (int)$post->id);
                $posturl->param('targetpost', (int)$post->id);
                $visible[] = [
                    'postid' => (int)$post->id,
                    'subject' => format_string($discussion->name, true, ['context' => $context]),
                    'forumname' => format_string($forum->name, true, ['context' => $context]),
                    'userid' => (int)$post->userid,
                    'created' => (int)$post->created,
                    'url' => $posturl->out(false),
                ];
                if (count($visible) >= self::MAX_DISCUSSIONS) {
                    break 2;
                }
            }
            if (count($posts) < self::BATCH_SIZE) {
                break;
            }
            $lastpost = end($posts);
            $lastcreated = (int)$lastpost->created;
            $lastpostid = (int)$lastpost->id;
        }

        if (!$visible) {
            return [];
        }

        $authors = $DB->get_records_list('user', 'id', array_unique(array_column($visible, 'userid')));
        $result = [];
        foreach ($visible as $item) {
            $result[] = [
                'postid' => $item['postid'],
                'subject' => $item['subject'],
                'forumname' => $item['forumname'],
                'author' => isset($authors[$item['userid']]) ? fullname($authors[$item['userid']]) : '',
                'time' => userdate($item['created'], get_string('strftimedatetime', 'langconfig')),
                'url' => $item['url'],
            ];
        }
        return $result;
    }
}
