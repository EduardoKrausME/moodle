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

require(__DIR__ . '/../../config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new \core\exception\invalid_parameter_exception();
}

$id = required_param('id', PARAM_INT);
$chapterid = required_param('chapterid', PARAM_INT);
$expectedrevision = required_param('revision', PARAM_INT);
$cm = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/book:edit', $context);
if ((int) $book->revision !== $expectedrevision) {
    throw new moodle_exception('chapterorderchanged', 'mod_book');
}
$DB->get_record('book_chapters', ['id' => $chapterid, 'bookid' => $book->id], '*', MUST_EXIST);

$newchapterid = \mod_book\chapter_management::duplicate($book, $chapterid, $context);
redirect(new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $newchapterid]),
    get_string('chapterduplicated', 'mod_book'));
