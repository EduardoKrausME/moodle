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
 * Sanitises the submitted selection before native assignment bulk operations.
 *
 * UI checkboxes are never an authorisation boundary. The assignment checks
 * participant enrolment, grouping and operation-specific capabilities after
 * this purely syntactic validation.
 *
 * @package mod_assign
 */
final class bulk_action_targets {
    /** Protect the server from unbounded lists of client-supplied user IDs. */
    public const MAX_TARGETS = 10000;

    /**
     * Accept a comma-separated list of positive numeric user IDs.
     *
     * Duplicate entries are processed once, retaining their first appearance.
     * Any malformed entry rejects the entire selection rather than silently
     * changing the operation's scope.
     *
     * @param string $sequence Submitted PARAM_SEQUENCE field.
     * @return int[] Stable ordered unique user IDs.
     */
    public static function normalise(string $sequence): array {
        if ($sequence === '' || !preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D', $sequence)) {
            throw new \moodle_exception('invalidbulkselection', 'assign');
        }

        $result = [];
        foreach (explode(',', $sequence) as $id) {
            $number = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($number === false) {
                throw new \moodle_exception('invalidbulkselection', 'assign');
            }
            $result[$number] = $number;
            if (count($result) > self::MAX_TARGETS) {
                throw new \moodle_exception('invalidbulkselection', 'assign');
            }
        }
        return array_values($result);
    }

    /**
     * Reject unsupported operations before any side effects.
     *
     * @param string $operation Native action key.
     * @return bool
     */
    public static function supported(string $operation): bool {
        return in_array($operation, [
            'lock', 'unlock', 'downloadselected', 'removesubmission',
            'grantextension', 'addattempt', 'reverttodraft',
            'setmarkingworkflowstate', 'setmarkingallocation',
        ], true) || str_starts_with($operation, 'plugingradingbatchoperation_');
    }
}
