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
 * Sanitises per-grader presentation preferences, never submission data.
 *
 * @package mod_assign
 */
final class grading_station_layout {
    public const DEFAULT_SPLIT = 70;
    public const MIN_SPLIT = 8;
    public const MAX_SPLIT = 92;

    /**
     * @param mixed $value Native core_user preference.
     * @param bool $hasreview True when an enabled plugin supports review.
     * @return array{mode: string, split: int}
     */
    public static function normalise($value, bool $hasreview = true): array {
        $default = ['mode' => 'split', 'split' => self::DEFAULT_SPLIT];
        if (!$hasreview) {
            return $default;
        }
        if (!is_string($value) || !preg_match('/^(split|review|grade)_([0-9]{1,3})$/D', $value, $matches)) {
            return $default;
        }
        $split = (int) $matches[2];
        if ($split < self::MIN_SPLIT || $split > self::MAX_SPLIT) {
            return $default;
        }
        return ['mode' => $matches[1], 'split' => $split];
    }
}
