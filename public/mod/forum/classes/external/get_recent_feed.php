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
use mod_forum\local\recent_feed;

/**
 * AJAX endpoint for the cross-course, permission-checked forum feed.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_recent_feed extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, '0 for all accessible courses'),
            'unread' => new external_value(PARAM_BOOL, 'Unread only'),
            'cursor' => new external_value(PARAM_RAW_TRIMMED, 'Opaque continuation cursor'),
            'following' => new external_value(PARAM_BOOL, 'Only starred discussions',
                VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(int $courseid, bool $unread, string $cursor,
            bool $following = false): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'unread' => $unread, 'cursor' => $cursor,
            'following' => $following,
        ]);
        require_login();
        if (isguestuser()) {
            throw new \required_capability_exception(
                \context_system::instance(), 'moodle/course:view', 'nopermissions', '');
        }
        self::validate_context(\context_system::instance());
        if ($params['courseid'] < 0) {
            throw new \invalid_parameter_exception('Invalid course filter.');
        }
        return recent_feed::get_page((int)$params['courseid'], (bool)$params['unread'],
            $params['cursor'], (bool)$params['following']);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'discussionid' => new external_value(PARAM_INT, 'Discussion ID'),
                'postid' => new external_value(PARAM_INT, 'Latest visible post ID'),
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'coursename' => new external_value(PARAM_TEXT, 'Course name'),
                'forumname' => new external_value(PARAM_TEXT, 'Forum name'),
                'subject' => new external_value(PARAM_TEXT, 'Discussion name'),
                'author' => new external_value(PARAM_TEXT, 'Post author'),
                'time' => new external_value(PARAM_TEXT, 'Date of latest visible post'),
                'preview' => new external_value(PARAM_TEXT, 'Plain-text post excerpt'),
                'url' => new external_value(PARAM_URL, 'Direct link to the post'),
                'unread' => new external_value(PARAM_BOOL, 'Whether the visible post is unread'),
                'favourited' => new external_value(PARAM_BOOL, 'Starred by the current user'),
                'canfavourite' => new external_value(PARAM_BOOL, 'User can star this discussion'),
                'canreply' => new external_value(PARAM_BOOL, 'May open the inline reply editor'),
                'advancedurl' => new external_value(PARAM_URL, 'Native advanced reply form'),
            ])),
            'nextcursor' => new external_value(PARAM_RAW_TRIMMED, 'Next page token'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether more items may be available'),
        ]);
    }
}
