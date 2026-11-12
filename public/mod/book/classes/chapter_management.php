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

use context_module;
use moodle_exception;
use stdClass;

/**
 * Safe chapter management operations used by the book editing UI.
 *
 * @package mod_book
 */
final class chapter_management {

    /**
     * Validate the complete requested hierarchy before writing anything.
     *
     * @param array $chapters Chapter records keyed by id.
     * @param array $groups Ordered [{id: chapterid, children: [chapterids]}].
     * @return array Ordered chapter id => (subchapter boolean).
     */
    public static function validate_order(array $chapters, array $groups): array {
        if (!$groups || count($groups) > count($chapters)) {
            throw new moodle_exception('invalidchapterorder', 'mod_book');
        }

        $order = [];
        foreach ($groups as $group) {
            if (!is_array($group) || !isset($group['id']) || !is_int($group['id']) ||
                    !isset($group['children']) || !is_array($group['children'])) {
                throw new moodle_exception('invalidchapterorder', 'mod_book');
            }
            $id = $group['id'];
            if (!isset($chapters[$id]) || $chapters[$id]->subchapter || isset($order[$id])) {
                throw new moodle_exception('invalidchapterorder', 'mod_book');
            }

            $order[$id] = 0;
            foreach ($group['children'] as $childid) {
                if (!is_int($childid) || !isset($chapters[$childid]) || !$chapters[$childid]->subchapter ||
                        isset($order[$childid])) {
                    throw new moodle_exception('invalidchapterorder', 'mod_book');
                }
                // Moving a visible child into a hidden chapter would silently hide it.
                if ($chapters[$id]->hidden && !$chapters[$childid]->hidden) {
                    throw new moodle_exception('invalidchapterorder', 'mod_book');
                }
                $order[$childid] = 1;
            }
        }
        if (count($order) !== count($chapters)) {
            throw new moodle_exception('invalidchapterorder', 'mod_book');
        }
        return $order;
    }

    /**
     * Reorder every chapter in one transaction.
     *
     * @param stdClass $book Book record.
     * @param array $groups Hierarchy supplied by the editing interface.
     * @param int $expectedrevision Revision at the moment the editor was opened.
     * @param context_module $context
     * @return int New or unchanged book revision.
     */
    public static function reorder(stdClass $book, array $groups, int $expectedrevision,
            context_module $context): int {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        try {
            $book = $DB->get_record('book', ['id' => $book->id], '*', MUST_EXIST);
            if ((int) $book->revision !== $expectedrevision) {
                throw new moodle_exception('chapterorderchanged', 'mod_book');
            }

            $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id],
                'pagenum ASC, id ASC');
            $order = self::validate_order($chapters, $groups);
            $changed = [];
            $pagenum = 1;

            foreach ($order as $id => $subchapter) {
                $chapter = $chapters[$id];
                if ((int) $chapter->pagenum !== $pagenum || (int) $chapter->subchapter !== $subchapter) {
                    $chapter->pagenum = $pagenum;
                    $chapter->subchapter = $subchapter;
                    $DB->update_record('book_chapters', $chapter);
                    $changed[] = $chapter;
                }
                $pagenum++;
            }

            if ($changed) {
                $DB->set_field('book', 'revision', (int) $book->revision + 1, ['id' => $book->id]);
                foreach ($changed as $chapter) {
                    \mod_book\event\chapter_updated::create_from_chapter($book, $context, $chapter)->trigger();
                }
            }

