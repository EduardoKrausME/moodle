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

use core\output\named_templatable;
use mod_quiz\quiz_attempt;
use renderable;
use renderer_base;

/**
 * Display summary information about a list of attempts.
 *
 * This is used on the front page of the quiz (view.php).
 *
 * @package mod_quiz
 * @copyright 2024 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_of_attempts implements renderable, named_templatable {

    /** @var int time to consider as now. */
    protected int $timenow;

    /** @var quiz_attempt[] The list of attempts to summarise. */
    protected array $attempts = [];

    /**
     * Constructor.
     *
     * @param int $timenow time that is now.
     */
    public function __construct(int $timenow) {
        $this->timenow = $timenow;
    }

    /**
     * Add an event to the list.
     *
     * @param quiz_attempt $attemptobj
     */
    public function add_attempt(quiz_attempt $attemptobj): void {
        $this->attempts[] = $attemptobj;
    }

    /**
     * Select a safe presentation variant for one of Moodle's native attempt states.
     *
     * The class does not imply a passing grade: finished means submitted, not
     * necessarily graded or passed. Never interpolate a raw state into CSS.
     *
     * @param string $state One of the native attempt states.
     * @return string A fixed, presentation-only CSS suffix.
     */
    public static function state_variant(string $state): string {
        return match ($state) {
            quiz_attempt::IN_PROGRESS => 'inprogress',
            quiz_attempt::OVERDUE => 'overdue',
            quiz_attempt::FINISHED => 'finished',
            quiz_attempt::ABANDONED => 'abandoned',
            default => 'other',
        };
    }

    public function export_for_template(renderer_base $output): array {

        $templatecontext = [
            'hasattempts' => !empty($this->attempts),
            'attempts' => [],
        ];

        // view.php supplies attempts in newest-first order. The visual indicator
        // is based on that order, not on a second query or grade comparison.
        $multipleattempts = count($this->attempts) > 1;
        foreach ($this->attempts as $index => $attemptobj) {
            $displayoptions = $attemptobj->get_display_options(true);
            $state = $attemptobj->get_state();
            $templatecontext['attempts'][] = (object) [
                'name' => get_string('attempt', 'mod_quiz', $attemptobj->get_attempt_number()),
                'statuslabel' => quiz_attempt::state_name($state),
                'statusvariant' => self::state_variant($state),
                'islatest' => $multipleattempts && $index === 0,
                // Do not duplicate review availability or expose any grade here.
                // Both continue to be determined by the existing native objects.
                'summarydata' => attempt_summary_information::create_for_attempt(
                        $attemptobj, $displayoptions)->export_for_template($output),
                'reviewlink' => $attemptobj->get_access_manager($this->timenow)->make_review_link(
                        $attemptobj->get_attempt(), $displayoptions, $output),
            ];
        }

        return $templatecontext;
    }

    public function get_template_name(\renderer_base $renderer): string {
        // Only reason we are forced to implement this is that we want the quiz renderer
        // passed to export_for_template, not a core_renderer.
        return 'mod_quiz/list_of_attempts';
    }
}
