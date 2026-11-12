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
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_book\reading_progress;
use mod_book\reading_tools;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the Book reading history privacy provider.
 *
 * @package mod_book
 * @covers \mod_book\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    public function test_metadata_and_context_discovery(): void {
        $collection = provider::get_metadata(new collection('mod_book'));
        $tables = $collection->get_collection();
        $this->assertCount(2, $tables);
        $names = array_map(fn($table) => $table->get_name(), $tables);
        $this->assertContains('book_chapter_reads', $names);
        $this->assertContains('book_chapter_userdata', $names);

        [$book, $chapter, $context] = $this->make_book_with_chapter();
        $user = $this->getDataGenerator()->create_user();
        // Context discovery must work even when there are only personal notes.
        reading_tools::set_note($book->id, $chapter->id, $user->id, 'My private note');

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context->id, $contextlist->current()->id);

        $users = new userlist($context, 'mod_book');
        provider::get_users_in_context($users);
        $this->assertEquals([$user->id], $users->get_userids());
    }

    public function test_export_and_delete_only_approved_user_data(): void {
        global $DB;

        [$book, $chapter, $context] = $this->make_book_with_chapter();
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        reading_progress::record_view($book->id, $chapter->id, $first->id);
        reading_progress::record_view($book->id, $chapter->id, $second->id);
        reading_tools::set_note($book->id, $chapter->id, $first->id, 'Only first user');
        reading_tools::set_favourite($book->id, $chapter->id, $second->id, true);

        $this->export_context_data_for_user($first->id, $context, 'mod_book');
        $this->assertTrue(writer::with_context($context)->has_any_data());

        $approved = new approved_contextlist($first, 'mod_book', [$context->id]);
        provider::delete_data_for_user($approved);
        $this->assertSame(0, $DB->count_records('book_chapter_reads', ['userid' => $first->id]));
        $this->assertSame(1, $DB->count_records('book_chapter_reads', ['userid' => $second->id]));
        $this->assertSame(0, $DB->count_records('book_chapter_userdata', ['userid' => $first->id]));
        $this->assertSame(1, $DB->count_records('book_chapter_userdata', ['userid' => $second->id]));

        // Deleting an unapproved context must not remove the other reader's data.
        $approvedusers = new approved_userlist($context, 'mod_book', [$second->id]);
        provider::delete_data_for_users($approvedusers);
        $this->assertSame(0, $DB->count_records('book_chapter_reads'));
        $this->assertSame(0, $DB->count_records('book_chapter_userdata'));
    }

    public function test_delete_all_data_for_context_preserves_other_books(): void {
        global $DB;

        [$firstbook, $firstchapter, $firstcontext] = $this->make_book_with_chapter();
        [$secondbook, $secondchapter] = $this->make_book_with_chapter();
        $user = $this->getDataGenerator()->create_user();
        reading_progress::record_view($firstbook->id, $firstchapter->id, $user->id);
        reading_progress::record_view($secondbook->id, $secondchapter->id, $user->id);
        reading_tools::set_note($firstbook->id, $firstchapter->id, $user->id, 'First');
        reading_tools::set_note($secondbook->id, $secondchapter->id, $user->id, 'Second');

        provider::delete_data_for_all_users_in_context($firstcontext);
        $this->assertSame(0, $DB->count_records('book_chapter_reads', ['bookid' => $firstbook->id]));
        $this->assertSame(1, $DB->count_records('book_chapter_reads', ['bookid' => $secondbook->id]));
        $this->assertSame(0, $DB->count_records('book_chapter_userdata', ['bookid' => $firstbook->id]));
        $this->assertSame(1, $DB->count_records('book_chapter_userdata', ['bookid' => $secondbook->id]));
    }

    /**
     * Create a book with a single chapter.
     *
     * @return array Book, chapter and module context.
     */
    private function make_book_with_chapter(): array {
        $course = $this->getDataGenerator()->create_course();
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $chapter = $this->getDataGenerator()->get_plugin_generator('mod_book')
            ->create_chapter(['bookid' => $book->id]);
        return [$book, $chapter, \context_module::instance($book->cmid)];
    }
}
