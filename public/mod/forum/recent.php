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
 * Cross-course recent forum discussions for the current user.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$unread = optional_param('unread', 0, PARAM_BOOL);
$following = optional_param('following', 0, PARAM_BOOL);
$unread = $following ? false : $unread;

require_login();
if (isguestuser()) {
    throw new \required_capability_exception(
        \context_system::instance(), 'moodle/course:view', 'nopermissions', '');
}

$context = \context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new \moodle_url('/mod/forum/recent.php', ['courseid' => $courseid, 'unread' => $unread, 'following' => $following]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('recentfeedtitle', 'mod_forum'));
$PAGE->set_heading(get_string('recentfeedtitle', 'mod_forum'));
$PAGE->navbar->add(get_string('recentfeedtitle', 'mod_forum'));

$courses = \mod_forum\local\recent_feed::get_course_options();
$validcourseids = array_column($courses, 'id');
if ($courseid !== 0 && !in_array($courseid, $validcourseids, true)) {
    $courseid = 0;
}
foreach ($courses as &$course) {
    $course['selected'] = $course['id'] === $courseid;
}
unset($course);

$PAGE->requires->js_call_amd('mod_forum/recent_feed', 'init', [$courseid, (bool)$unread, (bool)$following]);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_forum/recent_feed_page', [
    'courses' => $courses,
    'courseid' => $courseid,
    'unread' => (bool)$unread,
    'following' => (bool)$following,
    'allselected' => !$unread && !$following,
    'unreadselected' => (bool)$unread,
    'followingselected' => (bool)$following,
]);
echo $OUTPUT->footer();
