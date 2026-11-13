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

namespace mod_forum\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Authorised sources, date filters and reusable forum participation report.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class participation_report {
    public const MAX_DAYS = 90;

    /**
     * Return only visible forum modules with explicit report capability.
     *
     * @param int $courseid Mandatory course.
     * @param int $forumid Optional specific forum.
     * @return array Forum ID => module, course, context and forum record.
     */
    private static function sources(int $courseid, int $forumid = 0): array {
        global $DB, $USER;

        require_login();
        if ($courseid <= 0 || $forumid < 0 || isguestuser()) {
            throw new \invalid_parameter_exception('Invalid participation report source.');
        }
        $courses = recent_feed::get_accessible_courses($courseid);
        if (!isset($courses[$courseid])) {
            throw new \required_capability_exception(\context_course::instance($courseid),
                'mod/forum:viewparticipationreport', 'nopermissions', '');
        }
        $course = $courses[$courseid];
        require_login($course, false);
        $modulemap = [];
        foreach (get_fast_modinfo($course, $USER->id)->get_instances_of('forum') as $cm) {
            if (!$cm->uservisible || ($forumid && (int)$cm->instance !== $forumid)) {
                continue;
            }
            $context = \context_module::instance($cm->id);
            if (!has_capability('mod/forum:viewparticipationreport', $context, $USER) ||
                    !has_capability('mod/forum:viewdiscussion', $context, $USER)) {
                continue;
            }
            $modulemap[(int)$cm->instance] = [
                'cm' => $cm, 'context' => $context, 'course' => $course,
            ];
        }
        if (!$modulemap) {
            throw new \required_capability_exception(\context_course::instance($courseid),
                'mod/forum:viewparticipationreport', 'nopermissions', '');
        }
        $records = $DB->get_records_list('forum', 'id', array_keys($modulemap));
        foreach ($modulemap as $id => &$item) {
            if (!isset($records[$id])) {
                unset($modulemap[$id]);
                continue;
            }
            $item['forum'] = $records[$id];
        }
        unset($item);
        return $modulemap;
    }

    /**
     * Course dropdown includes only courses with an authorised forum.
     *
     * @return array Course options with IDs and names.
     */
    public static function get_course_options(): array {
        global $USER;

        require_login();
        $options = [];
        foreach (recent_feed::get_accessible_courses() as $course) {
            foreach (get_fast_modinfo($course, $USER->id)->get_instances_of('forum') as $cm) {
                $context = \context_module::instance($cm->id);
                if ($cm->uservisible &&
                        has_capability('mod/forum:viewparticipationreport', $context, $USER) &&
                        has_capability('mod/forum:viewdiscussion', $context, $USER)) {
                    $options[] = [
                        'id' => (int)$course->id,
                        'name' => format_string($course->fullname, true,
                            ['context' => \context_course::instance($course->id)]),
                    ];
                    break;
                }
            }
        }
        usort($options, static fn($a, $b) => strcoll($a['name'], $b['name']));
        return $options;
    }

    /**
     * Group availability is calculated against each activity's group mode and grouping.
     *
     * @param array $modules Permission-checked modules.
     * @return array Group ID => name and eligible forum IDs.
     */
    private static function available_groups(array $modules): array {
        global $USER;

        $result = [];
        foreach ($modules as $forumid => $item) {
            $mode = groups_get_activity_groupmode($item['cm'], $item['course']);
            if (!$mode) {
                continue;
            }
            $allgroups = $mode === VISIBLEGROUPS ||
                has_capability('moodle/site:accessallgroups', $item['context'], $USER);
            $groups = groups_get_all_groups(
                $item['course']->id, 0, $item['cm']->groupingid);
            foreach ($groups ?: [] as $group) {
                if (!$allgroups && !groups_is_member($group->id, $USER->id)) {
                    continue;
                }
                if (!isset($result[$group->id])) {
                    $result[$group->id] = [
                        'id' => (int)$group->id,
                        'name' => format_string($group->name, true,
                            ['context' => \context_course::instance($item['course']->id)]),
                        'forumids' => [],
                    ];
                }
                $result[$group->id]['forumids'][$forumid] = true;
            }
        }
        uasort($result, static fn($a, $b) => strcoll($a['name'], $b['name']));
        return $result;
    }