            $transaction->allow_commit();
            return (int) $book->revision + ($changed ? 1 : 0);
        } catch (\Throwable $e) {
            $transaction->rollback($e);
            throw $e;
        }
    }

    /**
     * Identify the chapter(s) to duplicate: a chapter plus its subchapters,
     * or only the selected subchapter.
     *
     * @param array $chapters Chapter records in pagenum order.
     * @param int $chapterid Source chapter.
     * @return array Chapter records in copy order.
     */
    public static function chapters_to_duplicate(array $chapters, int $chapterid): array {
        $result = [];
        $started = false;
        foreach ($chapters as $chapter) {
            if ((int) $chapter->id === $chapterid) {
                $started = true;
                $result[] = $chapter;
                if ($chapter->subchapter) {
                    break;
                }
                continue;
            }
            if ($started) {
                if (!$chapter->subchapter) {
                    break;
                }
                $result[] = $chapter;
            }
        }
        if (!$result) {
            throw new moodle_exception('errorchapter', 'mod_book');
        }
        return $result;
    }

    /**
     * Duplicate a chapter or whole chapter group, including embedded files and tags.
     * The reading history is deliberately not duplicated.
     *
     * @param stdClass $book
     * @param int $chapterid
     * @param context_module $context
     * @param int|null $expectedrevision Expected chapter revision for an editor request.
     * @return int ID of the new chapter.
     */
    public static function duplicate(stdClass $book, int $chapterid, context_module $context,
            ?int $expectedrevision = null): int {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $createdids = [];
        $fs = get_file_storage();

        try {
            $book = $DB->get_record('book', ['id' => $book->id], '*', MUST_EXIST);
            if ($expectedrevision !== null && (int) $book->revision !== $expectedrevision) {
                throw new moodle_exception('chapterorderchanged', 'mod_book');
            }
            $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id],
                'pagenum ASC, id ASC');
            $source = self::chapters_to_duplicate($chapters, $chapterid);
            $end = end($source);
            $insertat = (int) $end->pagenum + 1;
            $copycount = count($source);

            $DB->execute('UPDATE {book_chapters}
                             SET pagenum = pagenum + ?
                           WHERE bookid = ? AND pagenum >= ?',
                [$copycount, $book->id, $insertat]);

            $newchapterid = 0;
            foreach ($source as $index => $original) {
                $copy = clone $original;
                unset($copy->id);
                $copy->pagenum = $insertat + $index;
                $copy->timecreated = time();
                $copy->timemodified = time();
                $copy->importsrc = '';
                if ($index === 0) {
                    $copy->title = \core_text::substr($original->title, 0, 1323) .
                        get_string('chaptercopytitle', 'mod_book');
                }
                $copy->id = $DB->insert_record('book_chapters', $copy);
                $createdids[] = $copy->id;
                if (!$newchapterid) {
                    $newchapterid = $copy->id;
                }

                // The saved HTML contains @@PLUGINFILE@@ URLs; the copied files will
                // be served under the new chapter id without modifying the HTML.
                $files = $fs->get_area_files($context->id, 'mod_book', 'chapter',
                    $original->id, 'id', false);
                foreach ($files as $file) {
                    $fs->create_file_from_storedfile([
                        'contextid' => $context->id,
                        'component' => 'mod_book',
                        'filearea' => 'chapter',
                        'itemid' => $copy->id,
                        'filepath' => $file->get_filepath(),
                        'filename' => $file->get_filename(),
                        'userid' => $file->get_userid(),
                        'timecreated' => time(),
                        'timemodified' => time(),
                    ], $file);
                }

                if (\core_tag_tag::is_enabled('mod_book', 'book_chapters')) {
                    $tags = \core_tag_tag::get_item_tags_array('mod_book', 'book_chapters', $original->id);
                    \core_tag_tag::set_item_tags('mod_book', 'book_chapters', $copy->id, $context, $tags);
                }
                \mod_book\event\chapter_created::create_from_chapter($book, $context, $copy)->trigger();
            }

            $DB->set_field('book', 'revision', (int) $book->revision + 1, ['id' => $book->id]);
            $transaction->allow_commit();
            return $newchapterid;
        } catch (\Throwable $e) {
            // Moodle DB transactions cannot roll back files stored in filepool.
            foreach ($createdids as $createdid) {
                $fs->delete_area_files($context->id, 'mod_book', 'chapter', $createdid);
            }
            $transaction->rollback($e);
            throw $e;
        }
    }
}
