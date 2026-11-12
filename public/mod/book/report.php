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
$page = max(0, optional_param('page', 0, PARAM_INT));
$format = optional_param('format', '', PARAM_ALPHA);
if ($format !== '' && $format !== 'csv') {
    throw new \core\exception\invalid_parameter_exception();
}

$cm = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$book = $DB->get_record('book', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);
require_capability('mod/book:edit', $context);
require_capability('moodle/course:viewparticipants', $coursecontext);

// Respect separate groups: editing permissions alone must not expose students in
// groups the teacher cannot access. An empty allowed set means no participants.
$groupids = 0;
$restrictgroups = groups_get_activity_groupmode($cm) === SEPARATEGROUPS &&
    !has_capability('moodle/site:accessallgroups', $context);
if ($restrictgroups) {
    $allowedgroups = groups_get_activity_allowed_groups($cm);
    $groupids = $allowedgroups ? array_map('intval', array_keys($allowedgroups)) : [];
}

// Limit both the SQL query and generated CSV to the current page of authorized enrolled users.
$pagesize = 50;
$totalusers = ($restrictgroups && !$groupids) ? 0 :
    count_enrolled_users($coursecontext, '', $groupids);
// Moodle 5.x exposes its configurable name fields through core_user\fields.
$userfields = 'u.id, ' . implode(', ', array_map(
    static fn(string $namefield): string => 'u.' . $namefield,
    \core_user\fields::get_name_fields()
));
$users = ($restrictgroups && !$groupids) ? [] : get_enrolled_users($coursecontext, '', $groupids,
    $userfields, 'u.lastname ASC, u.firstname ASC, u.id ASC', $page * $pagesize, $pagesize);

$chapters = book_preload_chapters($book);
$stats = \mod_book\reading_metrics::summarize((int) $book->id, $chapters, array_keys($users));
$visiblenames = [];
foreach ($chapters as $chapter) {
    if (!$chapter->hidden) {
        $visiblenames[$chapter->id] = trim(html_entity_decode(
            strip_tags($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
$rows = [];
foreach ($users as $user) {
    $userstats = $stats[$user->id];
    $rows[] = [
        'name' => fullname($user),
        'visited' => $userstats['visited'],
        'total' => $userstats['total'],
        'percent' => $userstats['percent'],
        'lastchapter' => $visiblenames[$userstats['lastchapterid']] ?? '',
        'lastviewed' => $userstats['lastviewed'] ?
            userdate((int) floor($userstats['lastviewed'] / 1000)) : '',
    ];
}

if ($format === 'csv') {
    $filename = clean_filename($book->name . '-reading-report-page-' . ($page + 1) . '.csv');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store, max-age=0');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        get_string('participantname', 'mod_book'),
        get_string('chaptersvisited', 'mod_book'),
        get_string('readingpercent', 'mod_book'),
        get_string('lastreadchapter', 'mod_book'),
        get_string('lastaccessed', 'mod_book'),
    ], ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, [
            \mod_book\reading_tools::csv_safe($row['name']),
            $row['visited'] . '/' . $row['total'],
            $row['percent'],
            \mod_book\reading_tools::csv_safe($row['lastchapter']),
            $row['lastviewed'],
        ], ',', '"', '');
    }
    fclose($out);
    exit;
}

$PAGE->set_url('/mod/book/report.php', ['id' => $cm->id, 'page' => $page]);
$PAGE->set_title(get_string('readingreport', 'mod_book'));
$PAGE->set_heading($course->fullname);
$PAGE->activityheader->set_attrs(['description' => '', 'hidecompletion' => true]);

$previousurl = $page > 0 ? (new moodle_url('/mod/book/report.php',
    ['id' => $cm->id, 'page' => $page - 1]))->out(false) : '';
$nexturl = (($page + 1) * $pagesize < $totalusers) ?
    (new moodle_url('/mod/book/report.php', ['id' => $cm->id, 'page' => $page + 1]))->out(false) : '';

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('readingreport', 'mod_book'));
echo $OUTPUT->render_from_template('mod_book/reading_report', [
    'totalusers' => $totalusers,
    'users' => $rows,
    'csvurl' => (new moodle_url('/mod/book/report.php',
        ['id' => $cm->id, 'page' => $page, 'format' => 'csv']))->out(false),
    'returnurl' => (new moodle_url('/mod/book/view.php', ['id' => $cm->id]))->out(false),
    'previousurl' => $previousurl,
    'nexturl' => $nexturl,
]);
echo $OUTPUT->footer();
