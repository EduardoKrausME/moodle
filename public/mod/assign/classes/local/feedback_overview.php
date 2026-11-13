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

declare(strict_types=1);

namespace mod_assign\local;

/**
 * Pure presentation model for feedback already authorised by the assignment.
 *
 * This class must never fetch grades, inspect permissions or query the gradebook.
 * Its caller supplies only values already filtered by native Moodle access rules.
 *
 * @package mod_assign
 */
final class feedback_overview {
    /**
     * Describe content without altering HTML emitted by native feedback renderers.
     *
     * @param string|null $gradehtml Visible grade, already formatted by the gradebook.
     * @param int $gradeddate Timestamp of the visible grade, if any.
     * @param array $plugins Rendered visible feedback plugin summaries.
     * @param string $criteriahtml Native advanced-grading controller output.
     * @param string $graderhtml Native and capability-filtered grader identities.
     * @return array Mustache context containing no inaccessible data.
     */
    public static function describe(
        ?string $gradehtml,
        int $gradeddate,
        array $plugins,
        string $criteriahtml,
        string $graderhtml
    ): array {
        // The string "0" is a valid visible grade.
        $hasgrade = $gradehtml !== null && trim($gradehtml) !== '';
        $hasplugins = $plugins !== [];
        $hascriteria = trim($criteriahtml) !== '';
        return [
            'hascontent' => $hasgrade || $hasplugins || $hascriteria,
            'hasgrade' => $hasgrade,
            'gradehtml' => $hasgrade ? $gradehtml : '',
            'hasgradeddate' => $hasgrade && $gradeddate > 0,
            'hasgrader' => $hasgrade && $graderhtml !== '',
            'graderhtml' => $hasgrade ? $graderhtml : '',
            'hasplugins' => $hasplugins,
            'plugins' => $plugins,
            'hascriteria' => $hascriteria,
            'criteriahtml' => $hascriteria ? $criteriahtml : '',
        ];
    }
}
