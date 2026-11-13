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
 * Pure ordering helper for the native grading table.
 *
 * No fields are removed, renamed or fabricated. Column identifiers, headers
 * and their relative order outside the main summary are retained, so existing
 * plugins, preferences and download worksheets continue to work as before.
 *
 * @package mod_assign
 */
final class grading_table_layout {
    /** @var string[] Columns that should be encountered first on narrow screens. */
    private const PRIORITY = [
        'select',
        'fullname',
        'recordid',
        'status',
        'team',
        'duedate',
        'timesubmitted',
        'grade',
    ];

    /**
     * Reorder only rendered HTML columns, preserving all original column keys.
     *
     * @param string[] $columns Native column identifiers.
     * @param string[] $headers Headers corresponding to the original identifiers.
     * @param bool $download True for CSV/export, which must retain the native order.
     * @return array{0: array, 1: array} Ordered columns and headers.
     */
    public static function prioritise(array $columns, array $headers, bool $download): array {
        if ($download || count($columns) !== count($headers)) {
            return [$columns, $headers];
        }

        $map = array_combine($columns, $headers);
        $ordered = [];
        foreach (self::PRIORITY as $column) {
            if (array_key_exists($column, $map)) {
                $ordered[$column] = $map[$column];
            }
        }
        foreach ($map as $column => $header) {
            if (!array_key_exists($column, $ordered)) {
                $ordered[$column] = $header;
            }
        }

        // array_values must be used: table_sql expects parallel numeric arrays.
        return [array_keys($ordered), array_values($ordered)];
    }

    /**
     * Describe semantic column roles without relying on numeric c0/c1 indexes.
     *
     * @param string[] $columns Actual, permission-aware columns.
     * @return array<string, string> Classes keyed by column identifier.
     */
    public static function roles(array $columns): array {
        $roles = [];
        foreach ($columns as $column) {
            $roles[$column] = match ($column) {
                'select' => 'assign-grading-select',
                'fullname', 'recordid' => 'assign-grading-identity',
                'status' => 'status assign-grading-status',
                'team' => 'assign-grading-team',
                'duedate', 'timesubmitted' => 'assign-grading-date',
                'grade' => 'grade assign-grading-grade',
                default => 'assign-grading-extra',
            };
        }
        return $roles;
    }
}
