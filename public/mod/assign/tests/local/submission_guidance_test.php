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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

namespace mod_assign\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Regression tests for submission form guidance. This presentation-only helper
 * never changes File API or native online-text validation limits.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(submission_guidance::class)]
final class submission_guidance_test extends \basic_testcase {
    public function test_file_limits_keep_configured_maximums(): void {
        $result = submission_guidance::file_limits(3, 1048576, '.pdf,.docx');
        $this->assertSame(3, $result['maxfiles']);
        $this->assertSame(1048576, $result['maxbytes']);
        $this->assertSame('.pdf,.docx', $result['types']);
        $this->assertTrue($result['restrictedtypes']);
        $this->assertTrue($result['hassizelimit']);
    }

    public function test_file_type_config_is_trimmed_but_not_reinterpreted(): void {
        $result = submission_guidance::file_limits(2, 100000, '  .csv,.txt  ');
        $this->assertSame('.csv,.txt', $result['types']);
    }

    public function test_unrestricted_file_types_do_not_invent_restrictions(): void {
        $result = submission_guidance::file_limits(1, 0, '   ');
        $this->assertSame('', $result['types']);
        $this->assertFalse($result['restrictedtypes']);
        $this->assertFalse($result['hassizelimit']);
    }

    public function test_negative_file_values_do_not_leak_into_guidance(): void {
        $result = submission_guidance::file_limits(-1, -42, '');
        $this->assertSame(0, $result['maxfiles']);
        $this->assertSame(0, $result['maxbytes']);
    }

    public function test_active_word_limit_is_shown(): void {
        $this->assertSame(120, submission_guidance::word_limit(120, true));
    }

    public function test_disabled_word_limit_is_not_shown(): void {
        $this->assertNull(submission_guidance::word_limit(120, false));
    }

    public function test_non_positive_word_limit_is_not_shown(): void {
        $this->assertNull(submission_guidance::word_limit(0, true));
        $this->assertNull(submission_guidance::word_limit(-10, true));
    }
}
