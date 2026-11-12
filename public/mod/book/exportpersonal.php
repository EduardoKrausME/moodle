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
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/book:read', $context);
if (!isloggedin() || isguestuser()) {
    throw new moodle_exception('privateaccountrequired', 'mod_book');
}

$chapters = book_preload_chapters($book);
$viewhidden = has_capability('mod/book:viewhiddenchapters', $context);
$valid = [];
foreach ($chapters as $chapter) {
    if (!$chapter->hidden || $viewhidden) {
        $valid[$chapter->id] = $chapter;
    }
}

$personal = $DB->get_records('book_chapter_userdata', [
    'bookid' => $book->id, 'userid' => $USER->id,
], 'chapterid ASC');
$reads = $DB->get_records('book_chapter_reads', [
    'bookid' => $book->id, 'userid' => $USER->id,
]);
$lastvisit = [];
foreach ($reads as $record) {
    $lastvisit[$record->chapterid] = (int) floor($record->lastviewed / 1000);
}

$filename = clean_filename($book->name . '-personal-notes.csv');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, no-store, max-age=0');
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, [
    get_string('chaptertitle', 'mod_book'),
    get_string('privatetoolsfavourite', 'mod_book'),
    get_string('privatetoolsnote', 'mod_book'),
    get_string('lastaccessed', 'mod_book'),
], ',', '"', '');
foreach ($personal as $record) {
    if (!isset($valid[$record->chapterid])) {
        continue;
    }
    $chapter = $valid[$record->chapterid];
    $title = trim(html_entity_decode(strip_tags($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    fputcsv($output, [
        \mod_book\reading_tools::csv_safe($title),
        $record->favourite ? '1' : '0',
        \mod_book\reading_tools::csv_safe($record->note),
        isset($lastvisit[$chapter->id]) ? userdate($lastvisit[$chapter->id]) : '',
    ], ',', '"', '');
}
fclose($output);
exit;
