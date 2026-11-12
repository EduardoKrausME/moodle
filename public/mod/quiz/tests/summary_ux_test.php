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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_quiz;

use mod_quiz\output\renderer;
use question_attempt;
use question_state;

defined('MOODLE_INTERNAL') || die();

/**
 * Rendering tests for the pre-submission, read-only attempt summary.
 *
 * @package mod_quiz
 */
#[\PHPUnit\Framework\Attributes\CoversClass(renderer::class)]
final class summary_ux_test extends \advanced_testcase {
    /**
     * Mock an authorised attempt with representative persisted question states.
     *
     * @param array $states State objects keyed by question slot.
     * @param array $flags Flagged slots.
     * @param string $navmethod Moodle quiz navigation method.
     * @param string $attemptstate Native attempt status.
     * @return quiz_attempt
     */
    private function make_attempt(
        array $states,
        array $flags = [],
        string $navmethod = QUIZ_NAVMETHOD_FREE,
        string $attemptstate = quiz_attempt::IN_PROGRESS,
    ): quiz_attempt {
        $qas = [];
        foreach ($states as $slot => $state) {
            $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
                ->onlyMethods(['get_state', 'is_flagged'])->getMock();
            $qa->method('get_state')->willReturn($state);
            $qa->method('is_flagged')->willReturn(in_array($slot, $flags, true));
            $qas[$slot] = $qa;
        }

        $attempt = $this->getMockBuilder(quiz_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_slots', 'get_question_attempt', 'is_real_question',
                'is_blocked_by_previous_question', 'get_navigation_method', 'get_state'])
            ->getMock();
        $attempt->method('get_slots')->willReturn(array_keys($states));
        $attempt->method('get_question_attempt')->willReturnCallback(
            static fn($slot) => $qas[$slot]);
        // Slot 3 represents an information item, which is never included.
        $attempt->method('is_real_question')->willReturnCallback(static fn($slot) => $slot !== 3 ? 1 : 0);
        $attempt->method('is_blocked_by_previous_question')->willReturn(false);
        $attempt->method('get_navigation_method')->willReturn($navmethod);
        $attempt->method('get_state')->willReturn($attemptstate);
        return $attempt;
    }

    /**
     * Render the overview using Moodle's own renderer and language pack.
     *
     * @param quiz_attempt $attempt
     * @return string
     */
    private function render_progress(quiz_attempt $attempt): string {
        global $PAGE;
        $PAGE->set_context(\context_system::instance());
        $renderer = $PAGE->get_renderer('mod_quiz');
        return $renderer->summary_progress($attempt);
    }

    public function test_free_navigation_warns_about_pending_questions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $attempt = $this->make_attempt([
            1 => question_state::$complete,
            2 => question_state::$todo,
            3 => question_state::$todo,
            4 => question_state::$needsgrading,
        ], [1, 2]);

        $html = $this->render_progress($attempt);
        $this->assertStringContainsString('id="mod-quiz-summary-progress"', $html);
        $this->assertStringContainsString('data-summary-count="answered">2</strong>', $html);
        $this->assertStringContainsString('data-summary-count="pending">1</strong>', $html);
        $this->assertStringContainsString('data-summary-count="flagged">2</strong>', $html);
        $this->assertStringContainsString('aria-valuenow="2"', $html);
        $this->assertStringContainsString('aria-valuemax="3"', $html);
        $this->assertStringContainsString('data-summary-notice="pending"', $html);
        $this->assertStringContainsString('width: 67%;', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('finishattempt', $html);
    }

    public function test_sequential_navigation_never_suggests_returning_to_questions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $attempt = $this->make_attempt([
            1 => question_state::$complete,
            2 => question_state::$todo,
        ], [], QUIZ_NAVMETHOD_SEQ);
        $html = $this->render_progress($attempt);
        $this->assertStringContainsString('data-summary-notice="restricted"', $html);
        $this->assertStringContainsString('Sequential navigation', $html);
        $this->assertStringNotContainsString('available for navigation', $html);
    }

    public function test_overdue_attempt_does_not_offer_editing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $attempt = $this->make_attempt([
            1 => question_state::$todo,
        ], [], QUIZ_NAVMETHOD_FREE, quiz_attempt::OVERDUE);
        $html = $this->render_progress($attempt);
        $this->assertStringContainsString('data-summary-notice="overdue"', $html);
        $this->assertStringContainsString('answers can no longer be changed', $html);
    }

    public function test_completed_attempt_has_no_pending_warning(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $attempt = $this->make_attempt([
            1 => question_state::$complete,
            2 => question_state::$needsgrading,
        ], [2]);
        $html = $this->render_progress($attempt);
        $this->assertStringContainsString('data-summary-notice="complete"', $html);
        $this->assertStringContainsString('data-summary-count="pending">0</strong>', $html);
        $this->assertStringContainsString('aria-valuenow="2"', $html);
        $this->assertStringContainsString('width: 100%;', $html);
    }

    public function test_no_real_questions_does_not_divide_by_zero(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $attempt = $this->make_attempt([]);
        $html = $this->render_progress($attempt);
        $this->assertStringContainsString('data-summary-notice="neutral"', $html);
        $this->assertStringContainsString('aria-valuenow="0"', $html);
        $this->assertStringContainsString('width: 0%;', $html);
    }
}
