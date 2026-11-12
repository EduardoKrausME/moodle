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
 * Presentation-only attempt ordering and pairing for the assignment timeline.
 *
 * No access decisions are made here: callers must supply submissions belonging
 * to the authorised viewer and an explicit grade-visibility decision.
 *
 * @package mod_assign
 */
final class attempt_history_overview {
    /**
     * Return a chronological (latest first) and visibility-filtered history.
     *
     * @param array $submissions Existing native submission records.
     * @param array $grades Existing native grade records.
     * @param bool $showgrades Whether the native gradebook and marking workflow permit disclosure.
     * @return array An ordered list of status, timestamps and optional grade records.
     */
    public static function describe(array $submissions, array $grades, bool $showgrades): array {
        $gradesbyattempt = [];
        if ($showgrades) {
            foreach ($grades as $grade) {
                if (!is_object($grade) || !isset($grade->attemptnumber, $grade->grade) ||
                        $grade->grade === null || (float) $grade->grade === (float) ASSIGN_GRADE_NOT_SET) {
                    continue;
                }
                $gradesbyattempt[(int) $grade->attemptnumber] = $grade;
            }
        }

        $submissionsbyattempt = [];
        foreach ($submissions as $submission) {
            if (!is_object($submission) || !isset($submission->attemptnumber)) {
                continue;
            }
            $number = (int) $submission->attemptnumber;
            if ($number < 0) {
                continue;
            }
            $submissionsbyattempt[$number] = $submission;
        }

        if (!$submissionsbyattempt) {
            return [];
        }

        krsort($submissionsbyattempt, SORT_NUMERIC);
        $current = (int) array_key_first($submissionsbyattempt);
        $result = [];
        foreach ($submissionsbyattempt as $number => $submission) {
            $status = (string) ($submission->status ?? ASSIGN_SUBMISSION_STATUS_NEW);
            // The modification timestamp is not necessarily the final submission time.
            $modified = max(0, (int) ($submission->timemodified ?? 0));
            $result[] = [
                'attemptnumber' => (int) $number,
                'iscurrent' => (int) $number === $current,
                'submission' => $submission,
                'status' => $status,
                'modified' => $modified,
                'grade' => $gradesbyattempt[$number] ?? null,
            ];
        }
        return $result;
    }
}
