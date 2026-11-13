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
use mod_quiz\question\bank\custom_view;

defined('MOODLE_INTERNAL') || die();

/**
 * The question selection layout must not change the native form contracts.
 *
 * @package mod_quiz
 */
#[\PHPUnit\Framework\Attributes\CoversClass(edit_renderer::class)]
final class question_selection_ux_test extends \advanced_testcase {
    /**
     * Render a quiz selection template as an editing teacher.
     *
     * @param string $template Template name.
     * @param array $data Template context.
     * @return string HTML.
     */
    private function template(string $template, array $data): string {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());
        return $PAGE->get_renderer('mod_quiz')->render_from_template($template, $data);
    }

    public function test_random_form_preserves_selection_fields_and_actions(): void {
        $html = $this->template('mod_quiz/add_random_question_form', [
            'returnurl' => '/mod/quiz/edit.php?cmid=12',
            'questionbank' => '<div id="questionscontainer"><input name="cmid" value="99"></div>',
            'randomoptions' => [['value' => 1, 'name' => '1'], ['value' => 2, 'name' => '2']],
            'questioncategoryoptions' => [],
        ]);

        $this->assertStringContainsString('id="add_random_question_form"', $html);
        $this->assertStringContainsString('id="menurandomcount"', $html);
        $this->assertStringContainsString('name="randomcount"', $html);
        $this->assertStringContainsString('name="addrandom"', $html);
        $this->assertStringContainsString('name="newcategory"', $html);
        $this->assertStringContainsString('id="questionscontainer"', $html);
        $this->assertStringContainsString('data-region="quiz-selection-status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString(get_string('quizselectioncandidatehint', 'quiz'), $html);
        $this->assertSame(1, substr_count($html, '<form '));
    }

    public function test_edit_random_filters_does_not_offer_new_category_or_count(): void {
        $html = $this->template('mod_quiz/add_random_question_form', [
            'returnurl' => '/mod/quiz/edit.php?cmid=12',
            'slotid' => 123,
            'questionbank' => '<div data-filtercondition="{}"></div>',
            'randomoptions' => [],
            'questioncategoryoptions' => [],
        ]);

        $this->assertStringContainsString('name="slotid"', $html);
        $this->assertStringContainsString('name="update"', $html);
        $this->assertStringNotContainsString('name="newcategory"', $html);
        $this->assertStringNotContainsString('name="randomcount"', $html);
        $this->assertStringNotContainsString('name="addrandom"', $html);
        $this->assertSame(1, substr_count($html, '<form '));
    }

    public function test_question_bank_header_preserves_native_switch_action(): void {
        $html = $this->template('mod_quiz/switch_bank_header', [
            'currentbank' => 'Algebra & Geometry',
        ]);
        $this->assertStringContainsString('data-action="switch-question-bank"', $html);
        $this->assertStringContainsString('id="switch-question-bank"', $html);
        $this->assertStringContainsString('mod-quiz-selector-bank', $html);
        $this->assertStringContainsString('Algebra', $html);
    }

    public function test_update_form_preserves_service_arguments_and_filter_markup(): void {
        $html = $this->template('mod_quiz/update_filter_condition_form', [
            'returnurl' => '/mod/quiz/edit.php?cmid=12',
            'cmid' => 12,
            'slotid' => 123,
            'questionbank' => '<div data-filtercondition="{&quot;filter&quot;:{}}"></div>',
        ]);
        $this->assertStringContainsString('id="update_filter_condition_form"', $html);
        $this->assertStringContainsString('data-cmid="12"', $html);
        $this->assertStringContainsString('data-slotid="123"', $html);
        $this->assertStringContainsString('data-filtercondition=', $html);
        $this->assertStringContainsString('name="update"', $html);
        $this->assertStringContainsString('name="message"', $html);
        $this->assertStringContainsString('data-region="quiz-selection-status"', $html);
    }

    public function test_bank_fragment_preserves_native_markup_without_extra_forms(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $PAGE->set_context(\context_system::instance());

        $bank = $this->getMockBuilder(custom_view::class)->disableOriginalConstructor()
            ->onlyMethods(['render'])->getMock();
        $bank->expects($this->once())->method('render')->with([], 'editq')
            ->willReturn('<form id="questionsubmit"><input name="addonpage"></form>');
        /** @var edit_renderer $renderer */
        $renderer = $PAGE->get_renderer('mod_quiz', 'edit');
        $html = $renderer->question_bank_contents($bank, []);

        $this->assertStringContainsString('class="questionbankformforpopup mod-quiz-selector-dialog', $html);
        $this->assertStringContainsString('id="questionsubmit"', $html);
        $this->assertStringContainsString('name="addonpage"', $html);
        $this->assertSame(1, substr_count($html, '<form '));
    }
}
