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
 * Teacher queue for unanswered or unresolved forum discussions.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

use mod_forum\local\unanswered_discussions as queue;

$courseid = optional_param('courseid', 0, PARAM_INT);
$forumid = optional_param('forumid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$status = optional_param('status', 'all', PARAM_ALPHA);
$sort = optional_param('sort', 'oldest', PARAM_ALPHA);
$cursor = optional_param('cursor', '', PARAM_RAW_TRIMMED);

require_login();
if (isguestuser()) {
    throw new \required_capability_exception(
        \context_system::instance(), 'mod/forum:viewunanswered', 'nopermissions', '');
}
$courses = queue::get_course_options();
if (!$courses) {
    throw new \required_capability_exception(
        \context_system::instance(), 'mod/forum:viewunanswered', 'nopermissions', '');
}
$page = queue::get_page($courseid, $forumid, $groupid, $status, $sort, $cursor);
$forums = queue::get_forum_options($courseid);
$groups = queue::get_group_options($courseid, $forumid);
foreach ($courses as &$course) {
    $course['selected'] = $course['id'] === $courseid;
}
unset($course);
foreach ($forums as &$forum) {
    $forum['selected'] = $forum['id'] === $forumid;
}
unset($forum);
foreach ($groups as &$group) {
    $group['selected'] = $group['id'] === $groupid;
}
unset($group);

$statuses = [];
foreach (['all', 'unanswered', 'awaiting', 'accepted', 'answered'] as $value) {
    $statuses[] = [
        'value' => $value,
        'label' => get_string('unansweredstatus_' . $value, 'mod_forum'),
        'selected' => $value === $status,
    ];
}
$sorts = [];
foreach (['oldest', 'newest'] as $value) {
    $sorts[] = [
        'value' => $value,
        'label' => get_string('unanswered' . $value, 'mod_forum'),
        'selected' => $value === $sort,
    ];
}
$context = \context_system::instance();
$url = new \moodle_url('/mod/forum/unanswered.php', [
    'courseid' => $courseid,
    'forumid' => $forumid,
    'groupid' => $groupid,
    'status' => $status,
    'sort' => $sort,
]);
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('unansweredtitle', 'mod_forum'));
$PAGE->set_heading(get_string('unansweredtitle', 'mod_forum'));
$PAGE->navbar->add(get_string('unansweredtitle', 'mod_forum'));
$PAGE->requires->js_call_amd('mod_forum/unanswered', 'init');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_forum/unanswered_page', [
    'actionurl' => (new \moodle_url('/mod/forum/unanswered.php'))->out(false),
    'courses' => $courses,
    'forums' => $forums,
    'groups' => $groups,
    'hasgroups' => $courseid > 0,
    'selectedcourse' => $courseid,
    'selectedforum' => $forumid,
    'selectedgroup' => $groupid,
    'statuses' => $statuses,
    'sorts' => $sorts,
    'items' => $page['items'],
    'counts' => $page['counts'],
    'count' => count($page['items']),
    'nextcursor' => $page['nextcursor'],
    'hasmore' => $page['hasmore'],
    'empty' => !$page['items'],
]);
echo $OUTPUT->footer();
