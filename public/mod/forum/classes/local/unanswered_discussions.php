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
 * Read-only teacher queue for questions without a visible public response.
 *
 * All candidates are validated against native forum visibility after SQL
 * discovery. A bounded reply scan never invents a status when it is exhausted.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class unanswered_discussions {
    public const PAGE_SIZE = 20;
    private const BATCH_SIZE = 40;
    private const MAX_SCANNED = 1200;
    private const MAX_REPLY_ROWS = 6000;
    private const MAX_CURSOR_LENGTH = 1200;
    private const STATUSES = ['all', 'unanswered', 'awaiting', 'accepted', 'answered'];
    private const SORTS = ['oldest', 'newest'];

    /**
     * Return only activity modules in courses available to this teacher.
     *
     * @param int $courseid Optional course filter.
     * @param int $forumid Optional forum filter.
     * @return array Module map and forum records, both keyed by forum ID.
     */
    private static function sources(int $courseid = 0, int $forumid = 0): array {
        global $DB, $USER;

        $modules = [];
        foreach (recent_feed::get_accessible_courses($courseid) as $course) {
            foreach (get_fast_modinfo($course, $USER->id)->get_instances_of('forum') as $cm) {
                if (!$cm->uservisible || ($forumid && (int)$cm->instance !== $forumid)) {
                    continue;
                }
                $context = \context_module::instance($cm->id);
                if (!has_capability('mod/forum:viewunanswered', $context, $USER) ||
                        !has_capability('mod/forum:viewdiscussion', $context, $USER)) {
                    continue;
                }
                $modules[(int)$cm->instance] = [
                    'cm' => $cm,
                    'course' => $course,
                    'context' => $context,
                ];
            }
        }
        $forums = $modules ? $DB->get_records_list('forum', 'id', array_keys($modules)) : [];
        return [$modules, $forums];
    }

    /**
     * @return array Permitted course names for the filter.
     */
    public static function get_course_options(): array {
        [$modules] = self::sources();
        $courses = [];
        foreach ($modules as $module) {
            $course = $module['course'];
            if (!isset($courses[$course->id])) {
                $courses[$course->id] = [
                    'id' => (int)$course->id,
                    'name' => format_string($course->fullname, true, [
                        'context' => \context_course::instance($course->id),
                    ]),
                ];
            }
        }
        usort($courses, static fn($a, $b) => strcoll($a['name'], $b['name']));
        return array_values($courses);
    }

    /**
     * @param int $courseid Filter by an accessible course.
     * @return array Forum options.
     */
    public static function get_forum_options(int $courseid = 0): array {
        [$modules, $forums] = self::sources($courseid);
        $options = [];
        foreach ($forums as $forum) {
            $context = $modules[$forum->id]['context'];
            $options[] = [
                'id' => (int)$forum->id,
                'courseid' => (int)$forum->course,
                'name' => format_string($forum->name, true, ['context' => $context]),
            ];
        }
        usort($options, static fn($a, $b) => strcoll($a['name'], $b['name']));
        return $options;
    }

    /**
     * No cross-course group chooser: group filters are scoped to a course.
     *
     * @param int $courseid Course ID.
     * @param int $forumid Optional forum ID.
     * @return array Permitted groups.
     */
    public static function get_group_options(int $courseid, int $forumid = 0): array {
        global $DB, $USER;

        if ($courseid <= 0) {
            return [];
        }
        [$modules] = self::sources($courseid, $forumid);
        if (!$modules) {
            return [];
        }
        $groups = $DB->get_records('groups', ['courseid' => $courseid], 'name ASC', 'id,name');
        $options = [];
        foreach ($groups as $group) {
            foreach ($modules as $module) {
                $mode = groups_get_activity_groupmode($module['cm'], $module['course']);
                if ($mode === NOGROUPS) {
                    continue;
                }
                if ($mode === VISIBLEGROUPS ||
                        has_capability('moodle/site:accessallgroups', $module['context'], $USER) ||
                        groups_is_member($group->id, $USER->id)) {
                    $options[] = [
                        'id' => (int)$group->id,
                        'name' => format_string($group->name, true, [
                            'context' => \context_course::instance($courseid),
                        ]),
                    ];
                    break;
                }
            }
        }
        return $options;
    }

    /**
     * Cursor binds a position to the user and the complete filter set.
     *
     * @param string $cursor Cursor from a prior page.
     * @param array $filters Canonical filters.
     * @return array First post timestamp and discussion ID.
     */
    private static function parse_cursor(string $cursor, array $filters): array {
        global $USER;

        if ($cursor === '') {
            return ['time' => 0, 'id' => 0];
        }
        if (strlen($cursor) > self::MAX_CURSOR_LENGTH ||
                !preg_match('/^[A-Za-z0-9_-]+$/D', $cursor)) {
            throw new \invalid_parameter_exception('Invalid unanswered discussion cursor.');
        }
        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded) || ($decoded['v'] ?? null) !== 1 ||
                ($decoded['uid'] ?? null) !== (int)$USER->id ||
                ($decoded['filters'] ?? null) !== $filters ||
                !isset($decoded['time'], $decoded['id']) ||
                !is_int($decoded['time']) || !is_int($decoded['id']) ||
                $decoded['time'] <= 0 || $decoded['id'] <= 0) {
            throw new \invalid_parameter_exception('Invalid unanswered discussion cursor.');
        }
        return ['time' => $decoded['time'], 'id' => $decoded['id']];
    }

    /**
     * @param array $position Last consumed candidate.
     * @param array $filters Bound filters.
     * @return string Opaque continuation.
     */
    private static function encode_cursor(array $position, array $filters): string {
        global $USER;

        if (!$position['time'] || !$position['id']) {
            return '';
        }
        $json = json_encode([
            'v' => 1,
            'uid' => (int)$USER->id,
            'filters' => $filters,
            'time' => (int)$position['time'],
            'id' => (int)$position['id'],
        ]);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Aggregate eligible replies for a bounded set of authorised discussions.
     * Private replies, deleted replies, and self-replies are not answers.
     * A truncated scan never labels an unresolved discussion as unanswered.
     *
     * @param array $discussions Map of visible discussion data.
     * @param array $modules Authorised activity modules.
     * @param array $forums Forum records.
     * @return array Reply flags and IDs with incomplete scans.
     */
    private static function visible_replies(array $discussions, array $modules, array $forums): array {
        global $DB, $USER;

        if (!$discussions) {
            return [[], []];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($discussions), SQL_PARAMS_NAMED, 'pendingdiscussion');
        $where = "p.discussion {$insql} AND p.id <> d.firstpost AND p.deleted = 0
                  AND p.privatereplyto = 0 AND p.userid <> d.userid";
        $potential = $DB->get_fieldset_sql(
            "SELECT DISTINCT p.discussion
               FROM {forum_posts} p JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE {$where}", $params);
        $potential = array_fill_keys(array_map('intval', $potential), true);
        if (!$potential) {
            return [[], []];
        }

        $seen = [];
        $scanned = 0;
        $limited = false;
        $records = $DB->get_recordset_sql(
            "SELECT p.id, p.discussion, p.parent, p.userid, p.created, p.modified,
                    p.deleted, p.privatereplyto, p.mailnow
               FROM {forum_posts} p JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE {$where} ORDER BY p.id ASC", $params, 0, self::MAX_REPLY_ROWS + 1);
        foreach ($records as $post) {
            if (++$scanned > self::MAX_REPLY_ROWS) {
                $limited = true;
                break;
            }
            $id = (int)$post->discussion;
            if (isset($seen[$id]) || !isset($discussions[$id])) {
                continue;
            }
            $item = $discussions[$id];
            $forumid = (int)$item['discussion']->forum;
            if (forum_user_can_see_post($forums[$forumid], $item['discussion'], $post,
                    $USER, $modules[$forumid]['cm'])) {
                $seen[$id] = true;
            }
        }
        $records->close();

        $unknown = [];
        if ($limited) {
            foreach ($potential as $id => $yes) {
                if (!isset($seen[$id])) {
                    $unknown[$id] = true;
                }
            }
        }
        return [$seen, $unknown];
    }

    /**
     * Page of discussions visible to this teacher, with current statuses.
     *
     * The returned counters deliberately describe this page only.
     *
     * @param int $courseid Course filter.
     * @param int $forumid Forum filter.
     * @param int $groupid Group filter, only available in a chosen course.
     * @param string $status One of the declared classifications.
     * @param string $sort Stable creation-time order.
     * @param string $cursor Opaque keyset cursor.
     * @return array Template and external API result.
     */
    public static function get_page(int $courseid = 0, int $forumid = 0, int $groupid = 0,
            string $status = 'all', string $sort = 'oldest', string $cursor = ''): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/mod/forum/lib.php');
        require_login();
        if (isguestuser() || $courseid < 0 || $forumid < 0 || $groupid < 0 ||
                ($groupid > 0 && $courseid === 0) ||
                !in_array($status, self::STATUSES, true) ||
                !in_array($sort, self::SORTS, true)) {
            throw new \invalid_parameter_exception('Invalid unanswered discussion filters.');
        }
        $filters = [$courseid, $forumid, $groupid, $status, $sort];
        $position = self::parse_cursor($cursor, $filters);
        $empty = [
            'items' => [], 'nextcursor' => '', 'hasmore' => false,
            'counts' => ['unanswered' => 0, 'awaiting' => 0, 'accepted' => 0, 'answered' => 0],
        ];
        [$modules, $forums] = self::sources($courseid, $forumid);
        if (!$forums) {
            return $empty;
        }
        if ($groupid && !in_array($groupid, array_column(
                self::get_group_options($courseid, $forumid), 'id'), true)) {
            return $empty;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($forums), SQL_PARAMS_NAMED, 'pendingforum');
        $params['timestampnow'] = time();
        $sql = "SELECT d.*, fp.created AS started
                  FROM {forum_discussions} d
                  JOIN {forum_posts} fp ON fp.id = d.firstpost
                 WHERE d.forum {$insql} AND fp.deleted = 0 AND fp.created <= :timestampnow";
        if ($groupid) {
            $sql .= ' AND (d.groupid = :pendinggroup OR d.groupid = -1)';
            $params['pendinggroup'] = $groupid;
        }

        $direction = $sort === 'oldest' ? 'ASC' : 'DESC';
        $last = $position;
        $scanned = 0;
        $exhausted = false;
        $hasmore = false;
        $items = [];
        $counts = $empty['counts'];
        while ($scanned < self::MAX_SCANNED) {
            $limit = min(self::BATCH_SIZE, self::MAX_SCANNED - $scanned);
            $seekparams = $params;
            $seek = '';
            if ($last['id']) {
                $operator = $direction === 'ASC' ? '>' : '<';
                $seek = " AND (fp.created {$operator} :seektime OR
                             (fp.created = :seekequal AND d.id {$operator} :seekid))";
                $seekparams['seektime'] = $last['time'];
                $seekparams['seekequal'] = $last['time'];
                $seekparams['seekid'] = $last['id'];
            }
            $candidates = $DB->get_records_sql($sql . $seek .
                " ORDER BY fp.created {$direction}, d.id {$direction}", $seekparams, 0, $limit);
            if (!$candidates) {
                $exhausted = true;
                break;
            }
            $firstposts = $DB->get_records_list('forum_posts', 'id',
                array_values(array_map(static fn($d) => (int)$d->firstpost, $candidates)));
            $visible = [];
            foreach ($candidates as $discussion) {
                $forumidforpost = (int)$discussion->forum;
                $forum = $forums[$forumidforpost];
                $context = $modules[$forumidforpost];
                $firstpost = $firstposts[$discussion->firstpost] ?? null;
                if ($groupid && groups_get_activity_groupmode($context['cm'],
                        $context['course']) === NOGROUPS) {
                    continue;
                }
                if (!$firstpost || !forum_user_can_see_discussion($forum, $discussion,
                        $context['context'], $USER) ||
                        !forum_user_can_see_post($forum, $discussion, $firstpost,
                            $USER, $context['cm'])) {
                    continue;
                }
                $visible[(int)$discussion->id] = [
                    'discussion' => $discussion,
                    'firstpost' => $firstpost,
                    'forum' => $forum,
                    'cm' => $context['cm'],
                ];
            }
            [$replies, $unknown] = self::visible_replies($visible, $modules, $forums);
            $solutions = solution_manager::visible_for_page($visible);
            $authors = $visible ? $DB->get_records_list('user', 'id',
                array_unique(array_map(static fn($x) => (int)$x['discussion']->userid, $visible))) : [];

            foreach ($candidates as $discussion) {
                $scanned++;
                $id = (int)$discussion->id;
                // An accessible accepted post determines the outcome without
                // requiring a complete scan of unrelated replies.
                if (isset($visible[$id]) && (!isset($unknown[$id]) || isset($solutions[$id]))) {
                    $forum = $visible[$id]['forum'];
                    $module = $modules[(int)$discussion->forum];
                    $accepted = isset($solutions[$id]);
                    $answered = isset($replies[$id]);
                    // An accepted reply takes precedence, even when authored by the starter.
                    $classification = $accepted ? 'accepted' : ($answered ?
                        (!empty($forum->allowsolutions) ? 'awaiting' : 'answered') : 'unanswered');
                    if ($status === 'all' || $status === $classification) {
                        // Look ahead without consuming a discussion from the next page.
                        if (count($items) === self::PAGE_SIZE) {
                            $hasmore = true;
                            break 2;
                        }
                        $created = (int)$visible[$id]['firstpost']->created;
                        $replyurl = '';
                        if (forum_user_can_post($forum, $discussion, $USER,
                                $module['cm'], $module['course'], $module['context'])) {
                            $replyurl = (new \moodle_url('/mod/forum/post.php', [
                                'reply' => (int)$discussion->firstpost,
                            ]))->out(false);
                        }
                        $items[] = [
                            'discussionid' => $id,
                            'courseid' => (int)$module['course']->id,
                            'forumid' => (int)$forum->id,
                            'subject' => format_string($discussion->name, true,
                                ['context' => $module['context']]),
                            'coursename' => format_string($module['course']->fullname, true,
                                ['context' => \context_course::instance($module['course']->id)]),
                            'forumname' => format_string($forum->name, true,
                                ['context' => $module['context']]),
                            'author' => isset($authors[$discussion->userid]) ?
                                fullname($authors[$discussion->userid]) : '',
                            'created' => userdate($created, get_string('strftimedatetime', 'langconfig')),
                            'waitinghours' => max(0, (int)floor((time() - $created) / HOURSECS)),
                            'status' => $classification,
                            'statuslabel' => get_string('unansweredstatus_' . $classification, 'mod_forum'),
                            'url' => (new \moodle_url('/mod/forum/discuss.php', ['d' => $id]))->out(false),
                            'replyurl' => $replyurl,
                            'canreply' => $replyurl !== '',
                        ];
                        $counts[$classification]++;
                    }
                }
                $last = ['time' => (int)$discussion->started, 'id' => $id];
            }
            if (count($candidates) < $limit) {
                $exhausted = true;
                break;
            }
        }
        if (!$hasmore && !$exhausted && $scanned >= self::MAX_SCANNED) {
            $hasmore = true;
        }
        $nextcursor = $hasmore ? self::encode_cursor($last, $filters) : '';
        return [
            'items' => $items,
            'counts' => $counts,
            'nextcursor' => $nextcursor,
            'hasmore' => $hasmore && $nextcursor !== '',
        ];
    }
}
