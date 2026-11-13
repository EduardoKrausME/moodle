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
 * Presentation-only grading dashboard tests.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(grading_dashboard::class)]
final class grading_dashboard_test extends \basic_testcase {
    private function describe(
        int $participants = 20,
        int $submitted = 10,
        int $drafts = 3,
        int $pending = 4,
        bool $teams = false,
        bool $submissionsenabled = true,
        bool $draftsenabled = true,
        bool $gradedenabled = true
    ): array {
        return grading_dashboard::describe(
            $participants,
            $submitted,
            $drafts,
            $pending,
            $teams,
            $submissionsenabled,
            $draftsenabled,
            $gradedenabled,
            [
                'participants' => '/grading?status=',
                'submitted' => '/grading?status=submitted',
                'drafts' => '/grading?status=draft',
                'pending' => '/grading?status=requiregrading',
                'graded' => '/grading?status=graded',
            ]
        );
    }

    private function card(array $result, string $key): array {
        foreach ($result['cards'] as $card) {
            if ($card['key'] === $key) {
                return $card;
            }
        }
        throw new \LogicException('Missing dashboard card');
    }

    public function test_normal_counts_reconcile_with_latest_submitted_attempts(): void {
        $data = $this->describe();
        $this->assertSame(20, $this->card($data, 'participants')['value']);
        $this->assertSame(10, $this->card($data, 'submitted')['value']);
        $this->assertSame(3, $this->card($data, 'drafts')['value']);
        $this->assertSame(4, $this->card($data, 'pending')['value']);
        $this->assertSame(6, $this->card($data, 'graded')['value']);
        $this->assertSame(50, $data['percentage']);
    }

    public function test_empty_course_has_no_division_by_zero(): void {
        $data = $this->describe(0, 0, 0, 0);
        $this->assertSame(0, $data['percentage']);
        $this->assertSame(0, $this->card($data, 'graded')['value']);
    }

    public function test_pending_never_exceeds_submitted_when_old_attempts_exist(): void {
        $data = $this->describe(5, 2, 0, 25);
        $this->assertSame(2, $this->card($data, 'pending')['value']);
        $this->assertSame(0, $this->card($data, 'graded')['value']);
    }

    public function test_values_are_clamped_nonnegative(): void {
        $data = $this->describe(-2, -1, -5, -9);
        foreach ($data['cards'] as $card) {
            $this->assertGreaterThanOrEqual(0, $card['value']);
        }
    }

    public function test_percentage_is_bounded_when_dataset_has_inconsistent_totals(): void {
        $data = $this->describe(1, 3, 0, 0);
        $this->assertSame(100, $data['percentage']);
    }

    public function test_draft_indicator_is_not_applicable_without_confirmation(): void {
        $data = $this->describe(draftsenabled: false);
        $this->assertFalse($this->card($data, 'drafts')['available']);
        $this->assertFalse($this->card($data, 'drafts')['hasurl']);
    }

    public function test_grading_indicators_do_not_claim_a_grade_for_feedback_only(): void {
        $data = $this->describe(gradedenabled: false);
        $this->assertFalse($this->card($data, 'graded')['available']);
        $this->assertFalse($this->card($data, 'pending')['available']);
        $this->assertFalse($this->card($data, 'pending')['hasurl']);
    }

    public function test_no_submission_plugins_does_not_invent_submissions(): void {
        $data = $this->describe(submissionsenabled: false);
        $this->assertFalse($data['submissionsenabled']);
        $this->assertFalse($this->card($data, 'submitted')['available']);
        $this->assertFalse($this->card($data, 'graded')['available']);
        $this->assertTrue($this->card($data, 'participants')['available']);
    }

    public function test_team_card_never_links_to_per_student_filtered_counts(): void {
        $data = $this->describe(teams: true);
        $this->assertTrue($data['teams']);
        $this->assertTrue($this->card($data, 'participants')['hasurl']);
        $this->assertFalse($this->card($data, 'submitted')['hasurl']);
        $this->assertFalse($this->card($data, 'pending')['hasurl']);
        $this->assertFalse($this->card($data, 'graded')['hasurl']);
    }

    public function test_individual_cards_use_existing_native_filter_urls(): void {
        $data = $this->describe();
        $this->assertSame('/grading?status=requiregrading', $this->card($data, 'pending')['url']);
        $this->assertSame('/grading?status=graded', $this->card($data, 'graded')['url']);
    }
}
