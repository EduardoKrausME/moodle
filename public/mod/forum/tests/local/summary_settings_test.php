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
 * Validate the cross-component summary options before they affect queries.
 *
 * @package mod_forum
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\mod_forum\local\summary_settings::class)]
final class summary_settings_test extends \advanced_testcase {
    public function test_defaults_preserve_existing_course_overview(): void {
        $this->resetAfterTest();
        unset_config('forumsummarylocation', 'block_myoverview');
        unset_config('forumsummarycount', 'block_myoverview');
        unset_config('forumsummarysource', 'block_myoverview');

        $this->assertTrue(summary_settings::show_on_my_courses());
        $this->assertFalse(summary_settings::show_on_course());
        $this->assertSame(3, summary_settings::count());
        $this->assertSame('selected', summary_settings::source());
        $this->assertTrue(summary_settings::includes_forum((object)[
            'type' => 'general', 'showinmycourses' => 1,
        ]));
        $this->assertFalse(summary_settings::includes_forum((object)[
            'type' => 'general', 'showinmycourses' => 0,
        ]));
    }

    public function test_locations_are_exclusive_and_unrecognised_values_fall_back(): void {
        $this->resetAfterTest();
        set_config('forumsummarylocation', 'course', 'block_myoverview');
        $this->assertFalse(summary_settings::show_on_my_courses());
        $this->assertTrue(summary_settings::show_on_course());

        set_config('forumsummarylocation', 'hidden', 'block_myoverview');
        $this->assertFalse(summary_settings::show_on_my_courses());
        $this->assertFalse(summary_settings::show_on_course());

        set_config('forumsummarylocation', 'unexpected', 'block_myoverview');
        $this->assertTrue(summary_settings::show_on_my_courses());
    }

    public function test_count_whitelist_rejects_invalid_values(): void {
        $this->resetAfterTest();
        foreach ([3, 5, 10] as $count) {
            set_config('forumsummarycount', $count, 'block_myoverview');
            $this->assertSame($count, summary_settings::count());
        }
        foreach ([0, -1, 4, 99] as $count) {
            set_config('forumsummarycount', $count, 'block_myoverview');
            $this->assertSame(3, summary_settings::count());
        }
    }

    public function test_selected_all_and_news_sources(): void {
        $this->resetAfterTest();
        $selected = (object)['type' => 'general', 'showinmycourses' => 1];
        $disabled = (object)['type' => 'general', 'showinmycourses' => 0];
        $news = (object)['type' => 'news', 'showinmycourses' => 0];

        set_config('forumsummarysource', 'selected', 'block_myoverview');
        $this->assertTrue(summary_settings::includes_forum($selected));
        $this->assertFalse(summary_settings::includes_forum($disabled));
        $this->assertFalse(summary_settings::includes_forum($news));

        set_config('forumsummarysource', 'all', 'block_myoverview');
        $this->assertTrue(summary_settings::includes_forum($disabled));
        $this->assertTrue(summary_settings::includes_forum($news));

        set_config('forumsummarysource', 'news', 'block_myoverview');
        $this->assertFalse(summary_settings::includes_forum($selected));
        $this->assertTrue(summary_settings::includes_forum($news));

        set_config('forumsummarysource', 'invalid', 'block_myoverview');
        $this->assertSame('selected', summary_settings::source());
        $this->assertFalse(summary_settings::includes_forum($disabled));
    }
}
