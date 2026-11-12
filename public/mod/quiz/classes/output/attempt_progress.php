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
     * Whether this slot has a recorded answer and is not blocked by a prerequisite.
     *
     * Caller must have checked is_real_question(), so information items are not
     * treated as an answered question. Used by both the progress widget and the
     * final summary, keeping their status classifications consistent.
     *
     * @param quiz_attempt $attemptobj Quiz attempt.
     * @param int $slot Real question slot.
     * @return bool
     */
    public static function is_slot_answered(quiz_attempt $attemptobj, int $slot): bool {
        return !$attemptobj->is_blocked_by_previous_question($slot) &&
                self::is_answered($attemptobj->get_question_attempt($slot)->get_state());
    }

    /**
     * Build a safe context for the native submission dialogue.
     *
     * @param quiz_attempt $attemptobj Authenticated current attempt.
     * @return array Counts and the native navigation method.
     */
    public static function for_submission_confirmation(quiz_attempt $attemptobj): array {
        $progress = self::summarise($attemptobj);
        $progress['navmethod'] = $attemptobj->get_navigation_method();
        return $progress;
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
            if (self::is_slot_answered($attemptobj, $slot)) {
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
