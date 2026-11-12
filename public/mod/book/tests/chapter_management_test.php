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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/book/locallib.php');

/**
 * Chapter management tests: order validation, groups, duplication and files.
 *
 * @package mod_book
 * @covers \mod_book\chapter_management
 */
final class chapter_management_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * @return array Course, book, module context, main chapter, subchapter, next main chapter.
     */
    private function make_book(): array {
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_book');

        $first = $generator->create_chapter([
            'bookid' => $book->id, 'pagenum' => 1, 'title' => 'Introduction',
        ]);
        $subchapter = $generator->create_chapter([
            'bookid' => $book->id, 'pagenum' => 2, 'subchapter' => 1, 'title' => 'Details',
        ]);
        $second = $generator->create_chapter([
            'bookid' => $book->id, 'pagenum' => 3, 'title' => 'Summary',
        ]);
        return [$course, $book, \context_module::instance($book->cmid), $first, $subchapter, $second];
    }

    public function test_ordering_main_chapters_keeps_their_children(): void {
        global $DB;
        [$course, $book, $context, $first, $child, $second] = $this->make_book();

        $previousrevision = (int) $DB->get_field('book', 'revision', ['id' => $book->id]);
        $newrevision = chapter_management::reorder($book, [
            ['id' => $second->id, 'children' => []],
            ['id' => $first->id, 'children' => [$child->id]],
        ], $previousrevision, $context);

        $this->assertSame($previousrevision + 1, $newrevision);
        $rows = array_values($DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum ASC'));
        $this->assertSame([$second->id, $first->id, $child->id],
            array_map(fn($chapter) => (int) $chapter->id, $rows));
        $this->assertSame([0, 0, 1],
            array_map(fn($chapter) => (int) $chapter->subchapter, $rows));
        $this->assertSame([1, 2, 3],
            array_map(fn($chapter) => (int) $chapter->pagenum, $rows));

        // The editor's old revision must not overwrite a concurrent change.
        $this->expectException(\moodle_exception::class);
        chapter_management::reorder($book, [
            ['id' => $first->id, 'children' => [$child->id]],
            ['id' => $second->id, 'children' => []],
        ], $previousrevision, $context);
    }

    public function test_subchapter_can_move_between_visible_groups(): void {
        global $DB;

        [$course, $book, $context, $first, $child, $second] = $this->make_book();
        $revision = (int) $DB->get_field('book', 'revision', ['id' => $book->id]);
        chapter_management::reorder($book, [
            ['id' => $first->id, 'children' => []],
            ['id' => $second->id, 'children' => [$child->id]],
        ], $revision, $context);

        $ordered = array_values($DB->get_records('book_chapters', ['bookid' => $book->id],
            'pagenum ASC'));
        $this->assertSame([$first->id, $second->id, $child->id],
            array_map(fn($chapter) => (int) $chapter->id, $ordered));
        $this->assertSame(1, (int) $ordered[2]->subchapter);
    }

    public function test_duplicate_refuses_stale_revision_without_creating_chapters(): void {
        global $DB;

        [$course, $book, $context, $first] = $this->make_book();
        $revision = (int) $DB->get_field('book', 'revision', ['id' => $book->id]);
        try {
            chapter_management::duplicate($book, $first->id, $context, $revision - 1);
            $this->fail('Stale editor revision was accepted.');
        } catch (\moodle_exception $e) {
            $this->assertSame('chapterorderchanged', $e->errorcode);
        }
        $this->assertSame(3, $DB->count_records('book_chapters', ['bookid' => $book->id]));
    }

    public function test_rejects_missing_and_duplicate_chapters_without_changes(): void {
        global $DB;
        [$course, $book, $context, $first, $child, $second] = $this->make_book();
        $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id]);
        $orders = [
            [['id' => $first->id, 'children' => [$child->id]]], // Missing second.
            [['id' => $first->id, 'children' => [$child->id, $child->id]],
                ['id' => $second->id, 'children' => []]], // Duplicate child.
            [['id' => $child->id, 'children' => []],
                ['id' => $second->id, 'children' => []]], // Child cannot be a parent.
            [['id' => $first->id, 'children' => [$second->id]],
                ['id' => $second->id, 'children' => []]], // Main chapter cannot be a child.
        ];
        foreach ($orders as $order) {
            try {
                chapter_management::validate_order($chapters, $order);
                $this->fail('Invalid chapter hierarchy accepted.');
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidchapterorder', $e->errorcode);
            }
        }
        $this->assertSame(3, $DB->count_records('book_chapters', ['bookid' => $book->id]));
    }

    public function test_duplicate_parent_includes_children_and_embedded_files_without_read_history(): void {
        global $DB, $USER;

        [$course, $book, $context, $first, $child, $second] = $this->make_book();
        $fs = get_file_storage();
        $DB->set_field('book_chapters', 'content',
            '<p><img src="@@PLUGINFILE@@/diagram.txt" alt="Diagram"></p>',
            ['id' => $first->id]);

        $sourcefile = $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_book',
            'filearea' => 'chapter',
            'itemid' => $first->id,
            'filepath' => '/',
            'filename' => 'diagram.txt',
            'userid' => $USER->id,
        ], 'example image bytes');

        $student = $this->getDataGenerator()->create_user();
        reading_progress::record_view($book->id, $first->id, $student->id);

        $newid = chapter_management::duplicate($book, $first->id, $context);
        $rows = array_values($DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum ASC'));
        $this->assertCount(5, $rows);
        $this->assertSame([$first->id, $child->id, $newid, $rows[3]->id, $second->id],
            array_map(fn($ch) => (int) $ch->id, $rows));
        $this->assertSame(0, (int) $rows[2]->subchapter);
        $this->assertSame(1, (int) $rows[3]->subchapter);
        $this->assertStringContainsString(get_string('chaptercopytitle', 'mod_book'), $rows[2]->title);
        $this->assertSame($DB->get_field('book_chapters', 'content', ['id' => $first->id]),
            $rows[2]->content);

        $copiedfile = $fs->get_file($context->id, 'mod_book', 'chapter', $newid, '/', 'diagram.txt');
        $this->assertNotFalse($copiedfile);
        $this->assertSame($sourcefile->get_content(), $copiedfile->get_content());

        // Duplicated chapters must never inherit a student's reading history.
        $this->assertSame(1, $DB->count_records('book_chapter_reads', ['bookid' => $book->id]));
        $this->assertFalse($DB->record_exists('book_chapter_reads',
            ['bookid' => $book->id, 'chapterid' => $newid, 'userid' => $student->id]));
    }

    public function test_duplicate_subchapter_copies_only_that_subchapter(): void {
        global $DB;
        [$course, $book, $context, $first, $child, $second] = $this->make_book();

        $newid = chapter_management::duplicate($book, $child->id, $context);
        $chapters = array_values($DB->get_records('book_chapters',
            ['bookid' => $book->id], 'pagenum ASC'));
        $this->assertCount(4, $chapters);
        $this->assertSame([$first->id, $child->id, $newid, $second->id],
            array_map(fn($chapter) => (int) $chapter->id, $chapters));
        $this->assertSame(1, (int) $chapters[2]->subchapter);
    }
}
