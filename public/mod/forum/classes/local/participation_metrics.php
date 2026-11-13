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
 * Bounded, permission-aware participation metrics. No personal statistics are stored.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participation_metrics {
    private const MAX_PERIOD_POSTS = 6000;
    private const MAX_COHORT_REPLIES = 6000;
    private const DB_BATCH = 150;

    /**
     * Load complete discussion records in database-portable batches.
     *
     * @param string $table Database table.
     * @param string $field Filter field.
     * @param int[] $ids Requested identifiers.
     * @return array Records keyed by their Moodle primary ID.
     */
    private static function records(string $table, string $field, array $ids): array {
        global $DB;

        $result = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $ids))), self::DB_BATCH) as $batch) {
            $result += $DB->get_records_list($table, $field, $batch);
        }
        return $result;
    }

    /**
     * Fetch public candidate posts without trusting SQL as a permission check.
     *
     * @param int[] $forumids Authorised forums.
     * @param int $groupid Selected permitted group or zero.
     * @param int|null $from Inclusive creation timestamp; null for cohort replies.
     * @param int|null $to Exclusive creation timestamp.
     * @param int[] $discussionids Optional cohort discussion IDs.
     * @param int $maximum Maximum records before throwing.
     * @return array Full post records keyed by ID.
     */
    private static function candidate_posts(array $forumids, int $groupid, ?int $from, ?int $to,
            array $discussionids, int $maximum): array {
        global $DB;

        $all = [];
        $baseparams = [];
        $where = 'p.deleted = 0 AND p.privatereplyto = 0';
        if ($from !== null && $to !== null) {
            $where .= ' AND p.created >= :participationfrom AND p.created < :participationto';
            $baseparams['participationfrom'] = $from;
            $baseparams['participationto'] = $to;
        }
        if ($groupid) {
            $where .= ' AND (d.groupid = :participationgroup OR d.groupid = -1)';
            $baseparams['participationgroup'] = $groupid;
        }

        $ids = $discussionids ?: $forumids;
        foreach (array_chunk($ids, self::DB_BATCH) as $batch) {
            [$insql, $params] = $DB->get_in_or_equal($batch, SQL_PARAMS_NAMED, 'participationitem');
            $field = $discussionids ? 'd.id' : 'd.forum';
            $conditions = "{$where} AND {$field} {$insql}";
            if ($discussionids) {
                $conditions .= ' AND p.id <> d.firstpost AND p.userid <> d.userid';
            }
            $rows = $DB->get_records_sql(
                "SELECT p.*
                   FROM {forum_posts} p
                   JOIN {forum_discussions} d ON d.id = p.discussion
                  WHERE {$conditions} ORDER BY p.created ASC, p.id ASC",
                $baseparams + $params, 0, $maximum - count($all) + 1);
            $all += $rows;
            if (count($all) > $maximum) {
                throw new \moodle_exception('participationlimit', 'mod_forum');
            }
        }
        return $all;
    }

    /**
     * Calculate a cross-forum report from already permission-checked activity modules.
     *
     * @param array $modules Forum ID => cm, course, context, forum.
     * @param int $groupid Valid group, zero for all groups.
     * @param int $from Inclusive epoch timestamp.
     * @param int $to Exclusive epoch timestamp.
     * @param string $fromdate User-local first day, ISO format.
     * @param string $todate User-local last day, ISO format.
     * @return array Safe numerical dashboard data.
     */
    public static function calculate(array $modules, int $groupid, int $from, int $to,
            string $fromdate, string $todate): array {
        global $USER, $CFG;

        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $zero = [
            'discussions' => 0, 'replies' => 0, 'participants' => 0, 'responded' => 0,
            'coverage' => 0.0, 'medianhours' => 0.0, 'accepted' => 0,
        ];
        if (!$modules) {
            return ['metrics' => $zero, 'forums' => [], 'series' => []];
        }

        $forums = [];
        foreach ($modules as $id => $item) {
            $forums[$id] = $zero + [
                'id' => (int)$id,
                'name' => format_string($item['forum']->name, true, ['context' => $item['context']]),
                'url' => (new \moodle_url('/mod/forum/view.php', ['id' => $item['cm']->id]))->out(false),
                'summaryurl' => has_capability('forumreport/summary:view', $item['context'], $USER) ?
                    (new \moodle_url('/mod/forum/report/summary/index.php', [
                        'courseid' => $item['course']->id, 'forumid' => $id,
                    ]))->out(false) : '',
                'queueurl' => has_capability('mod/forum:viewunanswered', $item['context'], $USER) ?
                    (new \moodle_url('/mod/forum/unanswered.php', [
                        'courseid' => $item['course']->id, 'forumid' => $id,
                    ]))->out(false) : '',
            ];
        }

        // Candidate SQL excludes deleted/private posts, but Moodle visibility is checked below.
        $periodposts = self::candidate_posts(array_keys($modules), $groupid,
            $from, $to, [], self::MAX_PERIOD_POSTS);
        $discussions = self::records('forum_discussions', 'id',
            array_map(static fn($p) => (int)$p->discussion, $periodposts));
        $firstposts = self::records('forum_posts', 'id',
            array_map(static fn($d) => (int)$d->firstpost, $discussions));
        $visible = [];
        foreach ($discussions as $id => $discussion) {
            $forumid = (int)$discussion->forum;
            if (!isset($modules[$forumid], $firstposts[$discussion->firstpost])) {
                continue;
            }
            $module = $modules[$forumid];
            $start = $firstposts[$discussion->firstpost];
            if (!empty($start->deleted) ||
                    !forum_user_can_see_discussion($module['forum'], $discussion, $module['context'], $USER) ||
                    !forum_user_can_see_post($module['forum'], $discussion, $start, $USER, $module['cm'])) {
                continue;
            }
            $visible[$id] = [
                'discussion' => $discussion,
                'forum' => $module['forum'],
                'cm' => $module['cm'],
            ];
        }

        $timezone = new \DateTimeZone(\core_date::get_user_timezone($USER));
        $firstday = new \DateTimeImmutable($fromdate, $timezone);
        $lastday = new \DateTimeImmutable($todate, $timezone);
        $days = (int)$firstday->diff($lastday)->days + 1;
        $interval = $days > 31 ? 7 : 1;
        $series = [];
        for ($i = 0; $i < $days; $i += $interval) {
            $date = $firstday->modify('+' . $i . ' days');
            $series[] = [
                'label' => $date->format('d/m'),
                'discussions' => 0,
                'replies' => 0,
            ];
        }
        $metrics = $zero;
        $participants = [];
        $forumparticipants = [];
        $cohorts = [];

        foreach ($periodposts as $post) {
            $id = (int)$post->discussion;
            if (!isset($visible[$id])) {
                continue;
            }
            $context = $visible[$id];
            if (!forum_user_can_see_post($context['forum'],
                    $context['discussion'], $post, $USER, $context['cm'])) {
                continue;
            }
            $forumid = (int)$context['discussion']->forum;
            $participants[(int)$post->userid] = true;
            $forumparticipants[$forumid][(int)$post->userid] = true;
            $localday = (new \DateTimeImmutable('@' . $post->created))->setTimezone($timezone);
            $seriesindex = intdiv((int)$firstday->diff(
                new \DateTimeImmutable($localday->format('Y-m-d'), $timezone))->days, $interval);

            if ((int)$post->id === (int)$context['discussion']->firstpost) {
                $cohorts[$id] = $context;
                $metrics['discussions']++;
                $forums[$forumid]['discussions']++;
                $series[$seriesindex]['discussions']++;
            } else {
                $metrics['replies']++;
                $forums[$forumid]['replies']++;
                $series[$seriesindex]['replies']++;
            }
        }

        $metrics['participants'] = count($participants);
        foreach ($forums as $forumid => &$row) {
            $row['participants'] = count($forumparticipants[$forumid] ?? []);
        }
        unset($row);

        // Cohort means discussions started in the requested interval. Replies
        // can arrive after its end; this is a present-day response coverage measure.
        $cohortreplies = self::candidate_posts([], $groupid, null, null,
            array_keys($cohorts), self::MAX_COHORT_REPLIES);
        $firstresponses = [];
        foreach ($cohortreplies as $post) {
            $id = (int)$post->discussion;
            if (!isset($cohorts[$id])) {
                continue;
            }
            $item = $cohorts[$id];
            if (forum_user_can_see_post($item['forum'], $item['discussion'],
                    $post, $USER, $item['cm'])) {
                $firstresponses[$id] = isset($firstresponses[$id]) ?
                    min($firstresponses[$id], (int)$post->created) : (int)$post->created;
            }
        }

        $elapsed = [];
        $elapsedbyforum = [];
        foreach ($firstresponses as $id => $respondedat) {
            $forumid = (int)$cohorts[$id]['discussion']->forum;
            $metrics['responded']++;
            $forums[$forumid]['responded']++;
            $firstpost = $firstposts[$cohorts[$id]['discussion']->firstpost];
            $duration = max(0, $respondedat - (int)$firstpost->created);
            $elapsed[] = $duration;
            $elapsedbyforum[$forumid][] = $duration;
        }
        sort($elapsed, SORT_NUMERIC);
        if ($elapsed) {
            $middle = intdiv(count($elapsed), 2);
            $median = count($elapsed) % 2 ?
                $elapsed[$middle] : ($elapsed[$middle - 1] + $elapsed[$middle]) / 2;
            $metrics['medianhours'] = round($median / HOURSECS, 1);
        }
        $metrics['coverage'] = $metrics['discussions'] ?
            round(100 * $metrics['responded'] / $metrics['discussions'], 1) : 0.0;

        // Only the accepted reply visible to this viewer is counted.
        foreach (array_chunk($cohorts, self::DB_BATCH, true) as $batch) {
            $solutions = solution_manager::visible_for_page($batch);
            foreach ($solutions as $id => $solution) {
                $forumid = (int)$cohorts[$id]['discussion']->forum;
                $metrics['accepted']++;
                $forums[$forumid]['accepted']++;
            }
        }

        foreach ($forums as &$row) {
            $row['coverage'] = $row['discussions'] ?
                round(100 * $row['responded'] / $row['discussions'], 1) : 0.0;
            $times = $elapsedbyforum[$row['id']] ?? [];
            sort($times, SORT_NUMERIC);
            if ($times) {
                $middle = intdiv(count($times), 2);
                $median = count($times) % 2 ?
                    $times[$middle] : ($times[$middle - 1] + $times[$middle]) / 2;
                $row['medianhours'] = round($median / HOURSECS, 1);
            }
        }
        unset($row);

        $peak = max(1, ...array_map(static fn($row) =>
            max($row['discussions'], $row['replies']), $series));
        foreach ($series as &$row) {
            $row['discussionwidth'] = (int)round(100 * $row['discussions'] / $peak);
            $row['replywidth'] = (int)round(100 * $row['replies'] / $peak);
        }
        unset($row);

        return ['metrics' => $metrics, 'forums' => array_values($forums), 'series' => $series];
    }
}
