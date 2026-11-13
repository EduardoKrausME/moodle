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
 * Whitelisted navigation state for the native assignment grading controls.
 *
 * Callers must validate values and permissions before providing them here.
 * The class does not execute SQL, load user records or change user preferences.
 *
 * @package mod_assign
 */
final class grading_filter_state {
    /** @var string[] Filters carried across native navigation and query forms. */
    private const NAVIGATION = [
        'group', 'userid', 'search', 'status', 'tifirst', 'tilast',
        'workflowfilter', 'markingallocationfilter', 'suspendedparticipantsfilter',
    ];

    /** @var string[] Order of removable filter chips. */
    private const CHIP_ORDER = [
        'user', 'group', 'status', 'tifirst', 'tilast',
        'workflowfilter', 'markingallocationfilter', 'suspendedparticipantsfilter',
    ];

    /**
     * Prepare parameters for a grading URL, omitting empty/unsupported criteria.
     *
     * @param int $cmid Course-module ID.
     * @param array $filters Already validated active filter values.
     * @return array Whitelisted URL parameters.
     */
    public static function parameters(int $cmid, array $filters): array {
        $params = ['id' => $cmid, 'action' => 'grading'];

        foreach (self::NAVIGATION as $key) {
            if (!array_key_exists($key, $filters) || $filters[$key] === null || $filters[$key] === '') {
                continue;
            }
            $value = $filters[$key];
            if ($key === 'userid' || $key === 'group') {
                if ((int) $value > 0) {
                    $params[$key] = (int) $value;
                }
            } else if ($key === 'suspendedparticipantsfilter') {
                if ($value) {
                    $params[$key] = 1;
                }
            } else {
                $params[$key] = (string) $value;
            }
        }

        // A selected user always wins over a free-text search, as in the native
        // grading table. Removing the search chip must clear both fields.
        if (isset($params['userid'])) {
            unset($params['search']);
        }
        return $params;
    }

    /**
     * Remove one criterion, preserving the other allowed URL and preference values.
     *
     * @param int $cmid Course-module ID.
     * @param array $filters Already validated active filter values.
     * @param string $key Filter chip key.
     * @return array URL parameters with a native reset value for the target.
     */
    public static function without(int $cmid, array $filters, string $key): array {
        if (!in_array($key, self::CHIP_ORDER, true)) {
            throw new \InvalidArgumentException('Unsupported assignment grading filter');
        }
        $params = self::parameters($cmid, $filters);
        if ($key === 'user') {
            unset($params['userid'], $params['search']);
            $params['search'] = '';
        } else {
            // Empty values explicitly clear persistent Moodle preferences.
            $params[$key] = in_array($key, ['group', 'suspendedparticipantsfilter'], true) ? 0 : '';
        }
        return $params;
    }

    /**
     * Clear all selection criteria without resetting presentation preferences.
     *
     * @param int $cmid Course-module ID.
     * @return array Native reset parameters.
     */
    public static function reset(int $cmid): array {
        return [
            'id' => $cmid,
            'action' => 'grading',
            'group' => 0,
            'search' => '',
            'status' => '',
            'tifirst' => '',
            'tilast' => '',
            'workflowfilter' => '',
            'markingallocationfilter' => '',
            'suspendedparticipantsfilter' => 0,
        ];
    }

    /**
     * Build visible filter chips from previously validated, authorised labels.
     *
     * @param int $cmid Course-module ID.
     * @param array $filters Already validated active filter values.
     * @param array $labels Map of accessible chip key to human-readable description.
     * @return array List containing key, label, and removal parameter array.
     */
    public static function chips(int $cmid, array $filters, array $labels): array {
        $result = [];
        foreach (self::CHIP_ORDER as $key) {
            if (!isset($labels[$key]) || $labels[$key] === '') {
                continue;
            }
            $result[] = [
                'key' => $key,
                'label' => $labels[$key],
                'params' => self::without($cmid, $filters, $key),
            ];
        }
        return $result;
    }
}
