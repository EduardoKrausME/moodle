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

use moodle_exception;

/**
 * Private per-user chapter bookmarks and plain-text notes.
 *
 * @package mod_book
 */
final class reading_tools {
    public const MAX_NOTE_LENGTH = 5000;

    /**
     * Return the reader's own state for one chapter.
     *
     * @param int $bookid Book ID.
     * @param int $chapterid Chapter ID.
     * @param int $userid Current user.
     * @return array Bookmark and note.
     */
    public static function get(int $bookid, int $chapterid, int $userid): array {
        global $DB;

        $empty = ['favourite' => false, 'note' => ''];
        if ($userid <= 0) {
            return $empty;
        }
        $record = $DB->get_record('book_chapter_userdata', [
            'bookid' => $bookid, 'chapterid' => $chapterid, 'userid' => $userid,
        ], 'id, favourite, note');
        return $record ? [
            'favourite' => (bool) $record->favourite,
            'note' => $record->note,
        ] : $empty;
    }

    /**
     * Save one user's bookmark without modifying any existing note.
     *
     * @param int $bookid
     * @param int $chapterid
     * @param int $userid
     * @param bool $favourite New bookmark state.
     */
    public static function set_favourite(int $bookid, int $chapterid, int $userid, bool $favourite): void {
        self::save($bookid, $chapterid, $userid, $favourite, null);
    }

    /**
     * Save a plain-text personal annotation, preserving existing bookmark status.
     *
     * @param int $bookid
     * @param int $chapterid
     * @param int $userid
     * @param string $note New note.
     */
    public static function set_note(int $bookid, int $chapterid, int $userid, string $note): void {
        $note = trim(clean_param($note, PARAM_NOTAGS));
        if (\core_text::strlen($note) > self::MAX_NOTE_LENGTH) {
            throw new moodle_exception('notetoolong', 'mod_book');
        }
        self::save($bookid, $chapterid, $userid, null, $note);
    }

    /**
     * Return only the supplied user's bookmarks in the visible chapter set.
     *
     * @param int $bookid
     * @param int $userid
     * @param array $chapters Permitted chapters, keyed by chapter id.
     * @return array Bookmarked chapter IDs.
     */
    public static function get_favourites(int $bookid, int $userid, array $chapters): array {
        global $DB;

        if ($userid <= 0) {
            return [];
        }
        $records = $DB->get_records('book_chapter_userdata', [
            'bookid' => $bookid, 'userid' => $userid, 'favourite' => 1,
        ], '', 'id, chapterid');
        $result = [];
        foreach ($records as $record) {
            if (isset($chapters[$record->chapterid])) {
                $result[] = (int) $record->chapterid;
            }
        }
        return $result;
    }

    /**
     * Perform a partial update, dropping empty rows to avoid retaining stale data.
     *
     * @param int $bookid
     * @param int $chapterid
     * @param int $userid
     * @param bool|null $favourite Null means unchanged.
     * @param string|null $note Null means unchanged.
     */
    private static function save(int $bookid, int $chapterid, int $userid, ?bool $favourite,
            ?string $note): void {
        global $DB;
        if ($userid <= 0) {
            throw new moodle_exception('privateaccountrequired', 'mod_book');
        }
        $conditions = ['bookid' => $bookid, 'chapterid' => $chapterid, 'userid' => $userid];
        $record = $DB->get_record('book_chapter_userdata', $conditions);
        $newfavourite = $favourite === null ? (bool) ($record->favourite ?? false) : $favourite;
        $newnote = $note === null ? ($record->note ?? '') : $note;
        if (!$newfavourite && $newnote === '') {
            if ($record) {
                $DB->delete_records('book_chapter_userdata', ['id' => $record->id]);
            }
            return;
        }
        $now = time();
        if ($record) {
            $record->favourite = (int) $newfavourite;
            $record->note = $newnote;
            $record->timemodified = $now;
            $DB->update_record('book_chapter_userdata', $record);
        } else {
            $DB->insert_record('book_chapter_userdata', (object) ($conditions + [
                'favourite' => (int) $newfavourite,
                'note' => $newnote,
                'timecreated' => $now,
                'timemodified' => $now,
            ]));
        }
    }

    /**
     * Prefix spreadsheet formula-like values for safe CSV exports.
     *
     * @param string $value
     * @return string
     */
    public static function csv_safe(string $value): string {
        return preg_match('/^\s*[=+\-@]/u', $value) ? "'" . $value : $value;
    }
}
