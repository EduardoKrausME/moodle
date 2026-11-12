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
 * Aggregated, access-controlled unread message counts for My courses.
 *
 * Only returns a numeric count after checking each post against native forum
 * visibility rules. When the safety bound is reached, returns the non-numeric
 * "activity" state instead of displaying a potentially incorrect count.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_unread_counts {
    /** Maximum courses accepted by one request. */
    public const MAX_COURSES = 96;

    /** Maximum unread candidates checked for an entire request. */
    private const MAX_CANDIDATES = 4000;

    /** Avoid large IN clauses while loading discussion data. */
    private const DISCUSSION_BATCH = 200;

    /**
     * Aggregate unread post counts across accessible courses and visible forums.
     *
     * @param array $courses Course records indexed by their ID, already access-checked.
     * @return array Each entry has courseid, count, forumcount and state.
     */
    public static function get_for_courses(array $courses): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $result = [];
        $modulemap = [];
        foreach ($courses as $course) {
            $courseid = (int)$course->id;
            $result[$courseid] = [
                'courseid' => $courseid,
                'count' => 0,
                'forumcount' => 0,
                'state' => 'none',
            ];

            // Modinfo enforces activity availability and the current user's visibility.
            $modinfo = get_fast_modinfo($course, $USER->id);
            foreach ($modinfo->get_instances_of('forum') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $context = \context_module::instance($cm->id);
                if (!has_capability('mod/forum:viewdiscussion', $context, $USER)) {
                    continue;
                }
                $modulemap[(int)$cm->instance] = [
                    'cm' => $cm,
                    'courseid' => $courseid,
                ];
            }
        }

        if (!$modulemap) {
            return array_values($result);
        }

        $forums = $DB->get_records_list('forum', 'id', array_keys($modulemap));
        $tracked = [];
        $hasuntracked = [];
        foreach ($forums as $forumid => $forum) {
            // Forum-level opt-out added in item 1.
            if (empty($forum->showinmycourses)) {
                continue;
            }
            $courseid = $modulemap[$forumid]['courseid'];
            if (forum_tp_is_tracked($forum, $USER)) {
                $tracked[$forumid] = $forum;
            } else {
                $hasuntracked[$courseid] = true;
            }
        }

        if (!$tracked) {
            foreach ($hasuntracked as $courseid => $unused) {
                $result[$courseid]['state'] = 'activity';
            }
            return array_values($result);
        }

        [$forumsql, $params] = $DB->get_in_or_equal(array_keys($tracked), SQL_PARAMS_NAMED, 'trackedforum');
        $cutoff = floor(time() / MINSECS) * MINSECS -
            ((int)$CFG->forum_oldpostdays * DAYSECS);
        $params['userid'] = $USER->id;
        $params['cutoff'] = $cutoff;

        // A single bounded query, without an N+1 query per forum.
        // The results are not exposed until every candidate passes native access checks.
        $sql = "SELECT p.id, p.discussion, p.userid, p.parent, p.created, p.modified,
                       p.deleted, p.privatereplyto, d.forum AS forumid
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
             LEFT JOIN {forum_read} r ON r.postid = p.id AND r.userid = :userid
                 WHERE d.forum {$forumsql}
                       AND p.modified >= :cutoff
                       AND p.deleted = 0
                       AND r.id IS NULL
              ORDER BY p.modified DESC, p.id DESC";
        $posts = $DB->get_records_sql($sql, $params, 0, self::MAX_CANDIDATES + 1);

        if (count($posts) > self::MAX_CANDIDATES) {
            // Numeric totals would be incomplete. Do not show approximations as exact.
            foreach ($tracked as $forumid => $forum) {
                $courseid = $modulemap[$forumid]['courseid'];
                $result[$courseid]['state'] = 'activity';
            }
            foreach ($hasuntracked as $courseid => $unused) {
                $result[$courseid]['state'] = 'activity';
            }
            return array_values($result);
        }

        // Check posts in batches to avoid excessively large IN clauses.
        $forumcounts = [];
        foreach (array_chunk(array_values($posts), self::DISCUSSION_BATCH) as $batch) {
            $discussionids = array_unique(array_column($batch, 'discussion'));
            $discussions = $DB->get_records_list('forum_discussions', 'id', $discussionids);
            foreach ($batch as $post) {
                $forumid = (int)$post->forumid;
                if (empty($tracked[$forumid]) || empty($discussions[$post->discussion])) {
                    continue;
                }
                $forum = $tracked[$forumid];
                $discussion = $discussions[$post->discussion];
                $cm = $modulemap[$forumid]['cm'];

                // Groups, Q&A, private replies, timed posts and hidden activities.
                if (!forum_user_can_see_post($forum, $discussion, $post, $USER, $cm)) {
                    continue;
                }

                $courseid = $modulemap[$forumid]['courseid'];
                $result[$courseid]['count']++;
                $forumcounts[$courseid][$forumid] = true;
            }
        }

        foreach ($result as $courseid => &$entry) {
            $entry['forumcount'] = isset($forumcounts[$courseid]) ? count($forumcounts[$courseid]) : 0;
            if ($entry['count'] > 0) {
                $entry['state'] = isset($hasuntracked[$courseid]) ? 'partial' : 'unread';
            } else if (isset($hasuntracked[$courseid])) {
                $entry['state'] = 'activity';
            }
        }
        unset($entry);

        return array_values($result);
    }
}
