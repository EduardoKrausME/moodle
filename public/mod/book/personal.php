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
$action = required_param('action', PARAM_ALPHA);
$cm = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();
$context = context_module::instance($cm->id);
require_capability('mod/book:read', $context);
if (!isloggedin() || isguestuser()) {
    throw new moodle_exception('privateaccountrequired', 'mod_book');
}

$chapter = $DB->get_record('book_chapters', [
    'id' => $chapterid, 'bookid' => $book->id,
], '*', MUST_EXIST);
if ($chapter->hidden && !has_capability('mod/book:viewhiddenchapters', $context)) {
    throw new moodle_exception('errorchapter', 'mod_book');
}

switch ($action) {
    case 'favourite':
        $favourite = required_param('favourite', PARAM_INT);
        if ($favourite !== 0 && $favourite !== 1) {
            throw new \core\exception\invalid_parameter_exception();
        }
        \mod_book\reading_tools::set_favourite((int) $book->id, $chapterid, (int) $USER->id,
            (bool) $favourite);
        break;
    case 'note':
        $note = optional_param('note', '', PARAM_RAW);
        \mod_book\reading_tools::set_note((int) $book->id, $chapterid, (int) $USER->id, $note);
        break;
    default:
        throw new \core\exception\invalid_parameter_exception();
}

redirect(new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $chapterid]),
    get_string('personaltoolsupdated', 'mod_book'));
