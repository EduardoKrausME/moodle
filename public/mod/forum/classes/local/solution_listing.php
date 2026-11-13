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

use mod_forum\local\entities\forum as forum_entity;

/**
 * Resolve accepted solutions for user-visible native forum summaries.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class solution_listing {
    private const CHUNK_SIZE = 80;
    private const MAX_FILTER_CANDIDATES = 2400;

    /**
     * Resolve only visible accepted answers in one batch.
     *
     * @param forum_entity $forum Current forum.
     * @param \stdClass $user Current viewer.
     * @param array $summaries Native discussion summaries.
     * @return array Visible solution metadata indexed by discussion ID.
     */
    public static function visible_for_summaries(forum_entity $forum,
            \stdClass $user, array $summaries): array {
        global $DB, $CFG;

        if (!$summaries) {
            return [];
        }
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $ids = array_map(static fn($s) => $s->get_discussion()->get_id(), $summaries);
        $records = $DB->get_records_list('forum_discussions', 'id', $ids);
        if (!$records) {
            return [];
        }
        $firstpostids = array_map(static fn($d) => (int)$d->firstpost, $records);
        $firstposts = $DB->get_records_list('forum_posts', 'id', $firstpostids);
        $forumrecord = $DB->get_record('forum', ['id' => $forum->get_id()], '*', MUST_EXIST);
        if (empty($forumrecord->allowsolutions)) {
            return [];
        }
        $course = $forum->get_course_record();
        $cmrecord = $forum->get_course_module_record();
        $cm = get_fast_modinfo($course, $user->id)->get_cm($cmrecord->id);
        if (!$cm->uservisible) {
            return [];
        }
        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/forum:viewdiscussion', $context, $user)) {
            return [];
        }
        $visible = [];
        foreach ($records as $discussion) {
            $firstpost = $firstposts[$discussion->firstpost] ?? null;
            if (!$firstpost ||
                    !forum_user_can_see_discussion($forumrecord, $discussion, $context, $user) ||
                    !forum_user_can_see_post($forumrecord, $discussion, $firstpost, $user, $cm)) {
                continue;
            }
            $visible[(int)$discussion->id] = [
                'discussion' => $discussion,
                'forum' => $forumrecord,
                'cm' => $cm,
            ];
        }
        return solution_manager::visible_for_page($visible);
    }

    /**
     * Filter before pagination using the exact viewer-specific accepted state.
     * A bounded full scan avoids misleading counts when rows are inaccessible.
     *
     * @param forum_entity $forum Forum.
     * @param \stdClass $user Viewer.
     * @param int|null $groupid Native group selection.
     * @param int|null $sortorder Native discussion sort.
     * @param int $pageno Zero-based page.
     * @param int $pagesize Positive page size.
     * @param string $filter accepted or unaccepted.
     * @return array Filtered summary entities and exact total.
     */
    public static function get_filtered_page(forum_entity $forum, \stdClass $user,
            ?int $groupid, ?int $sortorder, int $pageno, int $pagesize, string $filter): array {
        if (!in_array($filter, ['accepted', 'unaccepted'], true) || $pageno < 0 || $pagesize <= 0) {
            throw new \invalid_parameter_exception('Invalid accepted solution filter.');
        }
        $selected = [];
        $total = 0;
        $scanned = 0;
        $offset = $pageno * $pagesize;
        for ($batch = 0; ; $batch++) {
            $candidates = mod_forum_get_discussion_summaries(
                $forum, $user, $groupid, $sortorder, $batch, self::CHUNK_SIZE);
            if (!$candidates) {
                break;
            }
            if ($scanned + count($candidates) > self::MAX_FILTER_CANDIDATES) {
                // Do not present a partial list with a falsely exact total.
                throw new \moodle_exception('solutionfiltertoobroad', 'forum');
            }
            $scanned += count($candidates);
            $accepted = self::visible_for_summaries($forum, $user, $candidates);
            foreach ($candidates as $id => $summary) {
                $solutionvisible = isset($accepted[$summary->get_discussion()->get_id()]);
                if (($filter === 'accepted') !== $solutionvisible) {
                    continue;
                }
                if ($total >= $offset && count($selected) < $pagesize) {
                    $selected[$id] = $summary;
                }
                $total++;
            }
            if (count($candidates) < self::CHUNK_SIZE) {
                break;
            }
        }
        return ['discussions' => $selected, 'total' => $total];
    }
}
