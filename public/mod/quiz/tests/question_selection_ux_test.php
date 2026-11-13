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
            'targetpage' => 'Page 2',
        ]);

        $this->assertStringContainsString('id="add_random_question_form"', $html);
        $this->assertStringContainsString('id="menurandomcount"', $html);
        $this->assertStringContainsString('aria-describedby="quiz-random-candidate-help"', $html);
        $this->assertStringContainsString('mod-quiz-selector-form__destination', $html);
        $this->assertStringContainsString('Page 2', $html);
        $this->assertStringContainsString(get_string('quizselectiondestination', 'quiz'), $html);
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
            'targetpage' => '',
            'questionbank' => '<div data-filtercondition="{}"></div>',
            'randomoptions' => [],
            'questioncategoryoptions' => [],
        ]);

        $this->assertStringContainsString('name="slotid"', $html);
        $this->assertStringContainsString('name="update"', $html);
        $this->assertStringNotContainsString('name="newcategory"', $html);
        $this->assertStringNotContainsString('name="randomcount"', $html);
        $this->assertStringNotContainsString('name="addrandom"', $html);
        $this->assertStringNotContainsString('mod-quiz-selector-form__destination', $html);
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

    /**
     * Question selection text must stay escaped even if the destination is unexpected.
     */
    public function test_random_destination_escapes_html(): void {
        $html = $this->template('mod_quiz/add_random_question_form', [
            'returnurl' => '/mod/quiz/edit.php?cmid=12',
            'questionbank' => '<div data-filtercondition="{}"></div>',
            'randomoptions' => [['value' => 1, 'name' => '1']],
            'questioncategoryoptions' => [],
            'targetpage' => '<script>alert(1)</script>',
        ]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * The native selected count belongs only to the ordinary question bank controls.
     */
    public function test_question_bank_bottom_controls_show_native_selection_counter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $bank = $this->getMockBuilder(custom_view::class)->disableOriginalConstructor()->getMock();
        $method = new \ReflectionMethod(custom_view::class, 'display_bottom_controls');
        ob_start();
        $method->invoke($bank, \context_system::instance());
        $html = ob_get_clean();

        $this->assertStringContainsString('data-region="quiz-selection-selected"', $html);
        $this->assertStringContainsString('data-count-template=', $html);
        $this->assertStringContainsString(get_string('quizselectionselectedcount', 'quiz', 0), $html);
        $this->assertStringContainsString('name="add"', $html);
    }

    /**
     * The service must not add new random slots after student attempts exist.
     */
    public function test_random_slot_addition_is_locked_after_attempts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $questions = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questions->create_question_category();
        $questions->create_question('truefalse', null, ['category' => $category->id]);
        $condition = ['filter' => ['category' => [
            'jointype' => \core_question\local\bank\condition::JOINTYPE_DEFAULT,
            'values' => [$category->id],
            'filteroptions' => ['includesubcategories' => false],
        ]]];
        $quizsettings = \mod_quiz\quiz_settings::create($quiz->id);
        $structure = $quizsettings->get_structure();
        $structure->add_random_questions(1, 1, $condition);
        $quizsettings->get_grade_calculator()->recompute_quiz_sumgrades();
        $quizgenerator->create_attempt($quiz->id, $student->id);

        $this->expectException(\moodle_exception::class);
        $structure->add_random_questions(1, 1, $condition);
    }

    public function test_random_add_service_rejects_zero_count(): void {
        $this->expectException(\core\exception\invalid_parameter_exception::class);
        \mod_quiz\external\add_random_questions::execute(0, 1, 0);
    }

    public function test_random_add_service_rejects_more_than_one_hundred(): void {
        $this->expectException(\core\exception\invalid_parameter_exception::class);
        \mod_quiz\external\add_random_questions::execute(0, 1, 101);
    }

    public function test_random_add_service_rejects_malformed_json(): void {
        $this->expectException(\core\exception\invalid_parameter_exception::class);
        \mod_quiz\external\add_random_questions::execute(0, 1, 1, '{invalid');
    }
}
