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
 * Access-controlled, cursor-paginated forum activity across the user's courses.
 *
 * Each topic appears once, using its newest visible post. Never expose rows,
 * counts, or previews before checking the native visibility rules.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recent_feed {
    public const PAGE_SIZE = 20;
    private const BATCH_SIZE = 160;
    private const MAX_SCANNED = 2400;
    private const MAX_SEEN = 5000;
    private const MAX_CURSOR_LENGTH = 60000;
    private const SNIPPET_LENGTH = 180;

    /**
     * Discover courses visible through active enrolments or viewing roles.
     *
     * @param int $filtercourseid Optional course filter to avoid full discovery.
     * @return array Course records keyed by course ID.
     */
    public static function get_accessible_courses(int $filtercourseid = 0): array {
        global $CFG, $DB, $USER;

        require_once($CFG->libdir . '/enrollib.php');

        // A single-course request should never enumerate the entire catalogue.
        if ($filtercourseid > 0) {
            $course = $DB->get_record('course', ['id' => $filtercourseid]);
            if (!$course) {
                return [];
            }
            $context = \context_course::instance($course->id);
            if ((!is_enrolled($context, $USER, '', true) && !is_viewing($context, $USER)) ||
                    (!$course->visible &&
                    !has_capability('moodle/course:viewhiddencourses', $context, $USER))) {
                return [];
            }
            return [$course->id => $course];
        }

        $courses = enrol_get_users_courses($USER->id, true);
        // Teachers with viewing roles may not have a conventional enrolment.
        $viewingcourses = get_user_capability_course('moodle/course:view', $USER->id, true,
            'fullname,shortname,visible');
        foreach ($viewingcourses ?: [] as $candidate) {
            if (isset($courses[$candidate->id])) {
                continue;
            }
            $context = \context_course::instance($candidate->id);
            if (!is_viewing($context, $USER)) {
                continue;
            }
            if (!$candidate->visible &&
                    !has_capability('moodle/course:viewhiddencourses', $context, $USER)) {
                continue;
            }
            $courses[$candidate->id] = $candidate;
        }

        foreach ($courses as $courseid => $course) {
            if ((int)$courseid === SITEID) {
                unset($courses[$courseid]);
                continue;
            }
            $context = \context_course::instance($courseid);
            if ((!is_enrolled($context, $USER, '', true) && !is_viewing($context, $USER)) ||
                    (!$course->visible &&
                    !has_capability('moodle/course:viewhiddencourses', $context, $USER))) {
                unset($courses[$courseid]);
            }
        }

        return $courses;
    }

    /**
     * Accessible course options for the course selector.
     *
     * @return array Course IDs and formatted names.
     */
    public static function get_course_options(): array {
        $result = [];
        foreach (self::get_accessible_courses() as $course) {
            $result[] = [
                'id' => (int)$course->id,
                'name' => format_string($course->fullname, true,
                    ['context' => \context_course::instance($course->id)]),
            ];
        }
        usort($result, static function(array $a, array $b): int {
            return strcoll($a['name'], $b['name']);
        });
        return $result;
    }

    /**
     * Decode a cursor bound to the current user and filter settings.
     *
     * A cursor can only omit results, not bypass access checks.
     *
     * @param string $cursor Opaque URL-safe cursor.
     * @param int $courseid Selected course.
     * @param bool $unread Unread-only filter.
     * @return array State for iteration.
     */
    private static function parse_cursor(string $cursor, int $courseid, bool $unread): array {
        global $USER;

        $empty = ['time' => 0, 'post' => 0, 'seen' => []];
        if ($cursor === '') {
            return $empty;
        }
        if (strlen($cursor) > self::MAX_CURSOR_LENGTH ||
                !preg_match('/^[A-Za-z0-9_-]+$/D', $cursor)) {
            throw new \invalid_parameter_exception('Invalid forum feed cursor.');
        }
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($data) || ($data['v'] ?? null) !== 1 ||
                ($data['uid'] ?? null) !== (int)$USER->id ||
                ($data['course'] ?? null) !== $courseid ||
                ($data['unread'] ?? null) !== (int)$unread ||
                !isset($data['time'], $data['post'], $data['seen']) ||
                !is_int($data['time']) || !is_int($data['post']) ||
                $data['time'] <= 0 || $data['post'] <= 0 ||
                !is_array($data['seen']) || count($data['seen']) > self::MAX_SEEN) {
            throw new \invalid_parameter_exception('Invalid forum feed cursor.');
        }
        foreach ($data['seen'] as $id) {
            if (!is_int($id) || $id <= 0) {
                throw new \invalid_parameter_exception('Invalid forum feed cursor.');
            }
        }
        return [
            'time' => $data['time'],
            'post' => $data['post'],
            'seen' => array_fill_keys($data['seen'], true),
        ];
    }

    /**
     * Make an opaque continuation token; URLs are not a trust boundary.
     *
     * @param array $position Last processed post position.
     * @param array $seen Already emitted discussion IDs.
     * @param int $courseid Filtered course.
     * @param bool $unread Only unread posts.
     * @return string
     */
    private static function encode_cursor(array $position, array $seen, int $courseid, bool $unread): string {
        global $USER;

        if (count($seen) >= self::MAX_SEEN) {
            return '';
        }
        $json = json_encode([
            'v' => 1,
            'uid' => (int)$USER->id,
            'course' => $courseid,
            'unread' => (int)$unread,
            'time' => (int)$position['time'],
            'post' => (int)$position['post'],
            'seen' => array_map('intval', array_keys($seen)),
        ]);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Return a safe page of recent forum discussion activity.
     *
     * @param int $courseid 0 means all accessible courses.
     * @param bool $unread Return only tracked, unread and visible posts.
     * @param string $cursor Continuation from the preceding page.
     * @return array Entries, nextcursor and hasmore.
     */
    public static function get_page(int $courseid = 0, bool $unread = false, string $cursor = ''): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $state = self::parse_cursor($cursor, $courseid, $unread);
        $courses = self::get_accessible_courses($courseid);
        $empty = ['items' => [], 'nextcursor' => '', 'hasmore' => false];
        if (!$courses) {
            return $empty;
        }

        $modulemap = [];
        foreach ($courses as $course) {
            $modinfo = get_fast_modinfo($course, $USER->id);
            foreach ($modinfo->get_instances_of('forum') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $context = \context_module::instance($cm->id);
                if (has_capability('mod/forum:viewdiscussion', $context, $USER)) {
                    $modulemap[(int)$cm->instance] = [
                        'cm' => $cm,
                        'course' => $course,
                        'context' => $context,
                    ];
                }
            }
        }
        if (!$modulemap) {
            return $empty;
        }

        $forums = $DB->get_records_list('forum', 'id', array_keys($modulemap));
        $tracked = [];
        foreach ($forums as $forumid => $forum) {
            // The showinmycourses option only affects item 1, not the global feed.
            $tracked[$forumid] = forum_tp_is_tracked($forum, $USER);
            if ($unread && !$tracked[$forumid]) {
                unset($forums[$forumid]);
            }
        }
        if (!$forums) {
            return $empty;
        }

        [$insql, $parameters] = $DB->get_in_or_equal(array_keys($forums), SQL_PARAMS_NAMED, 'feedforum');
        $parameters['readuser'] = $USER->id;
        $filtersql = '';
        $cutoff = floor(time() / MINSECS) * MINSECS - ($CFG->forum_oldpostdays * DAYSECS);
        if ($unread) {
            $filtersql .= ' AND r.id IS NULL AND p.modified >= :feedcutoff';
            $parameters['feedcutoff'] = $cutoff;
        }
        if ($state['time'] > 0) {
            $filtersql .= ' AND (p.created < :feedbefore OR (p.created = :feedequal AND p.id < :feedpost))';
            $parameters['feedbefore'] = $state['time'];
            $parameters['feedequal'] = $state['time'];
            $parameters['feedpost'] = $state['post'];
        }

        $sql = "SELECT p.id, p.discussion, p.userid, p.parent, p.created, p.modified,
                       p.privatereplyto, p.deleted, d.forum AS forumid, r.id AS readid
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
             LEFT JOIN {forum_read} r ON r.postid = p.id AND r.userid = :readuser
                 WHERE d.forum {$insql} AND p.deleted = 0 {$filtersql}
              ORDER BY p.created DESC, p.id DESC";

        $seen = $state['seen'];
        $lastposition = ['time' => $state['time'], 'post' => $state['post']];
        $rawitems = [];
        $hasmore = false;
        $exhausted = false;
        $scanned = 0;

        // Keyset pagination: no OFFSET scans or one request per course.
        while ($scanned < self::MAX_SCANNED) {
            $limit = min(self::BATCH_SIZE, self::MAX_SCANNED - $scanned);
            $posts = $DB->get_records_sql($sql, $parameters, 0, $limit);
            if (!$posts) {
                $exhausted = true;
                break;
            }

            $ids = array_unique(array_column(array_values($posts), 'discussion'));
            $discussions = $DB->get_records_list('forum_discussions', 'id', $ids);
            foreach ($posts as $post) {
                $discussionid = (int)$post->discussion;
                $allowed = false;
                if (!isset($seen[$discussionid]) && isset($discussions[$discussionid], $forums[$post->forumid])) {
                    $forum = $forums[$post->forumid];
                    $context = $modulemap[$post->forumid];
                    $allowed = forum_user_can_see_post(
                        $forum, $discussions[$discussionid], $post, $USER, $context['cm']);
                }

                if ($allowed && count($rawitems) === self::PAGE_SIZE) {
                    // The look-ahead post belongs to the next page; do not consume it.
                    $hasmore = true;
                    break 2;
                }

                $scanned++;
                $lastposition = ['time' => (int)$post->created, 'post' => (int)$post->id];
                if (!$allowed) {
                    continue;
                }

                $seen[$discussionid] = true;
                $rawitems[] = [
                    'post' => $post,
                    'discussion' => $discussions[$discussionid],
                    'forum' => $forum,
                    'course' => $context['course'],
                    'context' => $context['context'],
                    'unread' => $tracked[$post->forumid] && $post->readid === null &&
                        $post->modified >= $cutoff,
                ];
            }

            if (count($posts) < $limit) {
                $exhausted = true;
                break;
            }

            // Move the scan window forward without an OFFSET.
            $filtersqlnext = ' AND (p.created < :feedbefore OR (p.created = :feedequal AND p.id < :feedpost))';
            if ($state['time'] === 0) {
                $filtersql .= $filtersqlnext;
                $sql = str_replace(' AND p.deleted = 0 ', ' AND p.deleted = 0 ' . $filtersqlnext . ' ', $sql);
            }
            $state['time'] = $lastposition['time'];
            $parameters['feedbefore'] = $lastposition['time'];
            $parameters['feedequal'] = $lastposition['time'];
            $parameters['feedpost'] = $lastposition['post'];
        }

        if (!$hasmore && !$exhausted && $scanned >= self::MAX_SCANNED) {
            $hasmore = true; // Continue the bounded scan in the next request.
        }

        if (!$rawitems) {
            return [
                'items' => [],
                'nextcursor' => $hasmore ? self::encode_cursor($lastposition, $seen, $courseid, $unread) : '',
                'hasmore' => $hasmore,
            ];
        }

        $postids = array_map(static function(array $item): int {
            return (int)$item['post']->id;
        }, $rawitems);
        $postswithmessage = $DB->get_records_list('forum_posts', 'id', $postids, '', 'id,message,messageformat');
        $userids = array_unique(array_map(static function(array $item): int {
            return (int)$item['post']->userid;
        }, $rawitems));
        $authors = $DB->get_records_list('user', 'id', $userids);
        $urlfactory = \mod_forum\local\container::get_url_factory();

        $items = [];
        foreach ($rawitems as $item) {
            $post = $item['post'];
            $discussion = $item['discussion'];
            $context = $item['context'];
            $course = $item['course'];
            $forum = $item['forum'];
            $url = $urlfactory->get_view_post_url_from_post_id((int)$discussion->id, (int)$post->id);
            $url->param('targetpost', (int)$post->id);
            $preview = '';
            if (isset($postswithmessage[$post->id])) {
                $plain = html_to_text($postswithmessage[$post->id]->message, 0, false);
                $plain = preg_replace('/\s+/u', ' ', trim($plain));
                $preview = \core_text::substr($plain ?? '', 0, self::SNIPPET_LENGTH);
            }
            $items[] = [
                'discussionid' => (int)$discussion->id,
                'postid' => (int)$post->id,
                'courseid' => (int)$course->id,
                'coursename' => format_string($course->fullname, true,
                    ['context' => \context_course::instance($course->id)]),
                'forumname' => format_string($forum->name, true, ['context' => $context]),
                'subject' => format_string($discussion->name, true, ['context' => $context]),
                'author' => isset($authors[$post->userid]) ? fullname($authors[$post->userid]) : '',
                'time' => userdate($post->created, get_string('strftimedatetime', 'langconfig')),
                'preview' => $preview,
                'url' => $url->out(false),
                'unread' => (bool)$item['unread'],
            ];
        }

        $nextcursor = $hasmore ? self::encode_cursor($lastposition, $seen, $courseid, $unread) : '';
        return [
            'items' => $items,
            'nextcursor' => $nextcursor,
            'hasmore' => $nextcursor !== '',
        ];
    }
}
