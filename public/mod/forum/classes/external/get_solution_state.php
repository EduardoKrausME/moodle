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
use core_external\external_single_structure;
use core_external\external_value;
use mod_forum\local\solution_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Get the accepted solution visible to the authenticated viewer.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_solution_state extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'discussionid' => new external_value(PARAM_INT, 'Discussion'),
        ]);
    }

    public static function execute(int $discussionid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['discussionid' => $discussionid]);
        require_login();
        $state = solution_manager::get_state((int)$params['discussionid']);
        return $state;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Is accepted solution enabled'),
            'canmanage' => new external_value(PARAM_BOOL, 'Can change accepted reply'),
            'postid' => new external_value(PARAM_INT, 'Visible accepted post or 0'),
            'url' => new external_value(PARAM_URL, 'Direct link when visible', VALUE_DEFAULT, ''),
        ]);
    }
}
