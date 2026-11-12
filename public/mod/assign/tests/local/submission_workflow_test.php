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
 * Presentation-only workflow state tests.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(submission_workflow::class)]
final class submission_workflow_test extends \basic_testcase {
    public function test_draft_with_submit_action_is_not_a_final_submission(): void {
        $state = submission_workflow::describe('draft', true, false, true);
        $this->assertSame('draftready', $state['state']);
        $this->assertTrue($state['preparationcomplete']);
        $this->assertFalse($state['finalcomplete']);
        $this->assertTrue($state['canfinalise']);
    }

    public function test_draft_without_submit_action_remains_pending(): void {
        $state = submission_workflow::describe('draft', true, false, false);
        $this->assertSame('draftpending', $state['state']);
        $this->assertTrue($state['preparationcomplete']);
        $this->assertFalse($state['canfinalise']);
    }

    public function test_submitted_attempt_never_offers_another_finalisation(): void {
        $state = submission_workflow::describe('submitted', true, false, true);
        $this->assertSame('sent', $state['state']);
        $this->assertTrue($state['finalcomplete']);
        $this->assertFalse($state['canfinalise']);
    }

    public function test_confirmed_team_member_can_be_waiting_for_others(): void {
        $state = submission_workflow::describe('submitted', true, false, false, true);
        $this->assertSame('groupwaiting', $state['state']);
        $this->assertTrue($state['preparationcomplete']);
        $this->assertTrue($state['finalcomplete']);
        $this->assertFalse($state['canfinalise']);
    }

    public function test_direct_submission_also_uses_submitted_state(): void {
        $state = submission_workflow::describe('submitted', false, false, false);
        $this->assertSame('sent', $state['state']);
        $this->assertFalse($state['requiresconfirmation']);
    }

    public function test_locked_draft_never_offers_final_submission(): void {
        $state = submission_workflow::describe('draft', true, true, true);
        $this->assertSame('locked', $state['state']);
        $this->assertFalse($state['canfinalise']);
    }

    public function test_reopened_attempt_is_not_an_existing_submission(): void {
        $state = submission_workflow::describe('reopened', true, false, false);
        $this->assertSame('reopened', $state['state']);
        $this->assertFalse($state['finalcomplete']);
        $this->assertFalse($state['preparationcomplete']);
    }

    public function test_new_attempt_is_not_a_saved_draft(): void {
        $state = submission_workflow::describe('new', true, false, false);
        $this->assertSame('notstarted', $state['state']);
        $this->assertFalse($state['preparationcomplete']);
    }

    public function test_legacy_draft_cannot_be_mislabeled_as_direct_submission(): void {
        $state = submission_workflow::describe('draft', false, false, false);
        $this->assertSame('draftpending', $state['state']);
        $this->assertFalse($state['finalcomplete']);
    }
}
