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
use mod_forum\local\recent_discussions;

/**
 * AJAX endpoint for recent conversations in a course.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_course_recent_discussions extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
        ]);
    }

    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $course = get_course($params['courseid']);
        require_login($course);
        self::validate_context(\context_course::instance($course->id));
        return ['discussions' => recent_discussions::get_for_course($course)];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'discussions' => new external_multiple_structure(new external_single_structure([
                'postid' => new external_value(PARAM_INT, 'Latest visible post ID'),
                'subject' => new external_value(PARAM_TEXT, 'Discussion name'),
                'forumname' => new external_value(PARAM_TEXT, 'Forum name'),
                'author' => new external_value(PARAM_TEXT, 'Post author'),
                'time' => new external_value(PARAM_TEXT, 'Localized post date'),
                'url' => new external_value(PARAM_URL, 'Direct link to the post'),
            ])),
        ]);
    }
}
