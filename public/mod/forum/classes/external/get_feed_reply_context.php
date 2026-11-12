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
use mod_forum\local\feed_reply;

/**
 * Return the native, permission-checked reply context and safe citation.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_feed_reply_context extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'Post to reply to'),
        ]);
    }

    public static function execute(int $postid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['postid' => $postid]);
        require_login();
        $data = feed_reply::context((int)$params['postid']);
        self::validate_context($data['context']);
        if (!$data['canreply']) {
            throw new \required_capability_exception(
                $data['context'], 'mod/forum:replypost', 'nopermissions', '');
        }
        return feed_reply::describe($data);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'postid' => new external_value(PARAM_INT, 'Visible parent post'),
            'discussionid' => new external_value(PARAM_INT, 'Discussion'),
            'canreply' => new external_value(PARAM_BOOL, 'Native reply permission'),
            'subject' => new external_value(PARAM_TEXT, 'Parent subject'),
            'author' => new external_value(PARAM_TEXT, 'Visible author'),
            'quote' => new external_value(PARAM_TEXT, 'Short plain text citation'),
        ]);
    }
}
