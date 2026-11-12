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
 * Presentation-only availability calculations for a single assignment viewer.
 *
 * Never use this class to decide whether a submission is accepted. The native
 * submission, enrolment, gradebook, group and override checks remain authoritative.
 *
 * @package mod_assign
 */
final class availability {
    /**
     * Describe the effective dates already resolved for the viewer.
     *
     * @param int $now Current server timestamp.
     * @param int $opens The effective opening date or zero.
     * @param int $due The due date after user/group overrides or zero.
     * @param int $cutoff The cut-off date after overrides or zero.
     * @param int $extension Individual extension deadline or zero.
     * @param bool $submissionsopen The native submissions_open() decision.
     * @param bool $submitted Whether the attempt has been submitted.
     * @param bool $locked Whether the attempt is locked.
     * @param bool $submissiontypes Whether submission plugins are available.
     * @return array Effective timestamps and presentation state.
     */
    public static function describe(
        int $now,
        int $opens,
        int $due,
        int $cutoff,
        int $extension,
        bool $submissionsopen,
        bool $submitted,
        bool $locked,
        bool $submissiontypes
    ): array {
        // Match the native time remaining calculation: an extension never reduces the original due date.
        $effectivedue = $due > 0 ? max($due, $extension) : 0;
        // Native submissions_open() only extends a configured cut-off; it never creates one.
        $effectivecutoff = $cutoff > 0 ? max($cutoff, $extension) : 0;
        $islate = $effectivedue > 0 && $now > $effectivedue;
        $neardue = $effectivedue > 0 && $now <= $effectivedue && $effectivedue - $now <= DAYSECS;

        if ($submitted) {
            $state = 'submitted';
        } else if ($locked) {
            $state = 'locked';
        } else if (!$submissiontypes) {
            $state = 'unavailable';
        } else if ($opens > 0 && $now < $opens) {
            $state = 'upcoming';
        } else if ($effectivecutoff > 0 && $now > $effectivecutoff) {
            $state = 'closed';
        } else if (!$submissionsopen) {
            $state = 'unavailable';
        } else if ($islate) {
            $state = 'late';
        } else if ($neardue) {
            $state = 'duesoon';
        } else {
            $state = 'open';
        }

        return [
            'state' => $state,
            'opens' => $opens,
            'due' => $due,
            'effectivedue' => $effectivedue,
            'cutoff' => $cutoff,
            'effectivecutoff' => $effectivecutoff,
            'extension' => $extension,
            'hasextension' => $extension > 0 && $due > 0 && $extension > $due,
            'late' => $islate,
        ];
    }
}
