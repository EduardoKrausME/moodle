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

namespace mod_forum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Auditable event for accepting, replacing or removing a solution.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class solution_updated extends \core\event\base {
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'forum_solutions';
    }

    public static function get_name(): string {
        return get_string('solutioneventupdated', 'mod_forum');
    }

    public function get_description(): string {
        return "The user with id '{$this->userid}' {$this->other['action']} the accepted solution " .
            "for discussion '{$this->other['discussionid']}'.";
    }

    public function get_url(): \moodle_url {
        $params = ['d' => $this->other['discussionid']];
        if (!empty($this->other['postid'])) {
            $params['targetpost'] = $this->other['postid'];
        }
        return new \moodle_url('/mod/forum/discuss.php', $params);
    }

    public static function get_objectid_mapping(): array {
        return ['db' => 'forum_solutions', 'restore' => 'forum_solution'];
    }

    public static function get_other_mapping(): array {
        return [
            'forumid' => ['db' => 'forum', 'restore' => 'forum'],
            'discussionid' => ['db' => 'forum_discussions', 'restore' => 'forum_discussion'],
            'postid' => ['db' => 'forum_posts', 'restore' => 'forum_post'],
            'previouspostid' => ['db' => 'forum_posts', 'restore' => 'forum_post'],
        ];
    }
}
