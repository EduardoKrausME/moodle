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

use mod_quiz\question\display_options;
use question_attempt;
use question_display_options;

/**
 * Build a read-only review context using only Moodle's effective display options.
 *
 * Never read grades, responses, feedback text, correct answers, fractions or
 * user-specific data. The question engine remains solely responsible for
 * rendering any such details according to its own display options.
 *
 * @package mod_quiz
 */
final class review_context {
    /**
     * Build a context for either the overall review or a single question.
     *
     * If a question's correctness is visible, its native state string can be
     * repeated as a summary; for hidden correctness even that string is omitted.
     * Historical steps must not be labelled with the latest question state.
     *
     * @param display_options $options Effective options from get_display_options(true).
     * @param bool $question Whether the context is for a single question.
     * @param question_attempt|null $qa Native attempt, only used for visible state.
     * @param bool $historical Whether the renderer is displaying a past step.
     * @return array Safe, template-ready context.
     */
    public static function for_review(
        display_options $options,
        bool $question = false,
        ?question_attempt $qa = null,
        bool $historical = false,
    ): array {
        $result = [
            'show' => false,
            'question' => $question,
            'heading' => get_string(
                $question ? 'reviewcontextquestiontitle' : 'reviewcontexttitle', 'quiz'),
            'items' => [],
            'hasitems' => false,
            'hasstate' => false,
        ];

        // The caller has already checked review.php/reviewquestion.php permissions.
        // Still fail closed for review settings that hide the attempt.
        if (!$options->attempt || $historical) {
            return $result;
        }

        $items = [];

        if ($options->correctness == question_display_options::VISIBLE) {
            $items[] = ['label' => get_string('reviewcontextcorrectness', 'quiz')];
            if ($question && $qa !== null && $qa->has_marks()) {
                // Native state text, never reconstruct correctness from a fraction.
                $result['hasstate'] = true;
                $result['state'] = $qa->get_state_string(true);

                // Style only permitted native correctness classes, never a
                // guessed status or a value supplied by the browser.
                $stateclass = $qa->get_state_class(true);
                if (in_array($stateclass, [
                    'correct', 'partiallycorrect', 'incorrect', 'requiresgrading',
                ], true)) {
                    $result['stateclass'] = 'mod-quiz-review-context-state--' . $stateclass;
                }
            }
        }

        if ($options->marks == question_display_options::MARK_AND_MAX) {
            $items[] = ['label' => get_string('reviewcontextmarks', 'quiz')];
        } else if ($options->marks == question_display_options::MAX_ONLY) {
            // Maximum possible marks do not imply permission to see earned marks.
            $items[] = ['label' => get_string('reviewcontextmaxmarks', 'quiz')];
        }

        if ($options->feedback == question_display_options::VISIBLE) {
            $items[] = ['label' => get_string('reviewcontextspecificfeedback', 'quiz')];
        }
        if ($options->generalfeedback == question_display_options::VISIBLE) {
            $items[] = ['label' => get_string('reviewcontextgeneralfeedback', 'quiz')];
        }
        if ($options->rightanswer == question_display_options::VISIBLE) {
            $items[] = ['label' => get_string('reviewcontextcorrectanswer', 'quiz')];
        }
        if (!$question && $options->overallfeedback == question_display_options::VISIBLE) {
            $items[] = ['label' => get_string('reviewcontextoverallfeedback', 'quiz')];
        }
        if ($question && $options->manualcomment != question_display_options::HIDDEN) {
            $items[] = ['label' => get_string('reviewcontextcomments', 'quiz')];
        }
        if ($question && $options->flags != question_display_options::HIDDEN) {
            $items[] = ['label' => get_string('reviewcontextflags', 'quiz')];
        }
        if ($question && $options->history != question_display_options::HIDDEN) {
            $items[] = ['label' => get_string('reviewcontexthistory', 'quiz')];
        }

        $result['items'] = $items;
        $result['hasitems'] = !empty($items);
        $result['show'] = $result['hasitems'];
        return $result;
    }
}
