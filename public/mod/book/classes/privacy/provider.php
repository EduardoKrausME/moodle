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

namespace mod_book\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy implementation for the authenticated users' chapter reading history.
 *
 * @package mod_book
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe all stored personal reading data.
     *
     * @param collection $collection Privacy metadata collector.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('book_chapter_reads', [
            'bookid' => 'privacy:metadata:book_chapter_reads:bookid',
            'chapterid' => 'privacy:metadata:book_chapter_reads:chapterid',
            'userid' => 'privacy:metadata:book_chapter_reads:userid',
            'firstviewed' => 'privacy:metadata:book_chapter_reads:firstviewed',
            'lastviewed' => 'privacy:metadata:book_chapter_reads:lastviewed',
        ], 'privacy:metadata:book_chapter_reads');
        return $collection;
    }

    /**
     * Get Book module contexts containing the user's chapter history.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT DISTINCT ctx.id
                  FROM {book_chapter_reads} r
                  JOIN {book} b ON b.id = r.bookid
                  JOIN {course_modules} cm ON cm.instance = b.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE r.userid = :userid";
        $contexts = new contextlist();
        $contexts->add_from_sql($sql, [
            'modname' => 'book',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ]);
        return $contexts;
    }

    /**
     * Find users with reading history in a single module context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $bookid = self::get_bookid_for_context($userlist->get_context());
        if (!$bookid) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {book_chapter_reads} WHERE bookid = :bookid",
            ['bookid' => $bookid]);
    }

    /**
     * Export the list of chapters read and associated timestamps.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $bookid = self::get_bookid_for_context($context);
            if (!$bookid) {
                continue;
            }

            $sql = "SELECT r.id, r.firstviewed, r.lastviewed, ch.title
                      FROM {book_chapter_reads} r
                      JOIN {book_chapters} ch ON ch.id = r.chapterid AND ch.bookid = r.bookid
                     WHERE r.bookid = :bookid AND r.userid = :userid
                  ORDER BY r.firstviewed ASC, r.id ASC";
            $readings = $DB->get_records_sql($sql, ['bookid' => $bookid, 'userid' => $userid]);
            if (!$readings) {
                continue;
            }
            $chapters = [];
            foreach ($readings as $record) {
                $chapters[] = (object) [
                    'title' => $record->title,
                    'firstviewed' => transform::datetime((int) $record->firstviewed),
                    'lastviewed' => transform::datetime((int) floor($record->lastviewed / 1000)),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('readingprogress', 'mod_book')],
                (object) ['chapters' => $chapters]
            );
        }
    }

    /**
     * Erase all reading history belonging to one Book context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        $bookid = self::get_bookid_for_context($context);
        if ($bookid) {
            $DB->delete_records('book_chapter_reads', ['bookid' => $bookid]);
        }
    }

    /**
     * Erase one user's data in all approved Book contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $bookid = self::get_bookid_for_context($context);
            if ($bookid) {
                $DB->delete_records('book_chapter_reads', ['bookid' => $bookid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Erase selected users' data in a specified Book context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $bookid = self::get_bookid_for_context($userlist->get_context());
        if (!$bookid) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('book_chapter_reads', ['bookid' => $bookid, 'userid' => $userid]);
        }
    }

    /**
     * Resolve the Book instance only when the context belongs to mod_book.
     *
     * @param \context $context
     * @return int|null
     */
    private static function get_bookid_for_context(\context $context): ?int {
        if (!$context instanceof \context_module) {
            return null;
        }
        $cm = get_coursemodule_from_id('book', $context->instanceid);
        return $cm ? (int) $cm->instance : null;
    }
}
