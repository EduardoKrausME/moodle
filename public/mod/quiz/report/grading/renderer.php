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

/**
 * Defines the renderer for the quiz_grading module.
 *
 * @package   quiz_grading
 * @copyright 2018 Huong Nguyen <huongnv13@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The renderer for the quiz_grading module.
 *
 * @copyright  2018 Huong Nguyen <huongnv13@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_grading_renderer extends plugin_renderer_base {

    /**
     * Render no question notification.
     *
     * @param stdClass $quiz The quiz settings.
     * @param stdClass $cm The course-module for this quiz.
     * @param stdClass $context The quiz context.
     * @return string The HTML for the no questions message.
     */
    public function render_quiz_no_question_notification($quiz, $cm, $context) {
        return quiz_no_questions_message($quiz, $cm, $context);
    }

    /**
     * Render no question need to grade notification.
     *
     * @throws coding_exception
     */
    public function render_quiz_no_grade_question_notification() {
        return $this->notification(get_string('nothingfound', 'quiz_grading'), 'info', false);
    }

    /**
     * Render index display.
     *
     * @param string $linktext The text of the link.
     * @param moodle_url $listquestionurl Url of the page that list all questions.
     * @return string The HTML for the display heading.
     * @throws coding_exception
     */
    public function render_display_index_heading($linktext, $listquestionurl) {
        $output = '';

        $output .= $this->heading(get_string('questionsthatneedgrading', 'quiz_grading'), 3);
        $output .= html_writer::tag('p', html_writer::link($listquestionurl, $linktext), ['class' => 'toggleincludeauto']);

        return $output;
    }

    /**
     * Render questions list table.
     *
     * @param bool $includeauto True to show automatically graded questions.
     * @param array $data List of questions.
     * @param array $header List of table headers.
     * @return string The HTML for the question table.
     * @throws coding_exception
     */
    public function render_questions_table($includeauto, $data, $header) {
        if (empty($data)) {
            return $this->render_quiz_no_grade_question_notification();
        }
        $output = '';

        $table = new html_table();
        $table->class = 'table generaltable mod-quiz-manual-grading-table';
        $table->id = 'questionstograde';
        $table->head = $header;
        $table->data = $data;

        $output .= html_writer::div(html_writer::table($table), 'table-responsive mod-quiz-manual-grading-table-wrap');

        return $output;
    }

    /**
     * Render grade link for question.
     *
     * @param stdClass $counts
     * @param string $type Type of grade.
     * @param string $string Lang string.
     * @param string $component Lang string component.
     * @param moodle_url $gradequestionurl Url to grade question.
     * @return string The HTML for the question grade link.
     * @throws coding_exception
     */
    public function render_grade_link($counts, $type, $string, $component, $gradequestionurl) {
        $output = '';
        if ($counts->$type > 0) {
            $output .= ' ' . html_writer::link(
                            $gradequestionurl,
                            get_string($string, $component),
                            ['class' => 'gradetheselink']);
        }
        return $output;
    }

    /**
     * Render grading page.
     *
     * @param stdClass $questioninfo Information of a question.
     * @param moodle_url $listquestionsurl Url of the page that list all questions.
     * @param quiz_grading_settings_form $filterform Question filter form.
     * @param stdClass $paginginfo Pagination information.
     * @param stdClass $pagingbar Pagination bar information.
     * @param moodle_url $formaction Form submit url.
     * @param array $hiddeninputs List of hidden input fields.
     * @param string $gradequestioncontent HTML string of question content.
     * @return string The HTML for the grading interface.
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function render_grading_interface($questioninfo, $listquestionsurl, $filterform, $paginginfo, $pagingbar, $formaction,
            $hiddeninputs, $gradequestioncontent) {
        $output = '';

        $output .= question_engine::initialise_js();

        $output .= $this->heading(get_string('gradingquestionx', 'quiz_grading', $questioninfo), 3);

        $output .= html_writer::tag('p', html_writer::link($listquestionsurl,
                get_string('backtothelistofquestions', 'quiz_grading')),
                ['class' => 'mdl-align']);

        $output .= $filterform->render();

        $output .= html_writer::tag('p', get_string('gradingbatchhelp', 'quiz_grading'),
                ['class' => 'mod-quiz-manual-grading-help']);

        $output .= $this->heading(get_string('gradingattemptsxtoyofz', 'quiz_grading', $paginginfo), 3);

        $output .= $this->render_paging_bar($pagingbar);

        $output .= html_writer::start_tag('form', [
                'method' => 'post',
                'action' => $formaction,
                'class' => 'mform',
                'id' => 'manualgradingform',
                'data-saving-label' => get_string('gradingbatchsaving', 'quiz_grading'),
        ]);
        $output .= html_writer::start_tag('div');
        $output .= html_writer::input_hidden_params(new moodle_url('', $hiddeninputs));

        // This is local, unsaved editing progress, never a count of grades persisted to the database.
        $totalinbatch = max(0, $paginginfo->to - $paginginfo->from + 1);
        $progress = (object) ['edited' => 0, 'total' => $totalinbatch];
        $progressformat = (object) ['edited' => '%EDITED%', 'total' => '%TOTAL%'];
        $output .= html_writer::div(get_string('gradingbatchmodified', 'quiz_grading', $progress),
                'mod-quiz-manual-grading-progress', [
                    'data-region' => 'manual-grading-progress',
                    'data-progress-template' => get_string('gradingbatchmodified', 'quiz_grading', $progressformat),
                    'data-total' => $totalinbatch,
                    'role' => 'status',
                    'aria-live' => 'polite',
                    'aria-atomic' => 'true',
                ]);

        $output .= $gradequestioncontent;

        $output .= html_writer::tag('div', html_writer::empty_tag('input', [
                'type' => 'submit',
                'class' => 'btn btn-primary',
                'value' => get_string('saveandnext', 'quiz_grading')
        ]), ['class' => 'mdl-align mod-quiz-manual-grading-actions']);
        $output .= html_writer::end_tag('div') . html_writer::end_tag('form');

        $output .= $this->render_paging_bar($pagingbar);

        // Add the form change checker.
        $this->page->requires->js_call_amd('core_form/changechecker', 'watchFormById', ['manualgradingform']);
        $this->page->requires->js_call_amd('mod_quiz/manual_grading_navigation', 'init');

        return $output;
    }

    /**
     * Render grade question content.
     *
     * Keep the original public method signature for third-party renderer overrides.
     *
     * @param question_usage_by_activity $questionusage The question usage that needs grading.
     * @param int $slot Question slot in the usage.
     * @param question_display_options $displayoptions Native display options.
     * @param int $questionnumber Number of the question.
     * @param string $heading Heading permitted by the identity capabilities.
     * @return string Native question markup.
     */
    public function render_grade_question($questionusage, $slot, $displayoptions, $questionnumber, $heading) {
        $output = '';
        if ($heading) {
            $output .= $this->heading($heading, 4);
        }
        $output .= $questionusage->render_question($slot, $displayoptions, $questionnumber);
        return $output;
    }

    /**
     * Wrap rendered question markup in a local navigation region.
     *
     * Separate from render_grade_question to preserve its override contract.
     * No student identity or attempt id is included in the additional markup.
     *
     * @param string $questionhtml Unmodified, already rendered native question HTML.
     * @param int $batchnumber Response position in the current batch.
     * @param int $batchsize Number of responses in the batch.
     * @return string Accessible response card with native question content.
     */
    public function render_grading_response_card(string $questionhtml, int $batchnumber, int $batchsize): string {
        $label = get_string('gradingbatchresponse', 'quiz_grading', $batchnumber);
        $output = html_writer::start_tag('section', [
            'id' => 'mod-quiz-grading-response-' . $batchnumber,
            'class' => 'mod-quiz-manual-grading-card',
            'data-region' => 'manual-grading-card',
            'role' => 'region',
            'aria-label' => $label,
        ]);
        $output .= html_writer::tag('p', html_writer::tag('strong', $label),
                ['class' => 'mod-quiz-manual-grading-card__label']);

        $output .= $questionhtml;
        $output .= html_writer::tag('p', get_string('gradingbatchunchanged', 'quiz_grading'), [
            'class' => 'mod-quiz-manual-grading-card__status',
            'data-region' => 'manual-grading-card-status',
            'data-clean-message' => get_string('gradingbatchunchanged', 'quiz_grading'),
            'data-dirty-message' => get_string('gradingbatchunsaved', 'quiz_grading'),
            'aria-live' => 'polite',
        ]);
        $navigation = [];
        if ($batchnumber > 1) {
            $navigation[] = html_writer::link('#mod-quiz-grading-response-' . ($batchnumber - 1),
                    get_string('gradingbatchprevious', 'quiz_grading'));
        }
        if ($batchnumber < $batchsize) {
            $navigation[] = html_writer::link('#mod-quiz-grading-response-' . ($batchnumber + 1),
                    get_string('gradingbatchnext', 'quiz_grading'));
        }
        if ($navigation) {
            $output .= html_writer::tag('nav', implode(' ', $navigation), [
                'class' => 'mod-quiz-manual-grading-card__navigation',
                'aria-label' => get_string('gradingbatchnavigation', 'quiz_grading'),
            ]);
        }
        $output .= html_writer::end_tag('section');
        return $output;
    }

    /**
     * Render paging bar.
     *
     * @param object $pagingbar Pagination bar information.
     * @return string The HTML for the question display.
     */
    public function render_paging_bar(object $pagingbar): string {
        if ($pagingbar->count > $pagingbar->pagesize && $pagingbar->order != 'random') {
            return $this->paging_bar($pagingbar->count, $pagingbar->page, $pagingbar->pagesize, $pagingbar->pagingurl);
        }
        return '';
    }
}
