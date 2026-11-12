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
$revision = required_param('revision', PARAM_INT);
$encoded = required_param('groups', PARAM_RAW);

$cm = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
require_capability('mod/book:edit', $context);

if (strlen($encoded) > 200000) {
    throw new \core\exception\invalid_parameter_exception();
}
try {
    $groups = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    throw new \core\exception\invalid_parameter_exception();
}
if (!is_array($groups)) {
    throw new \core\exception\invalid_parameter_exception();
}

$newrevision = \mod_book\chapter_management::reorder($book, $groups, $revision, $context);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['success' => true, 'revision' => $newrevision]);
exit;
