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

use mod_quiz\output\list_of_attempts;

defined('MOODLE_INTERNAL') || die();

/**
 * Presentation-only history metadata and native attempt-card rendering.
 *
 * @package mod_quiz
 */
#[\PHPUnit\Framework\Attributes\CoversClass(list_of_attempts::class)]
final class list_of_attempts_ux_test extends \advanced_testcase {
    public function test_known_states_are_mapped_to_fixed_classes(): void {
        $this->assertSame('finished', list_of_attempts::state_variant(quiz_attempt::FINISHED));
        $this->assertSame('inprogress', list_of_attempts::state_variant(quiz_attempt::IN_PROGRESS));
        $this->assertSame('overdue', list_of_attempts::state_variant(quiz_attempt::OVERDUE));
        $this->assertSame('abandoned', list_of_attempts::state_variant(quiz_attempt::ABANDONED));
    }

    public function test_unrecognised_state_does_not_become_a_css_class(): void {
        $this->assertSame('other', list_of_attempts::state_variant('<script>alert(1)</script>'));
        $this->assertSame('other', list_of_attempts::state_variant('finished injected-class'));
        $this->assertSame('other', list_of_attempts::state_variant(''));
    }

    /**
     * The template must preserve the original native summary and review HTML.
     *
     * This tests rendering without generating a quiz, manipulating grades or
     * granting review permissions. Only the access manager supplies links in
     * production, and the partial supplies all permitted result fields.
     */
    public function test_card_keeps_native_summary_table_and_review_link(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());

        $context = [
            'hasattempts' => true,
            'attempts' => [
                [
                    'name' => 'Attempt 2',
                    'statuslabel' => 'Finished',
                    'statusvariant' => 'finished',
                    'islatest' => true,
                    'summarydata' => [
                        'hasitems' => true,
                        'caption' => 'Summary of attempt 2',
                        'items' => [
                            ['title' => 'Status', 'content' => 'Finished'],
                            ['title' => 'Started', 'content' => 'Thursday, 12 November'],
                            ['title' => 'Grade', 'content' => '8.50/10.00'],
                        ],
                    ],
                    'reviewlink' => '<a class="native-review" href="/mod/quiz/review.php?attempt=2">Review</a>',
                ],
            ],
        ];

        $html = $PAGE->get_renderer('mod_quiz')->render_from_template('mod_quiz/list_of_attempts', $context);
        $this->assertStringContainsString('mod-quiz-attempt-history__card--finished', $html);
        $this->assertStringContainsString('Most recent attempt', $html);
        $this->assertStringContainsString('quizreviewsummary', $html);
        $this->assertStringContainsString('scope="row"', $html);
        $this->assertStringContainsString('8.50/10.00', $html);
        $this->assertStringContainsString('class="native-review"', $html);
        $this->assertStringContainsString('href="/mod/quiz/review.php?attempt=2"', $html);
    }

    public function test_card_never_fabricates_hidden_grade_or_review_link(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());

        $context = [
            'hasattempts' => true,
            'attempts' => [
                [
                    'name' => 'Attempt 1',
                    'statuslabel' => 'Finished',
                    'statusvariant' => 'finished',
                    'islatest' => false,
                    'summarydata' => [
                        'hasitems' => true,
                        'caption' => 'Summary of attempt 1',
                        'items' => [['title' => 'Status', 'content' => 'Finished']],
                    ],
                    'reviewlink' => '<span class="noreviewmessage">Review not permitted</span>',
                ],
            ],
        ];

        $html = $PAGE->get_renderer('mod_quiz')->render_from_template('mod_quiz/list_of_attempts', $context);
        $this->assertStringContainsString('Review not permitted', $html);
        $this->assertStringNotContainsString('Most recent attempt', $html);
        $this->assertStringNotContainsString('8.50/10.00', $html);
        $this->assertStringNotContainsString('href="/mod/quiz/review.php', $html);
    }

    public function test_status_output_is_escaped_in_template(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());

        $context = [
            'hasattempts' => true,
            'attempts' => [
                [
                    'name' => 'Attempt 1',
                    'statuslabel' => '<script>alert(1)</script>',
                    'statusvariant' => 'other',
                    'islatest' => false,
                    'summarydata' => ['hasitems' => false, 'items' => []],
                    'reviewlink' => '',
                ],
            ],
        ];
        $html = $PAGE->get_renderer('mod_quiz')->render_from_template('mod_quiz/list_of_attempts', $context);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_no_attempts_renders_no_cards(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());

        $html = $PAGE->get_renderer('mod_quiz')->render_from_template('mod_quiz/list_of_attempts', [
            'hasattempts' => false,
            'attempts' => [],
        ]);
        $this->assertStringNotContainsString('mod-quiz-attempt-history__card', $html);
        $this->assertStringNotContainsString('mod-quiz-attempt-history-heading', $html);
    }
}
