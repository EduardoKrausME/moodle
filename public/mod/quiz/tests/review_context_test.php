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

use mod_quiz\output\review_context;
use mod_quiz\question\display_options;
use question_attempt;
use question_display_options;

defined('MOODLE_INTERNAL') || die();

/**
 * The contextual review never introduces information hidden by review settings.
 *
 * @package mod_quiz
 */
#[\PHPUnit\Framework\Attributes\CoversClass(review_context::class)]
final class review_context_test extends \advanced_testcase {
    /**
     * Start with a review where no optional information may be shown.
     *
     * @return display_options
     */
    private function hidden_options(): display_options {
        $options = new display_options();
        $options->attempt = true;
        $options->correctness = question_display_options::HIDDEN;
        $options->marks = question_display_options::HIDDEN;
        $options->feedback = question_display_options::HIDDEN;
        $options->generalfeedback = question_display_options::HIDDEN;
        $options->rightanswer = question_display_options::HIDDEN;
        $options->overallfeedback = question_display_options::HIDDEN;
        $options->manualcomment = question_display_options::HIDDEN;
        $options->history = question_display_options::HIDDEN;
        $options->flags = question_display_options::HIDDEN;
        return $options;
    }

    /**
     * Get the visible item labels without relying on their presentation.
     *
     * @param array $context The template context.
     * @return string[]
     */
    private function labels(array $context): array {
        return array_column($context['items'], 'label');
    }

    public function test_all_hidden_returns_no_context_and_no_correctness(): void {
        $options = $this->hidden_options();
        $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_state_string'])->getMock();
        $qa->expects($this->never())->method('get_state_string');
        $context = review_context::for_review($options, true, $qa);
        $this->assertFalse($context['show']);
        $this->assertFalse($context['hasstate']);
        $this->assertEmpty($context['items']);
        $this->assertArrayNotHasKey('state', $context);
    }

    public function test_visible_correctness_uses_native_state_only(): void {
        $options = $this->hidden_options();
        $options->correctness = question_display_options::VISIBLE;
        $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_state_string', 'get_state_class', 'has_marks'])->getMock();
        $qa->method('has_marks')->willReturn(true);
        $qa->expects($this->once())->method('get_state_string')->with(true)
            ->willReturn('Partially correct');
        $qa->expects($this->once())->method('get_state_class')->with(true)
            ->willReturn('partiallycorrect');
        $context = review_context::for_review($options, true, $qa);
        $this->assertTrue($context['show']);
        $this->assertTrue($context['hasstate']);
        $this->assertSame('Partially correct', $context['state']);
        $this->assertSame('mod-quiz-review-context-state--partiallycorrect', $context['stateclass']);
        $this->assertSame([get_string('reviewcontextcorrectness', 'quiz')], $this->labels($context));
    }


    public function test_zero_mark_question_has_no_correctness_state(): void {
        $options = $this->hidden_options();
        $options->correctness = question_display_options::VISIBLE;
        $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_state_string', 'has_marks'])->getMock();
        $qa->method('has_marks')->willReturn(false);
        $qa->expects($this->never())->method('get_state_string');
        $context = review_context::for_review($options, true, $qa);
        $this->assertTrue($context['show']);
        $this->assertFalse($context['hasstate']);
        $this->assertArrayNotHasKey('state', $context);
    }

    public function test_hidden_correctness_does_not_read_the_native_state(): void {
        $options = $this->hidden_options();
        $options->marks = question_display_options::MARK_AND_MAX;
        $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_state_string'])->getMock();
        $qa->expects($this->never())->method('get_state_string');
        $context = review_context::for_review($options, true, $qa);
        $this->assertTrue($context['show']);
        $this->assertArrayNotHasKey('state', $context);
        $this->assertSame([get_string('reviewcontextmarks', 'quiz')], $this->labels($context));
    }