    /**
     * Strict user-local dates, including DST transitions.
     *
     * @param string $fromdate ISO date.
     * @param string $todate ISO date.
     * @return array Inclusive start and exclusive next-day end timestamps.
     */
    public static function date_range(string $fromdate, string $todate): array {
        global $USER;

        $timezone = new \DateTimeZone(\core_date::get_user_timezone($USER));
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $fromdate, $timezone);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $todate, $timezone);
        if (!$start || !$end || $start->format('Y-m-d') !== $fromdate ||
                $end->format('Y-m-d') !== $todate) {
            throw new \invalid_parameter_exception('Invalid participation dates.');
        }
        $days = (int)$start->diff($end)->format('%r%a') + 1;
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw new \invalid_parameter_exception('Invalid participation date span.');
        }
        return [$start->getTimestamp(), $end->modify('+1 day')->getTimestamp()];
    }

    /**
     * Produce one exact, context-filtered aggregated report.
     *
     * @param int $courseid Course ID.
     * @param int $forumid Forum ID, zero for permitted forums.
     * @param int $groupid Group ID, zero for all.
     * @param string $fromdate First local date.
     * @param string $todate Last local date.
     * @return array Safe template and external service report data.
     */
    public static function get_page(int $courseid, int $forumid, int $groupid,
            string $fromdate, string $todate): array {
        if ($groupid < 0) {
            throw new \invalid_parameter_exception('Invalid participation group.');
        }
        [$start, $end] = self::date_range($fromdate, $todate);
        $modules = self::sources($courseid, $forumid);
        $allgroups = self::available_groups($modules);
        if ($groupid > 0) {
            if (!isset($allgroups[$groupid])) {
                throw new \invalid_parameter_exception('Invalid or inaccessible participation group.');
            }
            $modules = array_intersect_key($modules, $allgroups[$groupid]['forumids']);
        }
        $data = participation_metrics::calculate($modules, $groupid, $start, $end, $fromdate, $todate);
        $data['courseid'] = $courseid;
        $data['forumid'] = $forumid;
        $data['groupid'] = $groupid;
        $data['fromdate'] = $fromdate;
        $data['todate'] = $todate;
        return $data;
    }

    /**
     * Options for the selected course; an inaccessible forum is never shown.
     *
     * @param int $courseid Valid course.
     * @return array Visible forum options.
     */
    public static function get_forum_options(int $courseid): array {
        $modules = self::sources($courseid);
        $items = [];
        foreach ($modules as $id => $module) {
            $items[] = [
                'id' => $id,
                'name' => format_string($module['forum']->name, true, ['context' => $module['context']]),
            ];
        }
        usort($items, static fn($a, $b) => strcoll($a['name'], $b['name']));
        return $items;
    }

    /**
     * Groups that can be selected for the chosen course/forum.
     *
     * @param int $courseid Course.
     * @param int $forumid Forum or zero.
     * @return array Named permitted groups.
     */
    public static function get_group_options(int $courseid, int $forumid = 0): array {
        $groups = self::available_groups(self::sources($courseid, $forumid));
        return array_map(static fn($group) => [
            'id' => $group['id'], 'name' => $group['name'],
        ], array_values($groups));
    }

    /**
     * CSV formula defence for untrusted forum and course names.
     *
     * @param string $value Cell content.
     * @return string Spreadsheet-safe plain text.
     */
    public static function csv_cell(string $value): string {
        $value = trim(strip_tags($value));
        if (preg_match('/^[=+\-@\t\r\n]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}
