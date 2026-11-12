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

use mod_quiz\output\attempt_progress;
use question_attempt;
use question_state;

defined('MOODLE_INTERNAL') || die();

/**
 * Confirmation gets read-only counts from the persisted question engine.
 *
 * @package mod_quiz
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attempt_progress::class)]
final class submission_confirmation_test extends \advanced_testcase {
    /**
     * Create a quiz attempt without actual form controls or answers.
     *
     * @param string $navmethod Free or sequential.
     * @return quiz_attempt
     */
    private function make_attempt(string $navmethod): quiz_attempt {
        $states = [
            1 => question_state::$complete,
            2 => question_state::$todo,
            3 => question_state::$invalid,
            4 => question_state::$todo,
        ];
        $qas = [];
        foreach ($states as $slot => $state) {
            $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
                ->onlyMethods(['get_state', 'is_flagged'])->getMock();
            $qa->method('get_state')->willReturn($state);
            $qa->method('is_flagged')->willReturn($slot === 1 || $slot === 2);
            $qas[$slot] = $qa;
        }

        $attempt = $this->getMockBuilder(quiz_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_slots', 'get_question_attempt', 'get_navigation_method',
                'is_real_question', 'is_blocked_by_previous_question'])->getMock();
        $attempt->method('get_slots')->willReturn(array_keys($states));
        $attempt->method('get_question_attempt')->willReturnCallback(static fn($slot) => $qas[$slot]);
        $attempt->method('get_navigation_method')->willReturn($navmethod);
        $attempt->method('is_real_question')->willReturnCallback(static fn($slot) => $slot !== 4 ? 1 : 0);
        $attempt->method('is_blocked_by_previous_question')->willReturn(false);
        return $attempt;
    }

    public function test_free_navigation_includes_confirmed_counts_only(): void {
        $this->assertSame([
            'total' => 3,
            'answered' => 1,
            'pending' => 2,
            'flagged' => 2,
            'navmethod' => QUIZ_NAVMETHOD_FREE,
        ], attempt_progress::for_submission_confirmation($this->make_attempt(QUIZ_NAVMETHOD_FREE)));
    }

    public function test_sequential_navigation_uses_same_counts_without_exposing_answers(): void {
        $this->assertSame([
            'total' => 3,
            'answered' => 1,
            'pending' => 2,
            'flagged' => 2,
            'navmethod' => QUIZ_NAVMETHOD_SEQ,
        ], attempt_progress::for_submission_confirmation($this->make_attempt(QUIZ_NAVMETHOD_SEQ)));
    }

    public function test_empty_attempt_has_zero_counts(): void {
        $attempt = $this->getMockBuilder(quiz_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_slots', 'get_navigation_method'])->getMock();
        $attempt->method('get_slots')->willReturn([]);
        $attempt->method('get_navigation_method')->willReturn(QUIZ_NAVMETHOD_FREE);
        $this->assertSame([
            'total' => 0,
            'answered' => 0,
            'pending' => 0,
            'flagged' => 0,
            'navmethod' => QUIZ_NAVMETHOD_FREE,
        ], attempt_progress::for_submission_confirmation($attempt));
    }
}
