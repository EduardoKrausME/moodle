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
use mod_forum\local\feed_reply;

/**
 * Mention autocomplete restricted to authors visible in a discussion.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class search_feed_mentions extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'Visible post to reply to'),
            'query' => new external_value(PARAM_TEXT, 'Participant name, at least two letters'),
        ]);
    }

    public static function execute(int $postid, string $query): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'postid' => $postid, 'query' => $query,
        ]);
        require_login();
        $data = feed_reply::context((int)$params['postid']);
        self::validate_context($data['context']);
        if (!$data['canreply']) {
            throw new \required_capability_exception(
                $data['context'], 'mod/forum:replypost', 'nopermissions', '');
        }
        return ['users' => feed_reply::search_mentions($data, $params['query'])];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'users' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Participant ID'),
                'name' => new external_value(PARAM_TEXT, 'Public name'),
            ])),
        ]);
    }
}
