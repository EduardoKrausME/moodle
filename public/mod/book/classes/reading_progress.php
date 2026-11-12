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

namespace mod_book;

/**
 * Tracks chapters actually opened by an authenticated Book reader.
 *
 * Reading a chapter marks it as visited, not completed. The activity's existing
 * completion criteria are unchanged.
 *
 * @package mod_book
 */
final class reading_progress {
    /**
     * Record a deliberate chapter view.
     *
     * @param int $bookid Book instance.
     * @param int $chapterid Chapter in that book.
     * @param int $userid Authenticated user.
     */
    public static function record_view(int $bookid, int $chapterid, int $userid): void {
        global $DB;

        if ($userid <= 0) {
            return;
        }

        $conditions = [
            'bookid' => $bookid,
            'chapterid' => $chapterid,
            'userid' => $userid,
        ];
        $now = time();
        // Use milliseconds and a monotonically increasing position within this reader's book.
        // Page changes within the same millisecond must still preserve their actual order.
        $previouslast = (int) $DB->get_field_sql(
            'SELECT MAX(lastviewed) FROM {book_chapter_reads} WHERE bookid = :bookid AND userid = :userid',
            ['bookid' => $bookid, 'userid' => $userid]
        );
        $lastviewed = max((int) floor(microtime(true) * 1000), $previouslast + 1);
        $record = $DB->get_record('book_chapter_reads', $conditions, 'id, lastviewed');

        if ($record) {
            $DB->update_record('book_chapter_reads', (object) [
                'id' => $record->id,
                'lastviewed' => $lastviewed,
            ]);
            return;
        }

        $DB->insert_record('book_chapter_reads', (object) ($conditions + [
            'firstviewed' => $now,
            'lastviewed' => $lastviewed,
        ]));
    }

    /**
     * Return the current reader's status for accessible chapters only.
     *
     * @param int $bookid Book instance.
     * @param array $chapters Chapters from book_preload_chapters().
     * @param int $userid Reader ID.
     * @param bool $viewhidden Whether the reader can see hidden chapters.
     * @return array Status: visitedids, visitedcount, totalcount, percent and lastchapterid.
     */
    public static function get_progress(int $bookid, array $chapters, int $userid,
            bool $viewhidden = false): array {
        global $DB;

        $visibleids = [];
        foreach ($chapters as $chapter) {
            if (!$chapter->hidden || $viewhidden) {
                $visibleids[$chapter->id] = true;
            }
        }

        $result = [
            'visitedids' => [],
            'visitedcount' => 0,
            'totalcount' => count($visibleids),
            'percent' => 0,
            'lastchapterid' => 0,
        ];

        if ($userid <= 0 || !$visibleids) {
            return $result;
        }

        $records = $DB->get_records('book_chapter_reads', [
            'bookid' => $bookid,
            'userid' => $userid,
        ], 'lastviewed DESC, id DESC', 'id, chapterid, lastviewed');

        foreach ($records as $record) {
            if (!isset($visibleids[$record->chapterid])) {
                continue;
            }
            if (!$result['lastchapterid']) {
                $result['lastchapterid'] = (int) $record->chapterid;
            }
            $result['visitedids'][$record->chapterid] = true;
        }

        $result['visitedcount'] = count($result['visitedids']);
        $result['percent'] = (int) round(100 * $result['visitedcount'] / $result['totalcount']);
        return $result;
    }
}
