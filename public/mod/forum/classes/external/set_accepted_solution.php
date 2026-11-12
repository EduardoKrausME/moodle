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
 * Select, replace or remove a reply accepted by this discussion.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_accepted_solution extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'discussionid' => new external_value(PARAM_INT, 'Discussion'),
            'postid' => new external_value(PARAM_INT, 'Accepted reply or zero to remove'),
        ]);
    }

    public static function execute(int $discussionid, int $postid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'discussionid' => $discussionid, 'postid' => $postid,
        ]);
        require_login();
        return solution_manager::set_solution((int)$params['discussionid'], (int)$params['postid']);
    }

    public static function execute_returns(): external_single_structure {
        return get_solution_state::execute_returns();
    }
}
