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
 * Test native question state accounting without consulting submitted HTML.
 *
 * @package mod_quiz
 */
#[\PHPUnit\Framework\Attributes\CoversClass(attempt_progress::class)]
final class attempt_progress_test extends \advanced_testcase {
    public function test_answered_states(): void {
        foreach ([
            question_state::$complete,
            question_state::$needsgrading,
            question_state::$finished,
            question_state::$gradedright,
            question_state::$gradedwrong,
            question_state::$gradedpartial,
            question_state::$manfinished,
            question_state::$mangrwrong,
        ] as $state) {
            $this->assertTrue(attempt_progress::is_answered($state), (string) $state);
        }
    }

    public function test_pending_states(): void {
        foreach ([
            question_state::$notstarted,
            question_state::$unprocessed,
            question_state::$todo,
            question_state::$invalid,
            question_state::$gaveup,
            question_state::$mangaveup,
        ] as $state) {
            $this->assertFalse(attempt_progress::is_answered($state), (string) $state);
        }
    }

    public function test_summary_excludes_descriptions_and_preserves_flag_independently(): void {
        $states = [
            1 => question_state::$complete,
            2 => question_state::$todo,
            3 => question_state::$invalid,
            4 => question_state::$todo, // Informational description, not counted.
            5 => question_state::$complete, // Blocked dependent question.
            6 => question_state::$needsgrading,
        ];
        $flags = [1 => true, 2 => true, 3 => false, 4 => true, 5 => false, 6 => false];
        $qas = [];
        foreach ($states as $slot => $state) {
            $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
                ->onlyMethods(['get_state', 'is_flagged'])->getMock();
            $qa->method('get_state')->willReturn($state);
            $qa->method('is_flagged')->willReturn($flags[$slot]);
            $qas[$slot] = $qa;
        }

        $attempt = $this->getMockBuilder(quiz_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_slots', 'is_real_question', 'is_blocked_by_previous_question',
                'get_question_attempt'])->getMock();
        $attempt->method('get_slots')->willReturn(array_keys($states));
        $attempt->method('is_real_question')->willReturnCallback(
            static fn($slot) => $slot !== 4 ? 1 : 0);
        $attempt->method('is_blocked_by_previous_question')->willReturnCallback(
            static fn($slot) => $slot === 5);
        $attempt->method('get_question_attempt')->willReturnCallback(
            static fn($slot) => $qas[$slot]);

        $this->assertSame([
            'total' => 5,
            'answered' => 2,
            'pending' => 3,
            'flagged' => 2,
        ], attempt_progress::summarise($attempt));

        // The final summary's row badges must agree with the navigation counts.
        $this->assertTrue(attempt_progress::is_slot_answered($attempt, 1));
        $this->assertFalse(attempt_progress::is_slot_answered($attempt, 2));
        $this->assertFalse(attempt_progress::is_slot_answered($attempt, 3));
        $this->assertFalse(attempt_progress::is_slot_answered($attempt, 5));
        $this->assertTrue(attempt_progress::is_slot_answered($attempt, 6));
    }

    public function test_empty_attempt_is_valid(): void {
        $attempt = $this->getMockBuilder(quiz_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_slots'])->getMock();
        $attempt->method('get_slots')->willReturn([]);
        $this->assertSame([
            'total' => 0, 'answered' => 0, 'pending' => 0, 'flagged' => 0,
        ], attempt_progress::summarise($attempt));
    }
}
