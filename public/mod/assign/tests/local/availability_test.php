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
 * Tests the assignment availability presentation without changing permissions.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(availability::class)]
final class availability_test extends \basic_testcase {
    public function test_future_opening_and_due_soon(): void {
        $future = availability::describe(100, 200, 1000, 1100, 0, false, false, false, true);
        $this->assertSame('upcoming', $future['state']);
        $soon = availability::describe(100, 0, 100 + DAYSECS, 0, 0, true, false, false, true);
        $this->assertSame('duesoon', $soon['state']);
    }

    public function test_late_window_is_distinct_from_cutoff(): void {
        $late = availability::describe(201, 0, 200, 300, 0, true, false, false, true);
        $this->assertSame('late', $late['state']);
        $this->assertTrue($late['late']);
        $closed = availability::describe(301, 0, 200, 300, 0, false, false, false, true);
        $this->assertSame('closed', $closed['state']);
    }

    public function test_user_extension_does_not_reduce_due_or_cutoff(): void {
        $extended = availability::describe(201, 0, 200, 300, 400, true, false, false, true);
        $this->assertSame(400, $extended['effectivedue']);
        $this->assertSame(400, $extended['effectivecutoff']);
        $this->assertTrue($extended['hasextension']);
        $short = availability::describe(201, 0, 200, 300, 150, true, false, false, true);
        $this->assertSame(200, $short['effectivedue']);
        $this->assertSame(300, $short['effectivecutoff']);
        $this->assertFalse($short['hasextension']);
    }

    public function test_extension_does_not_create_unconfigured_cutoff(): void {
        $result = availability::describe(100, 0, 0, 0, 300, true, false, false, true);
        $this->assertSame(0, $result['effectivedue']);
        $this->assertSame(0, $result['effectivecutoff']);
    }

    public function test_missing_permission_is_not_misreported_as_closed(): void {
        $result = availability::describe(100, 0, 500, 600, 0, false, false, false, true);
        $this->assertSame('unavailable', $result['state']);
    }

    public function test_submission_and_locked_states_take_precedence(): void {
        $this->assertSame('submitted',
            availability::describe(400, 0, 100, 200, 0, false, true, true, true)['state']);
        $this->assertSame('locked',
            availability::describe(100, 0, 300, 400, 0, false, false, true, true)['state']);
        $this->assertSame('unavailable',
            availability::describe(100, 0, 300, 400, 0, false, false, false, false)['state']);
    }

    public function test_no_due_date_stays_open_if_native_access_permits(): void {
        $result = availability::describe(1000, 0, 0, 0, 0, true, false, false, true);
        $this->assertSame('open', $result['state']);
    }
}
