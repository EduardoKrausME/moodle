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
 * Describes the existing group submission state without granting any permissions.
 *
 * Counts are aggregated for eligible active participants of the current attempt.
 * Group membership and whether a learner may submit remain native assign concerns.
 *
 * @package mod_assign
 */
final class group_submission_overview {
    /**
     * Describe a group submission for the learner's landing page.
     *
     * @param int $groupcount Number of eligible groups in the configured grouping.
     * @param bool $requiregroup Whether a unique group is mandatory.
     * @param bool $requireall Whether each member must confirm the assignment.
     * @param string $teamstatus Native collective status.
     * @param int $total Eligible active team members.
     * @param int $confirmed Members with a submitted record for this attempt.
     * @param bool $myconfirmed Current learner has confirmed this attempt.
     * @param bool $locked Native locked flag, for presentation only.
     * @return array Template-safe state and aggregate counts.
     */
    public static function describe(
        int $groupcount,
        bool $requiregroup,
        bool $requireall,
        string $teamstatus,
        int $total,
        int $confirmed,
        bool $myconfirmed,
        bool $locked
    ): array {
        $blocked = $requiregroup && $groupcount !== 1;
        $total = max(0, $total);
        $confirmed = min($total, max(0, $confirmed));
        $pending = $total - $confirmed;

        if ($blocked) {
            $state = $groupcount === 0 ? 'nogroup' : 'multiplegroups';
        } else if ($teamstatus === 'submitted') {
            $state = 'submitted';
        } else if ($locked) {
            $state = 'locked';
        } else if ($teamstatus === 'reopened') {
            $state = 'reopened';
        } else if ($requireall && $myconfirmed) {
            $state = 'waiting';
        } else if ($teamstatus === 'draft') {
            $state = 'draft';
        } else {
            $state = 'notstarted';
        }

        return [
            'state' => $state,
            'blocked' => $blocked,
            'defaultgroup' => !$blocked && $groupcount !== 1,
            'requiresall' => $requireall,
            'showprogress' => !$blocked && $requireall && $total > 0,
            'noeligiblemembers' => !$blocked && $requireall && $total === 0,
            'onecanfinish' => !$blocked && !$requireall,
            'total' => $total,
            'confirmed' => $confirmed,
            'pending' => $pending,
            'haspending' => $pending > 0,
            'myconfirmed' => $requireall && $myconfirmed,
        ];
    }
}