    public function test_maximum_marks_only_does_not_advertise_earned_marks(): void {
        $options = $this->hidden_options();
        $options->marks = question_display_options::MAX_ONLY;
        $context = review_context::for_review($options, true);
        $this->assertSame([get_string('reviewcontextmaxmarks', 'quiz')], $this->labels($context));
        $this->assertArrayNotHasKey('state', $context);
    }

    public function test_feedback_and_right_answer_are_independent(): void {
        $options = $this->hidden_options();
        $options->feedback = question_display_options::VISIBLE;
        $this->assertSame([get_string('reviewcontextspecificfeedback', 'quiz')],
            $this->labels(review_context::for_review($options, true)));
        $options->generalfeedback = question_display_options::VISIBLE;
        $this->assertCount(2, $this->labels(review_context::for_review($options, true)));
        $options->rightanswer = question_display_options::VISIBLE;
        $this->assertCount(3, $this->labels(review_context::for_review($options, true)));
    }

    public function test_overall_feedback_only_appears_in_attempt_overview(): void {
        $options = $this->hidden_options();
        $options->overallfeedback = question_display_options::VISIBLE;
        $this->assertTrue(review_context::for_review($options)['show']);
        $this->assertFalse(review_context::for_review($options, true)['show']);
    }

    public function test_flags_and_history_do_not_expose_correctness(): void {
        $options = $this->hidden_options();
        $options->flags = question_display_options::EDITABLE;
        $options->history = question_display_options::VISIBLE;
        $context = review_context::for_review($options, true);
        $this->assertSame([
            get_string('reviewcontextflags', 'quiz'),
            get_string('reviewcontexthistory', 'quiz'),
        ], $this->labels($context));
        $this->assertArrayNotHasKey('state', $context);
    }

    public function test_hidden_attempt_fails_closed(): void {
        $options = $this->hidden_options();
        $options->attempt = false;
        $options->correctness = question_display_options::VISIBLE;
        $options->marks = question_display_options::MARK_AND_MAX;
        $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_state_string'])->getMock();
        $qa->expects($this->never())->method('get_state_string');
        $context = review_context::for_review($options, true, $qa);
        $this->assertFalse($context['show']);
        $this->assertEmpty($context['items']);
        $this->assertArrayNotHasKey('state', $context);
    }

    public function test_historical_step_never_inherits_current_state(): void {
        $options = $this->hidden_options();
        $options->correctness = question_display_options::VISIBLE;
        $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_state_string'])->getMock();
        $qa->expects($this->never())->method('get_state_string');
        $context = review_context::for_review($options, true, $qa, true);
        $this->assertFalse($context['show']);
        $this->assertArrayNotHasKey('state', $context);
    }

    public function test_review_phase_bitmasks_are_obeyed(): void {
        $quiz = (object) [
            'reviewattempt' => display_options::IMMEDIATELY_AFTER | display_options::AFTER_CLOSE,
            'reviewcorrectness' => display_options::IMMEDIATELY_AFTER,
            'reviewmaxmarks' => display_options::IMMEDIATELY_AFTER,
            'reviewmarks' => display_options::IMMEDIATELY_AFTER,
            'reviewspecificfeedback' => display_options::AFTER_CLOSE,
            'reviewgeneralfeedback' => 0,
            'reviewrightanswer' => 0,
            'reviewoverallfeedback' => 0,
            'questiondecimalpoints' => -1,
            'decimalpoints' => 2,
        ];
        $immediate = display_options::make_from_quiz($quiz, display_options::IMMEDIATELY_AFTER);
        $later = display_options::make_from_quiz($quiz, display_options::LATER_WHILE_OPEN);
        $closed = display_options::make_from_quiz($quiz, display_options::AFTER_CLOSE);

        $this->assertContains(get_string('reviewcontextcorrectness', 'quiz'),
            $this->labels(review_context::for_review($immediate)));
        $this->assertFalse(review_context::for_review($later)['show']);
        $this->assertSame([get_string('reviewcontextspecificfeedback', 'quiz')],
            $this->labels(review_context::for_review($closed)));
    }

