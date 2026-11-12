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
    /** Maximum unread candidates checked per displayed page. */
    private const MAX_UNREAD_LOOKUP = 1800;
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
    private static function parse_cursor(string $cursor, int $courseid, bool $unread, bool $following): array {
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
                ($data['following'] ?? 0) !== (int)$following ||
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
    private static function encode_cursor(array $position, array $seen, int $courseid, bool $unread,
            bool $following): string {
        global $USER;

        if (count($seen) >= self::MAX_SEEN) {
            return '';
        }
        $json = json_encode([
            'v' => 1,
            'uid' => (int)$USER->id,
            'course' => $courseid,
            'unread' => (int)$unread,
            'following' => (int)$following,
            'time' => (int)$position['time'],
            'post' => (int)$position['post'],
            'seen' => array_map('intval', array_keys($seen)),
        ]);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }


    /**
     * Mark a topic as unread if any visible tracked post is unread, even when
     * its most recent visible reply was read already.
     *
     * The candidate scan is bounded and never marks a topic as unread unless
     * the underlying message passes the native forum visibility check.
     *
     * @param array $rawitems Page of discussion entries, updated in place.
     * @param array $modulemap Accessible module metadata indexed by forum ID.
     */
    private static function annotate_unread_discussions(array &$rawitems, array $modulemap): void {
        global $CFG, $DB, $USER;

        $pending = [];
        foreach ($rawitems as $index => $item) {
            if (!$item['unread'] && forum_tp_is_tracked($item['forum'], $USER)) {
                $pending[(int)$item['discussion']->id] = $index;
            }
        }
        if (!$pending) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($pending), SQL_PARAMS_NAMED, 'pendingtopic');
        $params['readuser'] = $USER->id;
        $params['cutoff'] = floor(time() / MINSECS) * MINSECS -
            ($CFG->forum_oldpostdays * DAYSECS);

        $sql = "SELECT p.id, p.discussion, p.userid, p.parent, p.created, p.modified,
                       p.privatereplyto, p.deleted
                  FROM {forum_posts} p
             LEFT JOIN {forum_read} r ON r.postid = p.id AND r.userid = :readuser
                 WHERE p.discussion {$insql} AND p.deleted = 0
                       AND p.modified >= :cutoff AND r.id IS NULL
              ORDER BY p.modified DESC, p.id DESC";

        $recordset = $DB->get_recordset_sql($sql, $params, 0, self::MAX_UNREAD_LOOKUP);
        foreach ($recordset as $post) {
            $discussionid = (int)$post->discussion;
            if (!isset($pending[$discussionid])) {
                continue;
            }
            $index = $pending[$discussionid];
            $item = $rawitems[$index];
            $cm = $modulemap[(int)$item['forum']->id]['cm'];
            if (forum_user_can_see_post($item['forum'], $item['discussion'], $post, $USER, $cm)) {
                $rawitems[$index]['unread'] = true;
                unset($pending[$discussionid]);
                if (!$pending) {
                    break;
                }
            }
        }
        $recordset->close();
    }

    /**
     * Return a safe page of recent forum discussion activity.
     *
     * @param int $courseid 0 means all accessible courses.
     * @param bool $unread Return only tracked, unread and visible posts.
     * @param string $cursor Continuation from the preceding page.
     * @return array Entries, nextcursor and hasmore.
     */
    public static function get_page(int $courseid = 0, bool $unread = false, string $cursor = '',
            bool $following = false): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        if ($unread && $following) {
            throw new \invalid_parameter_exception('Conflicting forum feed filters.');
        }
        $state = self::parse_cursor($cursor, $courseid, $unread, $following);
        $courses = self::get_accessible_courses($courseid);
        $empty = ['items' => [], 'nextcursor' => '', 'hasmore' => false];
        if (!$courses) {
            return $empty;
        }

        $modulemap = [];
        foreach ($courses as $course) {
            $modinfo = get_fast_modinfo((int)$course->id, $USER->id);
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
        // Filter favourites at SQL level, before keyset pagination and limits.
        // The user's native discussion stars do not affect email subscriptions.
        $favouritejoin = '';
        if ($following) {
            $favouritejoin = " JOIN {favourite} fav ON fav.itemid = d.id
                                    AND fav.userid = :followuserid
                                    AND fav.component = :followcomponent
                                    AND fav.itemtype = :followtype";
            $parameters['followuserid'] = $USER->id;
            $parameters['followcomponent'] = 'mod_forum';
            $parameters['followtype'] = 'discussions';
        }
        $sql = "SELECT p.id, p.discussion, p.userid, p.parent, p.created, p.modified,
                       p.privatereplyto, p.deleted, d.forum AS forumid, r.id AS readid
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                  {$favouritejoin}
             LEFT JOIN {forum_read} r ON r.postid = p.id AND r.userid = :readuser
                 WHERE d.forum {$insql} AND p.deleted = 0 {$filtersql}";

        $seen = $state['seen'];
        // Reuse discussion records across query batches when a busy topic has
        // many replies. Each record is still checked against post visibility.
        $discussioncache = [];
        $lastposition = ['time' => $state['time'], 'post' => $state['post']];
        $rawitems = [];
        $hasmore = false;
        $exhausted = false;
        $scanned = 0;

        // Keyset pagination: no OFFSET scans or one request per course.
        while ($scanned < self::MAX_SCANNED) {
            $limit = min(self::BATCH_SIZE, self::MAX_SCANNED - $scanned);
            $seekparams = $parameters;
            $seeksql = '';
            if ($lastposition['time'] > 0) {
                $seeksql = ' AND (p.created < :feedbefore OR (p.created = :feedequal AND p.id < :feedpost))';
                $seekparams['feedbefore'] = $lastposition['time'];
                $seekparams['feedequal'] = $lastposition['time'];
                $seekparams['feedpost'] = $lastposition['post'];
            }
            $posts = $DB->get_records_sql(
                $sql . $seeksql . ' ORDER BY p.created DESC, p.id DESC',
                $seekparams, 0, $limit);
            if (!$posts) {
                $exhausted = true;
                break;
            }

            $ids = array_unique(array_map('intval', array_column(array_values($posts), 'discussion')));
            $missing = array_values(array_diff($ids, array_keys($discussioncache)));
            if ($missing) {
                $discussioncache += $DB->get_records_list('forum_discussions', 'id', $missing);
            }
            foreach ($posts as $post) {
                $discussionid = (int)$post->discussion;
                $allowed = false;
                if (!isset($seen[$discussionid]) && isset($discussioncache[$discussionid], $forums[$post->forumid])) {
                    $forum = $forums[$post->forumid];
                    $context = $modulemap[$post->forumid];
                    $allowed = forum_user_can_see_post(
                        $forum, $discussioncache[$discussionid], $post, $USER, $context['cm']);
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
                    'discussion' => $discussioncache[$discussionid],
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

            // The next iteration uses the last processed (created, id) keyset.
        }

        if (!$hasmore && !$exhausted && $scanned >= self::MAX_SCANNED) {
            $hasmore = true; // Continue the bounded scan in the next request.
        }

        if (!$rawitems) {
            return [
                'items' => [],
                'nextcursor' => $hasmore ? self::encode_cursor($lastposition, $seen, $courseid, $unread, $following) : '',
                'hasmore' => $hasmore,
            ];
        }

        // A topic can have a read latest reply but an earlier unread message.
        if (!$unread) {
            self::annotate_unread_discussions($rawitems, $modulemap);
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

        // Single bounded lookup for the page, scoped to this user and the
        // actual forum context. Do not fetch one favourite per card.
        $discussionids = array_unique(array_map(static function(array $item): int {
            return (int)$item['discussion']->id;
        }, $rawitems));
        [$favinsql, $favparams] = $DB->get_in_or_equal(
            $discussionids, SQL_PARAMS_NAMED, 'favdiscussion');
        $favparams['favuserid'] = $USER->id;
        $favparams['favcomponent'] = 'mod_forum';
        $favparams['favtype'] = 'discussions';
        $records = $DB->get_recordset_sql(
            "SELECT f.itemid, f.contextid
               FROM {favourite} f
              WHERE f.userid = :favuserid AND f.component = :favcomponent
                    AND f.itemtype = :favtype AND f.itemid {$favinsql}",
            $favparams);
        $favourites = [];
        foreach ($records as $favourite) {
            $favourites[(int)$favourite->itemid][(int)$favourite->contextid] = true;
        }
        $records->close();

        $items = [];
        foreach ($rawitems as $item) {
            $post = $item['post'];
            $discussion = $item['discussion'];
            $context = $item['context'];
            $course = $item['course'];
            $forum = $item['forum'];
            $favourited = !empty($favourites[(int)$discussion->id][(int)$context->id]);
            if ($following && !$favourited) {
                // Ignore orphaned favourites referring to another context.
                continue;
            }
            $url = $urlfactory->get_view_post_url_from_post_id((int)$discussion->id, (int)$post->id);
            $url->param('targetpost', (int)$post->id);
            $preview = '';
            if (isset($postswithmessage[$post->id])) {
                $plain = html_to_text($postswithmessage[$post->id]->message, 0, false);
                $plain = preg_replace('/\s+/u', ' ', trim($plain));
                $preview = \core_text::substr($plain ?? '', 0, self::SNIPPET_LENGTH);
            }
            // A UI hint only: the reply endpoint revalidates every capability
            // and the exact parent post before any write.
            $canreply = empty($post->privatereplyto) && forum_user_can_post(
                $forum, $discussion, $USER, $modulemap[(int)$forum->id]['cm'], $course, $context);
            $advancedurl = new \moodle_url('/mod/forum/post.php', ['reply' => $post->id]);
            $items[] = [
                'canreply' => (bool)$canreply,
                'advancedurl' => $advancedurl->out(false),
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
                'favourited' => $favourited,
                'canfavourite' => has_capability('mod/forum:cantogglefavourite', $context, $USER),
            ];
        }

        $nextcursor = $hasmore ? self::encode_cursor($lastposition, $seen, $courseid, $unread, $following) : '';
        return [
            'items' => $items,
            'nextcursor' => $nextcursor,
            'hasmore' => $nextcursor !== '',
        ];
    }
}
