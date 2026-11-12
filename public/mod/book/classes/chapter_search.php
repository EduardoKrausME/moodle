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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

namespace mod_book;

/**
 * Scoped chapter search. Operates on the chapters already loaded for the Book
 * and never includes hidden content unless the reader has permission.
 *
 * @package mod_book
 */
final class chapter_search {
    /**
     * Find matching chapter titles or text, without exposing raw HTML.
     *
     * @param array $chapters book_preload_chapters() result.
     * @param string $query Search term.
     * @param bool $viewhidden Permission to view hidden chapters.
     * @param int $limit Maximum results.
     * @return array Matching chapter data.
     */
    public static function find(array $chapters, string $query, bool $viewhidden = false,
            int $limit = 40): array {
        $query = trim($query);
        if ($query === '' || \core_text::strlen($query) > 100) {
            return [];
        }

        $needle = \core_text::strtolower($query);
        $results = [];
        foreach ($chapters as $chapter) {
            if ($chapter->hidden && !$viewhidden) {
                continue;
            }

            $title = trim(html_entity_decode(strip_tags($chapter->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $body = html_entity_decode(strip_tags($chapter->content ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body = trim(preg_replace('/\s+/u', ' ', $body));
            $titlematch = \core_text::strpos(\core_text::strtolower($title), $needle);
            $bodymatch = \core_text::strpos(\core_text::strtolower($body), $needle);
            if ($titlematch === false && $bodymatch === false) {
                continue;
            }

            $position = $bodymatch === false ? 0 : max(0, $bodymatch - 65);
            $excerpt = \core_text::substr($body, $position, 165);
            if ($position > 0) {
                $excerpt = '…' . $excerpt;
            }
            if (\core_text::strlen($body) > $position + 165) {
                $excerpt .= '…';
            }
            $results[] = [
                'chapterid' => (int) $chapter->id,
                'title' => $title,
                'excerpt' => $excerpt,
            ];
            if (count($results) >= $limit) {
                break;
            }
        }
        return $results;
    }
}
