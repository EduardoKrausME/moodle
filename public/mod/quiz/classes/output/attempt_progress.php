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

namespace mod_quiz\output;

use mod_quiz\quiz_attempt;
use question_state;

/**
 * Read-only summary of persisted question states for a quiz attempt.
 *
 * Never examines form values, answers or correctness and never changes the
 * question engine. Flagging is orthogonal to answered/pending.
 *
 * @package mod_quiz
 */
final class attempt_progress {
    /**
     * Determine whether a question has a persisted complete/graded answer.
     *
     * @param question_state $state The question engine state.
     * @return bool
     */
    public static function is_answered(question_state $state): bool {
        return !in_array($state, [
            question_state::$notstarted,
            question_state::$unprocessed,
            question_state::$todo,
            question_state::$invalid,
            question_state::$gaveup,
            question_state::$mangaveup,
        ], true);
    }

    /**
     * Summarise the authorised, already-loaded attempt without further queries.
     *
     * Each real question slot counts once, even when it has several subparts.
     * Descriptions are excluded and blocked dependencies remain pending.
     *
     * @param quiz_attempt $attemptobj Quiz attempt.
     * @return array{total:int,answered:int,pending:int,flagged:int}
     */
    public static function summarise(quiz_attempt $attemptobj): array {
        $total = 0;
        $answered = 0;
        $flagged = 0;
        foreach ($attemptobj->get_slots() as $slot) {
            if (!$attemptobj->is_real_question($slot)) {
                continue;
            }
            $total++;
            $qa = $attemptobj->get_question_attempt($slot);
            if ($qa->is_flagged()) {
                $flagged++;
            }
            if (!$attemptobj->is_blocked_by_previous_question($slot) &&
                    self::is_answered($qa->get_state())) {
                $answered++;
            }
        }
        return [
            'total' => $total,
            'answered' => $answered,
            'pending' => $total - $answered,
            'flagged' => $flagged,
        ];
    }
}
