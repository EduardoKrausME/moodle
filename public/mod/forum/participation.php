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

/**
 * Course and forum participation statistics for authorised staff.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use mod_forum\local\participation_report as report;

require_login();
if (isguestuser()) {
    throw new \required_capability_exception(
        \context_system::instance(), 'mod/forum:viewparticipationreport', 'nopermissions', '');
}

$courses = report::get_course_options();
if (!$courses) {
    throw new \required_capability_exception(
        \context_system::instance(), 'mod/forum:viewparticipationreport', 'nopermissions', '');
}
$courseid = optional_param('courseid', 0, PARAM_INT);
if (!$courseid) {
    $courseid = $courses[0]['id'];
}
$forumid = optional_param('forumid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$timezone = new \DateTimeZone(\core_date::get_user_timezone($USER));
$now = new \DateTimeImmutable('now', $timezone);
$fromdate = optional_param('from', $now->modify('-29 days')->format('Y-m-d'), PARAM_ALPHANUMEXT);
$todate = optional_param('to', $now->format('Y-m-d'), PARAM_ALPHANUMEXT);
$download = optional_param('download', '', PARAM_ALPHA);
if ($download !== '' && $download !== 'csv') {
    throw new \invalid_parameter_exception('Invalid participation download format.');
}

// All metrics and exports share exactly the same server-side permission checks.
$data = report::get_page($courseid, $forumid, $groupid, $fromdate, $todate);
$course = get_course($courseid);
if ($download === 'csv') {
    $filename = 'forum-participation-' . $fromdate . '-' . $todate . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');
    $columns = [
        get_string('participationforum', 'mod_forum'),
        get_string('participationdiscussions', 'mod_forum'),
        get_string('participationreplies', 'mod_forum'),
        get_string('participationparticipants', 'mod_forum'),
        get_string('participationresponded', 'mod_forum'),
        get_string('participationcoverage', 'mod_forum'),
        get_string('participationmedian', 'mod_forum'),
        get_string('participationaccepted', 'mod_forum'),
    ];
    fputcsv($output, array_map([report::class, 'csv_cell'], $columns), ',', '"', '');
    $rows = [array_merge(['name' => get_string('participationallforums', 'mod_forum')], $data['metrics'])];
    $rows = array_merge($rows, $data['forums']);
    foreach ($rows as $item) {
        $values = [
            report::csv_cell($item['name']),
            $item['discussions'],
            $item['replies'],
            $item['participants'],
            $item['responded'],
            $item['coverage'],
            $item['medianhours'],
            $item['accepted'],
        ];
        fputcsv($output, $values, ',', '"', '');
    }
    fclose($output);
    exit;
}

$forums = report::get_forum_options($courseid);
$groups = report::get_group_options($courseid, $forumid);
foreach ($courses as &$item) {
    $item['selected'] = $item['id'] === $courseid;
}
unset($item);
foreach ($forums as &$item) {
    $item['selected'] = $item['id'] === $forumid;
}
unset($item);
foreach ($groups as &$item) {
    $item['selected'] = $item['id'] === $groupid;
}
unset($item);
$url = new \moodle_url('/mod/forum/participation.php', [
    'courseid' => $courseid, 'forumid' => $forumid, 'groupid' => $groupid,
    'from' => $fromdate, 'to' => $todate,
]);
$downloadurl = new \moodle_url($url, ['download' => 'csv']);
$periodoptions = [];
foreach ([7, 30, 90] as $days) {
    $start = $now->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
    $link = new \moodle_url('/mod/forum/participation.php', [
        'courseid' => $courseid, 'forumid' => $forumid, 'groupid' => $groupid,
        'from' => $start, 'to' => $now->format('Y-m-d'),
    ]);
    $periodoptions[] = [
        'label' => get_string('participationdays', 'mod_forum', $days),
        'url' => $link->out(false),
        'selected' => $fromdate === $start && $todate === $now->format('Y-m-d'),
    ];
}
$PAGE->set_context(\context_course::instance($courseid));
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('participationtitle', 'mod_forum'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('participationtitle', 'mod_forum'));
$PAGE->activityheader->disable();
$PAGE->requires->js_call_amd('mod_forum/participation', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_forum/participation_page', array_merge($data, [
    'actionurl' => (new \moodle_url('/mod/forum/participation.php'))->out(false),
    'downloadurl' => $downloadurl->out(false),
    'periodoptions' => $periodoptions,
    'courses' => $courses,
    'forumoptions' => $forums,
    'groupoptions' => $groups,
    'hasgroupoptions' => !empty($groups),
    'hasactivity' => $data['metrics']['participants'] > 0,
]));
echo $OUTPUT->footer();
