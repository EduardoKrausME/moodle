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
 * Pure presentation data for the instructor's grading dashboard.
 *
 * Counting and authorisation belong to the assignment; never inspect a user's
 * submissions or resolve a permission in this class.
 *
 * @package mod_assign
 */
final class grading_dashboard {
    /**
     * Build five indicators using one unit (students or teams) and only current attempts.
     *
     * @param int $participants Eligible students or teams in the active group scope.
     * @param int $submitted Final submissions in the current attempts.
     * @param int $drafts Current draft submissions.
     * @param int $pending Submitted current attempts requiring grading.
     * @param bool $teams Whether counts represent teams.
     * @param bool $submissionsenabled Whether the assignment accepts submissions.
     * @param bool $draftsenabled Whether final confirmation is required.
     * @param bool $gradedenabled Whether the assignment has a grade.
     * @param array $urls Native, capability-checked grading page filter URLs.
     * @return array Data suitable for the dashboard Mustache template.
     */
    public static function describe(
        int $participants,
        int $submitted,
        int $drafts,
        int $pending,
        bool $teams,
        bool $submissionsenabled,
        bool $draftsenabled,
        bool $gradedenabled,
        array $urls
    ): array {
        $participants = max(0, $participants);
        $submitted = max(0, $submitted);
        $drafts = max(0, $drafts);
        $pending = min($submitted, max(0, $pending));
        // Submission attempts which have never been graded are included in pending,
        // not assumed completed merely because an older attempt has a grade.
        $graded = max(0, $submitted - $pending);
        $ratio = $participants ? min(100, (int) round(100 * $submitted / $participants)) : 0;

        $values = [
            'participants' => $participants,
            'submitted' => $submitted,
            'drafts' => $drafts,
            'pending' => $pending,
            'graded' => $graded,
        ];
        $cards = [];
        foreach ($values as $key => $value) {
            $available = $submissionsenabled || $key === 'participants';
            if ($key === 'drafts') {
                $available = $submissionsenabled && $draftsenabled;
            } else if ($key === 'pending' || $key === 'graded') {
                $available = $submissionsenabled && $gradedenabled;
            }
            // Native grading filters enumerate participants, not distinct teams.
            // Do not link a team count to a differently-sized list.
            $url = $available && (!$teams || $key === 'participants') ? ($urls[$key] ?? '') : '';
            $cards[] = [
                'key' => $key,
                'value' => $available ? $value : 0,
                'available' => $available,
                'hasurl' => $url !== '',
                'url' => $url,
            ];
        }
        return [
            'teams' => $teams,
            'submissionsenabled' => $submissionsenabled,
            'gradable' => $gradedenabled,
            'cards' => $cards,
            'participants' => $participants,
            'submitted' => $submitted,
            'percentage' => $ratio,
        ];
    }
}