    public function test_native_status_classes_are_allowlisted(): void {
        $options = $this->hidden_options();
        $options->correctness = question_display_options::VISIBLE;
        foreach (['correct', 'partiallycorrect', 'incorrect', 'requiresgrading'] as $state) {
            $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
                ->onlyMethods(['has_marks', 'get_state_string', 'get_state_class'])->getMock();
            $qa->method('has_marks')->willReturn(true);
            $qa->method('get_state_string')->willReturn('Native status');
            $qa->method('get_state_class')->willReturn($state);
            $context = review_context::for_review($options, true, $qa);
            $this->assertSame('mod-quiz-review-context-state--' . $state, $context['stateclass']);
            $this->assertSame('Native status', $context['state']);
        }
    }

    public function test_unexpected_native_status_class_cannot_become_css_or_html(): void {
        $options = $this->hidden_options();
        $options->correctness = question_display_options::VISIBLE;
        $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['has_marks', 'get_state_string', 'get_state_class'])->getMock();
        $qa->method('has_marks')->willReturn(true);
        $qa->method('get_state_string')->willReturn('Native status');
        $qa->method('get_state_class')->willReturn('correct" onmouseover="alert(1)');
        $context = review_context::for_review($options, true, $qa);
        $this->assertTrue($context['hasstate']);
        $this->assertArrayNotHasKey('stateclass', $context);
        $this->assertSame('Native status', $context['state']);
    }

    public function test_hidden_correctness_does_not_consult_status_even_if_feedback_is_visible(): void {
        $options = $this->hidden_options();
        $options->feedback = question_display_options::VISIBLE;
        $options->marks = question_display_options::MAX_ONLY;
        $qa = $this->getMockBuilder(question_attempt::class)->disableOriginalConstructor()
            ->onlyMethods(['get_state_string', 'get_state_class'])->getMock();
        $qa->expects($this->never())->method('get_state_string');
        $qa->expects($this->never())->method('get_state_class');
        $context = review_context::for_review($options, true, $qa);
        $this->assertTrue($context['hasitems']);
        $this->assertFalse($context['hasstate']);
        $this->assertArrayNotHasKey('state', $context);
        $this->assertArrayNotHasKey('stateclass', $context);
        $this->assertNotContains(get_string('reviewcontextcorrectness', 'quiz'), $this->labels($context));
    }

    public function test_review_during_attempt_does_not_advertise_future_results(): void {
        $quiz = (object) [
            'reviewattempt' => display_options::IMMEDIATELY_AFTER | display_options::AFTER_CLOSE,
            'reviewcorrectness' => display_options::AFTER_CLOSE,
            'reviewmaxmarks' => display_options::IMMEDIATELY_AFTER,
            'reviewmarks' => display_options::IMMEDIATELY_AFTER,
            'reviewspecificfeedback' => display_options::AFTER_CLOSE,
            'reviewgeneralfeedback' => 0,
            'reviewrightanswer' => display_options::AFTER_CLOSE,
            'reviewoverallfeedback' => 0,
            'questiondecimalpoints' => -1,
            'decimalpoints' => 2,
        ];
        foreach ([display_options::DURING, display_options::LATER_WHILE_OPEN] as $phase) {
            $context = review_context::for_review(display_options::make_from_quiz($quiz, $phase));
            $this->assertFalse($context['show']);
            $this->assertFalse($context['hasitems']);
            $this->assertEmpty($context['items']);
        }
        $immediate = review_context::for_review(display_options::make_from_quiz(
            $quiz, display_options::IMMEDIATELY_AFTER));
        $this->assertSame([get_string('reviewcontextmarks', 'quiz')], $this->labels($immediate));
        $afterclose = review_context::for_review(display_options::make_from_quiz(
            $quiz, display_options::AFTER_CLOSE));
        $this->assertSame([
            get_string('reviewcontextcorrectness', 'quiz'),
            get_string('reviewcontextspecificfeedback', 'quiz'),
            get_string('reviewcontextcorrectanswer', 'quiz'),
        ], $this->labels($afterclose));
    }
}
