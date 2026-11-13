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
 * Presentation-only assessment state for the existing Moodle grading form.
 *
 * This class never reads names, returns individual marks, calculates the agreed
 * grade, or changes the native grading/workflow/advanced-grading contracts.
 *
 * @package mod_assign
 */
final class grading_assessment_view {
    /**
     * Decide the applicable form presentation, not the grading method's rules.
     *
     * @param string|null $method Active and available advanced-grading method.
     * @param bool $ismarker Whether the user is recording an individual mark.
     * @param bool $multimarking Whether native multiple marking is in use.
     * @return string One of: rubric, guide, advanced, marker, multiple, simple.
     */
    public static function mode(?string $method, bool $ismarker, bool $multimarking): string {
        if ($ismarker) {
            return 'marker';
        }
        if ($method !== null && $method !== '') {
            return in_array($method, ['rubric', 'guide'], true) ? $method : 'advanced';
        }
        return $multimarking ? 'multiple' : 'simple';
    }

    /**
     * Prepare an anonymous status-only overview from existing allocations.
     *
     * The native marker rules remain authoritative: only enabled optional
     * markers contribute to the expected number of marks, and a mark counts as
     * completed only with the workflow state used by mark_is_marked().
     *
     * @param array $allocations Records keyed by native marker position.
     * @param array $marks Records keyed by allocated marker user ID.
     * @param int $required Required positions for the assignment.
     * @param int $optional Optional positions for the assignment.
     * @param string $completedstate Native completed-mark workflow state.
     * @return array{expected: int, completed: int, slots: array}
     */
    public static function marker_progress(
        array $allocations,
        array $marks,
        int $required,
        int $optional,
        string $completedstate
    ): array {
        $required = max(0, $required);
        $optional = max(0, $optional);
        $expected = $required;
        $completed = 0;
        $slots = [];

        for ($position = 1; $position <= $required + $optional; $position++) {
            $isoptional = $position > $required;
            $allocation = $allocations[$position] ?? null;
            $enabled = !$isoptional || (!empty($allocation) && !empty($allocation->enabled));
            $assigned = $allocation !== null && !empty($allocation->marker);
            if ($isoptional && $enabled) {
                $expected++;
            }

            $marked = false;
            if ($assigned && $enabled) {
                $record = $marks[$allocation->marker] ?? null;
                $marked = $record !== null &&
                    isset($record->mark) && $record->mark >= 0 &&
                    isset($record->workflowstate) && $record->workflowstate === $completedstate;
            }
            if ($marked) {
                $completed++;
            }
            $slots[] = [
                'position' => $position,
                'optional' => $isoptional,
                'enabled' => $enabled,
                'assigned' => $assigned,
                'completed' => $marked,
            ];
        }

        return ['expected' => $expected, 'completed' => $completed, 'slots' => $slots];
    }
}
