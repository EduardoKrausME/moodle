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
 * Aggregate visited chapters for the selected enrolled users in one query.
 * Results measure chapter visits, not time spent or learning completion.
 *
 * @package mod_book
 */
final class reading_metrics {
    /**
     * @param int $bookid
     * @param array $chapters Book chapters, including hidden chapters.
     * @param array $userids Users in the requested report page.
     * @return array Associative array of metrics keyed by user id.
     */
    public static function summarize(int $bookid, array $chapters, array $userids): array {
        global $DB;

        $visible = [];
        foreach ($chapters as $chapter) {
            if (!$chapter->hidden) {
                $visible[(int) $chapter->id] = true;
            }
        }
        $total = count($visible);
        $data = [];
        foreach ($userids as $userid) {
            $data[(int) $userid] = [
                'visited' => 0, 'total' => $total, 'percent' => 0,
                'lastchapterid' => 0, 'lastviewed' => 0,
            ];
        }
        if (!$data || !$visible) {
            return $data;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal(array_keys($data), SQL_PARAMS_NAMED, 'bu');
        [$chaptersql, $chapterparams] = $DB->get_in_or_equal(array_keys($visible), SQL_PARAMS_NAMED, 'bc');
        $sql = "SELECT r.id, r.userid, r.chapterid, r.lastviewed
                  FROM {book_chapter_reads} r
                 WHERE r.bookid = :bookid AND r.userid $usersql AND r.chapterid $chaptersql";
        $records = $DB->get_recordset_sql($sql, ['bookid' => $bookid] + $userparams + $chapterparams);
        foreach ($records as $record) {
            $userid = (int) $record->userid;
            $data[$userid]['visited']++;
            if ($record->lastviewed > $data[$userid]['lastviewed']) {
                $data[$userid]['lastviewed'] = (int) $record->lastviewed;
                $data[$userid]['lastchapterid'] = (int) $record->chapterid;
            }
        }
        $records->close();
        foreach ($data as &$stats) {
            $stats['percent'] = $total ? (int) round($stats['visited'] * 100 / $total) : 0;
        }
        unset($stats);
        return $data;
    }
}
