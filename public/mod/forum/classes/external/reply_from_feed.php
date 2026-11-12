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
 * Post from the cross-course feed through Moodle's native forum service.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reply_from_feed extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'postid' => new external_value(PARAM_INT, 'Visible parent post'),
            'message' => new external_value(PARAM_RAW, 'User-authored plain reply'),
            'quote' => new external_value(PARAM_BOOL, 'Include validated server citation'),
            'mentionids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Selected eligible user ID')),
        ]);
    }

    public static function execute(int $postid, string $message, bool $quote, array $mentionids): array {
        global $CFG, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'postid' => $postid, 'message' => $message, 'quote' => $quote,
            'mentionids' => $mentionids,
        ]);
        require_login();
        $data = feed_reply::context((int)$params['postid']);
        self::validate_context($data['context']);
        if (!$data['canreply']) {
            throw new \required_capability_exception(
                $data['context'], 'mod/forum:replypost', 'nopermissions', '');
        }
        $mentionids = array_unique(array_map('intval', $params['mentionids']));
        if (count($mentionids) > feed_reply::MAX_MENTIONS) {
            throw new \invalid_parameter_exception('Too many forum mentions.');
        }

        // Server-provided quote; the browser cannot insert restricted content.
        $nativebody = feed_reply::message($data, $params['message'], (bool)$params['quote']);
        require_once($CFG->dirroot . '/mod/forum/externallib.php');
        $result = \mod_forum_external::add_discussion_post(
            $data['post']->id, $data['post']->subject, $nativebody, [], FORMAT_MOODLE);
        $newpostid = (int)$result['postid'];

        // A messaging provider failure must not make the client retry an
        // already-created reply. Notify only recipients who can see the post.
        $notified = 0;
        try {
            $notified = feed_reply::notify_mentions($data, $newpostid, $params['message'], $mentionids);
        } catch (\Throwable $e) {
            debugging('Forum feed mention notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $url = new \moodle_url('/mod/forum/discuss.php', [
            'd' => $data['discussion']->id,
            'targetpost' => $newpostid,
        ]);
        $url->set_anchor('p' . $newpostid);
        return ['postid' => $newpostid, 'url' => $url->out(false), 'notified' => $notified];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'postid' => new external_value(PARAM_INT, 'ID of published forum post'),
            'url' => new external_value(PARAM_URL, 'Native permanent post URL'),
            'notified' => new external_value(PARAM_INT, 'Mention notifications sent'),
        ]);
    }
}
