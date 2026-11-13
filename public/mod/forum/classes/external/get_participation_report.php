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

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_forum\local\participation_report;

/**
 * Read-only, module-scoped participation report API.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_participation_report extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'forumid' => new external_value(PARAM_INT, 'Optional forum'),
            'groupid' => new external_value(PARAM_INT, 'Optional permitted group'),
            'fromdate' => new external_value(PARAM_ALPHANUMEXT, 'Inclusive local ISO date'),
            'todate' => new external_value(PARAM_ALPHANUMEXT, 'Inclusive local ISO date'),
        ]);
    }

    /**
     * @param int $courseid Course.
     * @param int $forumid Forum or zero.
     * @param int $groupid Group or zero.
     * @param string $fromdate ISO date.
     * @param string $todate ISO date.
     * @return array Permission-scoped aggregate statistics.
     */
    public static function execute(int $courseid, int $forumid, int $groupid,
            string $fromdate, string $todate): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'forumid' => $forumid,
            'groupid' => $groupid,
            'fromdate' => $fromdate,
            'todate' => $todate,
        ]);
        require_login();
        if (isguestuser() || (int)$params['courseid'] <= 0) {
            throw new \required_capability_exception(\context_system::instance(),
                'mod/forum:viewparticipationreport', 'nopermissions', '');
        }
        self::validate_context(\context_course::instance((int)$params['courseid']));
        return participation_report::get_page((int)$params['courseid'],
            (int)$params['forumid'], (int)$params['groupid'],
            $params['fromdate'], $params['todate']);
    }

    public static function execute_returns(): external_single_structure {
        $metrics = [
            'discussions' => new external_value(PARAM_INT, 'Visible discussions initiated in period'),
            'replies' => new external_value(PARAM_INT, 'Visible replies posted in period'),
            'participants' => new external_value(PARAM_INT, 'Distinct visible post authors'),
            'responded' => new external_value(PARAM_INT, 'Period discussions answered by others'),
            'coverage' => new external_value(PARAM_FLOAT, 'Response coverage as percent'),
            'medianhours' => new external_value(PARAM_FLOAT, 'Median first response hours'),
            'accepted' => new external_value(PARAM_INT, 'Currently visible accepted solutions'),
        ];
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Selected course'),
            'forumid' => new external_value(PARAM_INT, 'Selected forum'),
            'groupid' => new external_value(PARAM_INT, 'Selected group'),
            'fromdate' => new external_value(PARAM_ALPHANUMEXT, 'First date'),
            'todate' => new external_value(PARAM_ALPHANUMEXT, 'Last date'),
            'metrics' => new external_single_structure($metrics),
            'forums' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Forum ID'),
                'name' => new external_value(PARAM_TEXT, 'Visible forum name'),
                'url' => new external_value(PARAM_URL, 'Forum URL'),
                'summaryurl' => new external_value(PARAM_RAW, 'Permitted native user summary URL'),
                'queueurl' => new external_value(PARAM_RAW, 'Permitted teacher queue URL'),
            ] + $metrics)),
            'series' => new external_multiple_structure(new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Day or week start'),
                'discussions' => new external_value(PARAM_INT, 'Discussion starters'),
                'replies' => new external_value(PARAM_INT, 'Replies'),
                'discussionwidth' => new external_value(PARAM_INT, 'Normalised graph width 0 to 100'),
                'replywidth' => new external_value(PARAM_INT, 'Normalised graph width 0 to 100'),
            ])),
        ]);
    }
}
