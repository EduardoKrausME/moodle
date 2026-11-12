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
 * Format existing submission-plugin limits for the form's explanatory text.
 *
 * Presentation only. These values must never be used instead of the native
 * filemanager and online text submission validators.
 *
 * @package mod_assign
 */
final class submission_guidance {
    /**
     * Normalise configured upload limits without changing their interpretation.
     *
     * Zero bytes means no explicit maximum was supplied by the caller.
     *
     * @param int $maxfiles Native filemanager maximum number of files.
     * @param int $maxbytes Effective file size limit in bytes.
     * @param string $types Configured accepted file types, or empty for unrestricted.
     * @return array{maxfiles:int,maxbytes:int,types:string,restrictedtypes:bool,hassizelimit:bool}
     */
    public static function file_limits(int $maxfiles, int $maxbytes, string $types): array {
        $types = trim($types);
        return [
            'maxfiles' => max(0, $maxfiles),
            'maxbytes' => max(0, $maxbytes),
            'types' => $types,
            'restrictedtypes' => $types !== '',
            'hassizelimit' => $maxbytes > 0,
        ];
    }

    /**
     * Return the configured word limit only if enforcement is enabled.
     *
     * @param int $wordlimit Configured maximum words.
     * @param bool $enabled Whether the Moodle submission plugin enforces it.
     * @return int|null Active limit, or null when there is no configured limit.
     */
    public static function word_limit(int $wordlimit, bool $enabled): ?int {
        return $enabled && $wordlimit > 0 ? $wordlimit : null;
    }
}
