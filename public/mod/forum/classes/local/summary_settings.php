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
 * Shared, validated presentation preferences for course forum summaries.
 *
 * This is a display preference, not an access-control mechanism. All returned
 * posts must still pass the Moodle forum visibility and permission checks.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class summary_settings {
    public const LOCATION_MYCOURSES = 'mycourses';
    public const LOCATION_COURSE = 'course';
    public const LOCATION_HIDDEN = 'hidden';
    public const SOURCE_SELECTED = 'selected';
    public const SOURCE_ALL = 'all';
    public const SOURCE_NEWS = 'news';

    /**
     * Default to the previously implemented My courses placement.
     *
     * @return string
     */
    public static function location(): string {
        $value = get_config('block_myoverview', 'forumsummarylocation');
        return in_array($value, [self::LOCATION_MYCOURSES, self::LOCATION_COURSE,
            self::LOCATION_HIDDEN], true) ? $value : self::LOCATION_MYCOURSES;
    }

    /**
     * Limit the number of summaries rather than accepting an arbitrary client number.
     *
     * @return int
     */
    public static function count(): int {
        $count = (int)get_config('block_myoverview', 'forumsummarycount');
        return in_array($count, [3, 5, 10], true) ? $count : 3;
    }

    /**
     * Preserve existing per-forum showinmycourses settings as the default.
     *
     * @return string
     */
    public static function source(): string {
        $value = get_config('block_myoverview', 'forumsummarysource');
        return in_array($value, [self::SOURCE_SELECTED, self::SOURCE_ALL,
            self::SOURCE_NEWS], true) ? $value : self::SOURCE_SELECTED;
    }

    /**
     * Apply the same source policy in the recent list and unread counters.
     *
     * @param \stdClass $forum A forum record already associated with a visible cm.
     * @return bool Whether it is included as a source (not whether it is visible).
     */
    public static function includes_forum(\stdClass $forum): bool {
        switch (self::source()) {
            case self::SOURCE_ALL:
                return true;
            case self::SOURCE_NEWS:
                // Moodle's Announcements forum has the native type 'news'.
                return $forum->type === 'news';
            default:
                return !empty($forum->showinmycourses);
        }
    }

    /**
     * Whether the summary component is displayed in the course overview.
     *
     * @return bool
     */
    public static function show_on_my_courses(): bool {
        return self::location() === self::LOCATION_MYCOURSES;
    }

    /**
     * Whether the summary component may be used by a course-page block.
     *
     * @return bool
     */
    public static function show_on_course(): bool {
        return self::location() === self::LOCATION_COURSE;
    }
}
