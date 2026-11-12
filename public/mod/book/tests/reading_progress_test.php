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
require_once($CFG->dirroot . '/mod/book/lib.php');
require_once($CFG->dirroot . '/mod/book/locallib.php');

/**
 * Tests for per-user chapter reading progress.
 *
 * @package mod_book
 * @covers \mod_book\reading_progress
 */
final class reading_progress_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_book_entry_preserves_resume_location(): void {
        // A new reader starts at chapter one and establishes an initial position.
        $this->assertTrue(reading_progress::should_track_view(0, 0));
        // Returning to the Book itself must not replace an established last-read chapter.
        $this->assertFalse(reading_progress::should_track_view(0, 42));
        // Opening a specific chapter deliberately changes the last-read location.
        $this->assertTrue(reading_progress::should_track_view(17, 42));
    }

    public function test_visited_chapters_last_chapter_and_hidden_chapters(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_book');
        $first = $generator->create_chapter(['bookid' => $book->id, 'pagenum' => 1]);
        $second = $generator->create_chapter(['bookid' => $book->id, 'pagenum' => 2]);
        $hidden = $generator->create_chapter(['bookid' => $book->id, 'pagenum' => 3, 'hidden' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $chapters = book_preload_chapters($book);

        $empty = reading_progress::get_progress($book->id, $chapters, $user->id);
        $this->assertSame(0, $empty['visitedcount']);
        $this->assertSame(2, $empty['totalcount']);
        $this->assertSame(0, $empty['lastchapterid']);

        reading_progress::record_view($book->id, $first->id, $user->id);
        reading_progress::record_view($book->id, $second->id, $user->id);
        reading_progress::record_view($book->id, $hidden->id, $user->id);
        $status = reading_progress::get_progress($book->id, $chapters, $user->id);
        $this->assertSame(2, $status['visitedcount']);
        $this->assertSame(100, $status['percent']);
        $this->assertSame($second->id, $status['lastchapterid']);
        $this->assertArrayNotHasKey($hidden->id, $status['visitedids']);

        $teacherstatus = reading_progress::get_progress($book->id, $chapters, $user->id, true);
        $this->assertSame(3, $teacherstatus['visitedcount']);
        $this->assertSame($hidden->id, $teacherstatus['lastchapterid']);

        // Revisiting an existing chapter must update its position without duplicating the record.
        reading_progress::record_view($book->id, $first->id, $user->id);
        $this->assertSame(3, $DB->count_records('book_chapter_reads', [
            'bookid' => $book->id, 'userid' => $user->id,
        ]));
        $latest = reading_progress::get_progress($book->id, $chapters, $user->id);
        $this->assertSame($first->id, $latest['lastchapterid']);
    }

    public function test_users_have_isolated_history_and_book_deletion_cleans_it(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $chapter = $this->getDataGenerator()->get_plugin_generator('mod_book')
            ->create_chapter(['bookid' => $book->id]);
        $firstuser = $this->getDataGenerator()->create_user();
        $seconduser = $this->getDataGenerator()->create_user();

        reading_progress::record_view($book->id, $chapter->id, $firstuser->id);
        $this->assertSame(1, reading_progress::get_progress(
            $book->id, book_preload_chapters($book), $firstuser->id)['visitedcount']);
        $this->assertSame(0, reading_progress::get_progress(
            $book->id, book_preload_chapters($book), $seconduser->id)['visitedcount']);

        reading_progress::record_view($book->id, $chapter->id, 0);
        $this->assertSame(1, $DB->count_records('book_chapter_reads'));

        $this->assertTrue(book_delete_instance($book->id));
        $this->assertSame(0, $DB->count_records('book_chapter_reads'));
    }

    public function test_course_reset_can_clear_reading_history(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $chapter = $this->getDataGenerator()->get_plugin_generator('mod_book')
            ->create_chapter(['bookid' => $book->id]);
        $student = $this->getDataGenerator()->create_user();

        reading_progress::record_view($book->id, $chapter->id, $student->id);
        $status = book_reset_userdata((object) [
            'courseid' => $course->id,
            'reset_book_reading' => 1,
        ]);

        $this->assertNotEmpty($status);
        $this->assertSame(0, $DB->count_records('book_chapter_reads', ['bookid' => $book->id]));
        $this->assertEquals(1, $DB->count_records('book_chapters', ['bookid' => $book->id]));
    }
}
