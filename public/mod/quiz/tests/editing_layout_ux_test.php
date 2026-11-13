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

namespace mod_quiz;

use mod_quiz\output\edit_renderer;

defined('MOODLE_INTERNAL') || die();

/**
 * Ensure the responsive editor remains a presentation layer over Moodle's
 * original slot, question-bank and editing controls.
 *
 * @package mod_quiz
 */
#[\PHPUnit\Framework\Attributes\CoversClass(edit_renderer::class)]
final class editing_layout_ux_test extends \advanced_testcase {
    /**
     * Render a single question slot using Moodle's edit renderer.
     *
     * @param array $changes Changes to the default Mustache context.
     * @return string Rendered HTML.
     */
    private function render_slot(array $changes = []): string {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());

        $context = array_replace([
            'slotid' => 123,
            'actionslabel' => 'Actions for question 1',
            'israndom' => false,
            'isinfo' => false,
            'canbeedited' => true,
            'checkbox' => '<input type="checkbox" id="selectquestion-123" class="select-multiple-checkbox">',
            'questionnumber' => '<span class="slotnumber">Question 1</span>',
            'questionname' => '<a class="native-question" href="/question/edit.php">Sample question</a>',
            'questionicons' => '<a data-action="delete" class="editing_delete" href="#">Delete</a>',
            'questiondependencyicon' => '<span class="question_dependency_wrapper">Dependency</span>',
            'versionselection' => true,
            'versionoption' => [
                ['version' => 0, 'selected' => true, 'versionvalue' => 'Always latest'],
                ['version' => 1, 'selected' => false, 'versionvalue' => 'v1'],
            ],
            'draftversion' => false,
            'issharedbank' => false,
            'bankurl' => '',
            'bankname' => '',
        ], $changes);

        return $PAGE->get_renderer('mod_quiz', 'edit')->render_from_template(
            'mod_quiz/question_slot', $context);
    }

    public function test_slot_preserves_native_controls_and_version_selector(): void {
        $html = $this->render_slot();
        $this->assertStringContainsString('id="mod-indent-outer-slot-123"', $html);
        $this->assertStringContainsString('mod-quiz-edit-question__content', $html);
        $this->assertStringContainsString('id="selectquestion-123"', $html);
        $this->assertStringContainsString('class="slotnumber"', $html);
        $this->assertStringContainsString('class="native-question"', $html);
        $this->assertStringContainsString('data-action="delete"', $html);
        $this->assertStringContainsString('class="editing_delete"', $html);
        $this->assertStringContainsString('id="version-123"', $html);
        $this->assertStringContainsString('data-action="mod_quiz-select_slot"', $html);
        $this->assertStringContainsString('data-slot-id="123"', $html);
        $this->assertStringContainsString('question_dependency_wrapper', $html);
        $this->assertStringContainsString('role="group"', $html);
        $this->assertStringContainsString('aria-label="Actions for question 1"', $html);
    }

    public function test_random_slot_has_distinct_layout_without_version_selector(): void {
        $html = $this->render_slot(['israndom' => true, 'versionselection' => false]);
        $this->assertStringContainsString('mod-quiz-edit-question--random', $html);
        $this->assertStringContainsString('mod-quiz-edit-question__kind', $html);
        $this->assertStringContainsString(get_string('randomquestion', 'quiz'), $html);
        $this->assertStringNotContainsString('id="version-123"', $html);
    }

    public function test_information_slot_has_no_invented_mark_or_version(): void {
        $html = $this->render_slot(['isinfo' => true, 'versionselection' => false,
            'questionicons' => '']);
        $this->assertStringContainsString('mod-quiz-edit-question--info', $html);
        $this->assertStringNotContainsString('mod-quiz-edit-question--random', $html);
        $this->assertStringNotContainsString('id="version-123"', $html);
        $this->assertStringNotContainsString('instancemaxmark', $html);
    }

    public function test_uneditable_slot_does_not_gain_dependent_question_action(): void {
        $html = $this->render_slot(['canbeedited' => false, 'questionicons' => '']);
        $this->assertStringNotContainsString('question_dependency_wrapper', $html);
        $this->assertStringContainsString('Sample question', $html);
    }

    public function test_draft_question_keeps_original_accessible_warning(): void {
        $html = $this->render_slot(['draftversion' => true]);
        $this->assertStringContainsString('alert alert-danger', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString(get_string('questiondraftwillnotwork', 'quiz'), $html);
    }

    public function test_shared_bank_keeps_authorised_link(): void {
        $html = $this->render_slot(['issharedbank' => true,
            'bankurl' => '/question/edit.php?cmid=77', 'bankname' => 'Shared bank']);
        $this->assertStringContainsString('mod-quiz-edit-question__bank', $html);
        $this->assertStringContainsString('Shared bank', $html);
        $this->assertStringContainsString('href="/question/edit.php?cmid=77"', $html);
    }

    public function test_unavailable_shared_bank_link_is_not_created(): void {
        $html = $this->render_slot(['issharedbank' => true,
            'bankurl' => '', 'bankname' => 'Shared bank']);
        $this->assertStringContainsString('Shared bank', $html);
        $this->assertStringNotContainsString('href="/question/edit.php?cmid=', $html);
    }

    public function test_question_row_keeps_slot_id_and_dragdrop_class(): void {
        $structure = $this->getMockBuilder(structure::class)->disableOriginalConstructor()
            ->onlyMethods(['can_be_edited', 'get_question_type_for_slot',
                'get_slot_id_for_slot', 'can_finish_during_the_attempt'])->getMock();
        $structure->method('can_be_edited')->willReturn(false);
        $structure->method('get_question_type_for_slot')->willReturn('multichoice');
        $structure->method('get_slot_id_for_slot')->willReturn(123);
        $structure->method('can_finish_during_the_attempt')->willReturn(true);

        $renderer = $this->getMockBuilder(edit_renderer::class)->disableOriginalConstructor()
            ->onlyMethods(['page_row', 'question'])->getMock();
        $renderer->method('page_row')->willReturn('<li id="page-1" class="page">Page 1</li>');
        $renderer->method('question')->willReturn('<div class="stub-question">Sample</div>');

        $html = $renderer->question_row($structure, 1, null, [],
            new \moodle_url('/mod/quiz/edit.php'));
        $this->assertStringContainsString('id="page-1"', $html);
        $this->assertStringContainsString('id="slot-123"', $html);
        $this->assertStringContainsString('qtype_multichoice', $html);
        $this->assertStringContainsString('mod-quiz-edit-slot', $html);
        $this->assertStringContainsString('data-canfinish=', $html);
        $this->assertStringContainsString('stub-question', $html);
    }
}
