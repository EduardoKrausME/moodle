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
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

namespace mod_assign\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Test the non-authoritative UI representation of team submissions.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(group_submission_overview::class)]
final class group_submission_overview_test extends \basic_testcase {
    public function test_missing_group_is_blocked_only_when_required(): void {
        $state = group_submission_overview::describe(0, true, true, 'new', 0, 0, false, false);
        $this->assertSame('nogroup', $state['state']);
        $this->assertTrue($state['blocked']);
        $this->assertFalse($state['showprogress']);
        $this->assertFalse($state['defaultgroup']);
    }

    public function test_multiple_groups_is_blocked_when_one_is_required(): void {
        $state = group_submission_overview::describe(2, true, true, 'draft', 2, 1, false, false);
        $this->assertSame('multiplegroups', $state['state']);
        $this->assertTrue($state['blocked']);
        $this->assertFalse($state['showprogress']);
    }

    public function test_default_group_is_supported_when_allowed(): void {
        $state = group_submission_overview::describe(0, false, false, 'new', 0, 0, false, false);
        $this->assertSame('notstarted', $state['state']);
        $this->assertTrue($state['defaultgroup']);
        $this->assertFalse($state['blocked']);
        $this->assertTrue($state['onecanfinish']);
    }

    public function test_multiple_memberships_use_default_group_when_allowed(): void {
        $state = group_submission_overview::describe(2, false, false, 'draft', 0, 0, false, false);
        $this->assertSame('draft', $state['state']);
        $this->assertTrue($state['defaultgroup']);
        $this->assertTrue($state['onecanfinish']);
    }

    public function test_zero_confirmations_are_not_a_completed_group(): void {
        $state = group_submission_overview::describe(1, true, true, 'draft', 3, 0, false, false);
        $this->assertSame('draft', $state['state']);
        $this->assertSame(3, $state['pending']);
        $this->assertSame(0, $state['confirmed']);
        $this->assertTrue($state['showprogress']);
    }

    public function test_partial_confirmations_retain_pending_count(): void {
        $state = group_submission_overview::describe(1, true, true, 'draft', 3, 1, true, false);
        $this->assertSame('waiting', $state['state']);
        $this->assertSame(1, $state['confirmed']);
        $this->assertSame(2, $state['pending']);
        $this->assertTrue($state['myconfirmed']);
    }

    public function test_other_member_confirmation_does_not_mark_viewer_confirmed(): void {
        $state = group_submission_overview::describe(1, true, true, 'draft', 3, 1, false, false);
        $this->assertSame('draft', $state['state']);
        $this->assertFalse($state['myconfirmed']);
    }

    public function test_collective_status_is_authoritative_for_completion(): void {
        $state = group_submission_overview::describe(1, true, true, 'submitted', 3, 3, true, false);
        $this->assertSame('submitted', $state['state']);
        $this->assertSame(0, $state['pending']);
        $this->assertSame(3, $state['confirmed']);
    }

    public function test_suspended_or_removed_members_can_change_current_denominator(): void {
        $state = group_submission_overview::describe(1, true, true, 'draft', 2, 3, false, false);
        $this->assertSame(2, $state['total']);
        $this->assertSame(2, $state['confirmed']);
        $this->assertSame(0, $state['pending']);
    }

    public function test_no_eligible_members_hides_progress_bar(): void {
        $state = group_submission_overview::describe(1, true, true, 'new', 0, 0, false, false);
        $this->assertTrue($state['noeligiblemembers']);
        $this->assertFalse($state['showprogress']);
    }

    public function test_non_positive_counts_cannot_escape_to_template(): void {
        $state = group_submission_overview::describe(1, true, true, 'draft', -10, -5, false, false);
        $this->assertSame(0, $state['total']);
        $this->assertSame(0, $state['confirmed']);
        $this->assertSame(0, $state['pending']);
    }

    public function test_submission_without_individual_confirmation_does_not_invent_progress(): void {
        $state = group_submission_overview::describe(1, true, false, 'submitted', 3, 1, true, false);
        $this->assertFalse($state['showprogress']);
        $this->assertTrue($state['onecanfinish']);
        $this->assertFalse($state['myconfirmed']);
    }

    public function test_reopened_group_attempt_is_not_final(): void {
        $state = group_submission_overview::describe(1, true, true, 'reopened', 2, 0, false, false);
        $this->assertSame('reopened', $state['state']);
        $this->assertSame(2, $state['pending']);
    }

    public function test_locked_group_remains_locked_without_changing_permissions(): void {
        $state = group_submission_overview::describe(1, true, true, 'draft', 2, 1, false, true);
        $this->assertSame('locked', $state['state']);
        $this->assertTrue($state['showprogress']);
    }

    public function test_submitted_status_stays_submitted_when_marked_locked(): void {
        $state = group_submission_overview::describe(1, true, true, 'submitted', 2, 2, true, true);
        $this->assertSame('submitted', $state['state']);
    }
}
