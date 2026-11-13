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
use mod_forum\local\unanswered_discussions;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only AJAX view of teacher-visible unanswered forum discussions.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_unanswered_discussions extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course filter'),
            'forumid' => new external_value(PARAM_INT, 'Forum filter'),
            'groupid' => new external_value(PARAM_INT, 'Group filter'),
            'status' => new external_value(PARAM_ALPHA, 'Status filter'),
            'sort' => new external_value(PARAM_ALPHA, 'Sort order'),
            'cursor' => new external_value(PARAM_RAW_TRIMMED, 'Page cursor'),
        ]);
    }

    public static function execute(int $courseid, int $forumid, int $groupid,
            string $status, string $sort, string $cursor): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid, 'forumid' => $forumid, 'groupid' => $groupid,
            'status' => $status, 'sort' => $sort, 'cursor' => $cursor,
        ]);
        require_login();
        if (isguestuser()) {
            throw new \required_capability_exception(
                \context_system::instance(), 'mod/forum:viewunanswered', 'nopermissions', '');
        }
        self::validate_context(\context_system::instance());
        return unanswered_discussions::get_page((int)$params['courseid'],
            (int)$params['forumid'], (int)$params['groupid'],
            $params['status'], $params['sort'], $params['cursor']);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'discussionid' => new external_value(PARAM_INT, 'Visible discussion'),
                'courseid' => new external_value(PARAM_INT, 'Visible course'),
                'forumid' => new external_value(PARAM_INT, 'Visible forum'),
                'subject' => new external_value(PARAM_TEXT, 'Discussion title'),
                'coursename' => new external_value(PARAM_TEXT, 'Course name'),
                'forumname' => new external_value(PARAM_TEXT, 'Forum name'),
                'author' => new external_value(PARAM_TEXT, 'Discussion starter'),
                'created' => new external_value(PARAM_TEXT, 'Formatted original post time'),
                'waitinghours' => new external_value(PARAM_INT, 'Hours since the question'),
                'status' => new external_value(PARAM_ALPHA, 'Classification'),
                'statuslabel' => new external_value(PARAM_TEXT, 'Localized status'),
                'solutionurl' => new external_value(PARAM_RAW, 'Visible accepted solution link or empty'),
                'url' => new external_value(PARAM_URL, 'Native discussion URL'),
                'replyurl' => new external_value(PARAM_RAW, 'Authorised native reply URL'),
                'canreply' => new external_value(PARAM_BOOL, 'Can use native reply action'),
            ])),
            'counts' => new external_single_structure([
                'unanswered' => new external_value(PARAM_INT, 'No replies on this page'),
                'awaiting' => new external_value(PARAM_INT, 'Replies without solution on this page'),
                'accepted' => new external_value(PARAM_INT, 'Accepted answers on this page'),
                'answered' => new external_value(PARAM_INT, 'Answered without tracking on this page'),
            ]),
            'nextcursor' => new external_value(PARAM_RAW_TRIMMED, 'Continuation cursor'),
            'hasmore' => new external_value(PARAM_BOOL, 'Further candidates might exist'),
        ]);
    }
}
