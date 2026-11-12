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

namespace mod_forum\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_forum\local\course_unread_counts;

/**
 * AJAX service for per-course unread forum message indicators.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_course_unread_counts extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course identifier'),
                'Displayed course IDs (at most 96)',
            ),
        ]);
    }

    /**
     * @param array $courseids Displayed course IDs.
     * @return array Count data for accessible courses.
     */
    public static function execute(array $courseids): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['courseids' => $courseids]);
        require_login();
        if (isguestuser()) {
            throw new \required_capability_exception(
                \context_system::instance(), 'moodle/course:view', 'nopermissions', '');
        }

        $ids = array_values(array_unique(array_map('intval', $params['courseids'])));
        if (count($ids) > course_unread_counts::MAX_COURSES) {
            throw new \invalid_parameter_exception('Too many courses requested.');
        }
        if (!$ids) {
            return ['courses' => []];
        }

        $requested = $DB->get_records_list('course', 'id', $ids);
        $accessible = [];
        foreach ($requested as $course) {
            if ((int)$course->id === SITEID) {
                continue;
            }
            $context = \context_course::instance($course->id);
            // A course in the browser must still be authorised on every AJAX request.
            if (!is_enrolled($context, $USER, '', true) && !is_viewing($context, $USER)) {
                continue;
            }
            if (!$course->visible &&
                    !has_capability('moodle/course:viewhiddencourses', $context, $USER)) {
                continue;
            }
            self::validate_context($context);
            $accessible[$course->id] = $course;
        }

        return ['courses' => course_unread_counts::get_for_courses($accessible)];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(new external_single_structure([
                'courseid' => new external_value(PARAM_INT, 'Accessible course identifier'),
                'count' => new external_value(PARAM_INT, 'Number of visible unread posts'),
                'forumcount' => new external_value(PARAM_INT, 'Forums containing visible unread posts'),
                'state' => new external_value(PARAM_ALPHA, 'unread, partial, activity or none'),
            ])),
        ]);
    }
}
