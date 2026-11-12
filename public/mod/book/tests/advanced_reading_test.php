<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without ANY WARRANTY; without even ANY WARRANTY of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

namespace mod_book;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/book/lib.php');
require_once($CFG->dirroot . '/mod/book/locallib.php');

/**
 * Tests for advanced Book search, bookmarks, personal notes and metrics.
 *
 * @package mod_book
 * @covers \mod_book\chapter_search
 * @covers \mod_book\reading_tools
 * @covers \mod_book\reading_metrics
 */
final class advanced_reading_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create a Book with two visible chapters and one hidden chapter.
     *
     * @return array
     */
    private function make_book(): array {
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $first = $generator->create_chapter([
            'bookid' => $book->id,
            'pagenum' => 1,
            'title' => 'Solar Energy',
            'content' => '<p>Panels produce renewable power.</p>',
        ]);
        $second = $generator->create_chapter([
            'bookid' => $book->id,
            'pagenum' => 2,
            'title' => 'Battery storage',
            'content' => '<h2>Store excess energy</h2><p>Charge controllers are important.</p>',
        ]);
        $hidden = $generator->create_chapter([
            'bookid' => $book->id,
            'pagenum' => 3,
            'title' => 'Secret power',
            'content' => '<p>Confidential material</p>',
            'hidden' => 1,
        ]);
        return [$course, $book, $first, $second, $hidden];
    }

    public function test_search_scoped_to_accessible_chapters_and_plain_text(): void {
        [$course, $book, $first, $second, $hidden] = $this->make_book();
        $chapters = book_preload_chapters($book);

        $title = chapter_search::find($chapters, 'solar');
        $this->assertCount(1, $title);
        $this->assertSame($first->id, $title[0]['chapterid']);

        $body = chapter_search::find($chapters, 'controllers');
        $this->assertCount(1, $body);
        $this->assertSame($second->id, $body[0]['chapterid']);
        $this->assertStringNotContainsString('<h2>', $body[0]['excerpt']);

        $this->assertCount(0, chapter_search::find($chapters, 'confidential'));
        $this->assertCount(1, chapter_search::find($chapters, 'confidential', true));
        $this->assertCount(0, chapter_search::find($chapters, ''));
        $this->assertCount(1, chapter_search::find($chapters, 'energy', false, 1));
        $this->assertCount(0, chapter_search::find($chapters, str_repeat('A', 101)));
    }

    public function test_favourites_and_notes_are_independent_per_reader(): void {
        global $DB;
        [$course, $book, $first, $second] = $this->make_book();
        $alice = $this->getDataGenerator()->create_user();
        $bob = $this->getDataGenerator()->create_user();

        reading_tools::set_favourite($book->id, $first->id, $alice->id, true);
        reading_tools::set_note($book->id, $first->id, $alice->id, "My private note\nRead again");
        reading_tools::set_note($book->id, $second->id, $bob->id, 'Bob only');

        $alicefirst = reading_tools::get($book->id, $first->id, $alice->id);
        $this->assertTrue($alicefirst['favourite']);
        $this->assertStringContainsString('My private note', $alicefirst['note']);
        $this->assertSame(['favourite' => false, 'note' => ''],
            reading_tools::get($book->id, $first->id, $bob->id));
        $this->assertSame([$first->id], reading_tools::get_favourites($book->id, $alice->id, [
            $first->id => $first, $second->id => $second,
        ]));

        // Removing the favourite must preserve the personal note.
        reading_tools::set_favourite($book->id, $first->id, $alice->id, false);
        $this->assertFalse(reading_tools::get($book->id, $first->id, $alice->id)['favourite']);
        $this->assertNotEmpty(reading_tools::get($book->id, $first->id, $alice->id)['note']);
        reading_tools::set_note($book->id, $first->id, $alice->id, '');
        $this->assertFalse($DB->record_exists('book_chapter_userdata', [
            'bookid' => $book->id, 'chapterid' => $first->id, 'userid' => $alice->id,
        ]));
        $this->assertSame(1, $DB->count_records('book_chapter_userdata'));
    }

    public function test_note_validation_and_csv_export_injection_protection(): void {
        [$course, $book, $first] = $this->make_book();
        $student = $this->getDataGenerator()->create_user();

        reading_tools::set_note($book->id, $first->id, $student->id, '<b>Visible</b>');
        $this->assertSame('Visible', reading_tools::get($book->id, $first->id, $student->id)['note']);
        $this->assertSame("'=HYPERLINK(\"x\")", reading_tools::csv_safe('=HYPERLINK("x")'));
        $this->assertSame("' +SUM(A1:A2)", reading_tools::csv_safe(' +SUM(A1:A2)'));
        $this->assertSame('Ordinary text', reading_tools::csv_safe('Ordinary text'));
        // Zero-width separators and tabs must not bypass spreadsheet injection defence.
        $hiddenprefix = "\u{200B}=1+1";
        $this->assertSame("'" . $hiddenprefix, reading_tools::csv_safe($hiddenprefix));
        $tabprefix = "\t@SUM(A1:A2)";
        $this->assertSame("'" . $tabprefix, reading_tools::csv_safe($tabprefix));

        $this->expectException(\moodle_exception::class);
        reading_tools::set_note($book->id, $first->id, $student->id, str_repeat('x', 5001));
    }

    public function test_metrics_use_distinct_visible_chapters_and_ignore_other_users(): void {
        [$course, $book, $first, $second, $hidden] = $this->make_book();
        $reader = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        reading_progress::record_view($book->id, $first->id, $reader->id);
        reading_progress::record_view($book->id, $hidden->id, $reader->id);
        reading_progress::record_view($book->id, $second->id, $other->id);

        $stats = reading_metrics::summarize($book->id, book_preload_chapters($book),
            [$reader->id, $other->id]);
        $this->assertSame(2, $stats[$reader->id]['total']);
        $this->assertSame(1, $stats[$reader->id]['visited']);
        $this->assertSame(50, $stats[$reader->id]['percent']);
        $this->assertSame($first->id, $stats[$reader->id]['lastchapterid']);
        $this->assertSame($second->id, $stats[$other->id]['lastchapterid']);
    }

    public function test_book_deletion_clears_personal_data_and_course_reset_does_too(): void {
        global $DB;
        [$course, $book, $first] = $this->make_book();
        $user = $this->getDataGenerator()->create_user();
        reading_tools::set_note($book->id, $first->id, $user->id, 'Personal');
        reading_tools::set_favourite($book->id, $first->id, $user->id, true);
        reading_progress::record_view($book->id, $first->id, $user->id);

        book_reset_userdata((object) ['courseid' => $course->id, 'reset_book_reading' => 1]);
        $this->assertSame(0, $DB->count_records('book_chapter_userdata'));
        $this->assertSame(0, $DB->count_records('book_chapter_reads'));

        reading_tools::set_note($book->id, $first->id, $user->id, 'Personal again');
        $this->assertTrue(book_delete_instance($book->id));
        $this->assertSame(0, $DB->count_records('book_chapter_userdata'));
    }
}
