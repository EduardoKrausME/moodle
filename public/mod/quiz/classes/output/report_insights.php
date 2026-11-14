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

/**
 * Shared read-only presentation for the three native quiz results reports.
 *
 * @package mod_quiz
 */

namespace mod_quiz\output;

use html_writer;
use mod_quiz\local\reports\attempts_report;
use mod_quiz\local\reports\attempts_report_options;

/**
 * HTML-only report summaries. Never queries attempts, grades or statistics.
 *
 * Callers pass already authorised, already calculated values. No part of this
 * class is involved in export, report filtering or changing quiz results.
 */
final class report_insights {
    /**
     * Explain the current native attempt filter state.
     *
     * @param attempts_report_options $options Resolved options from the existing report.
     * @param int $groupid Report-selected, authorised group, or NO_GROUPS_ALLOWED.
     * @return string Accessible report scope summary.
     */
    public static function attempt_scope(attempts_report_options $options, int $groupid): string {
        $attemptlabels = [
            attempts_report::ALL_WITH => 'reportresultallwith',
            attempts_report::ENROLLED_WITH => 'reportresultenrolledwith',
            attempts_report::ENROLLED_WITHOUT => 'reportresultenrolledwithout',
            attempts_report::ENROLLED_ALL => 'reportresultenrolledall',
        ];
        $attempttype = $attemptlabels[$options->attempts] ?? 'reportresultenrolledwith';

        if ($groupid == attempts_report::NO_GROUPS_ALLOWED) {
            $group = get_string('reportresultnogroup', 'quiz');
        } else if ($groupid > 0) {
            $group = format_string(groups_get_group_name($groupid), true,
                    ['context' => \context_module::instance($options->cm->id)]);
        } else {
            $group = get_string('reportresultallaccessible', 'quiz');
        }

        $filters = [
            ['label' => get_string('reportresultattemptselection', 'quiz'),
                'value' => get_string($attempttype, 'quiz')],
            ['label' => get_string('reportresultgroup', 'quiz'), 'value' => $group],
            ['label' => get_string('reportresultgradeselection', 'quiz'),
                'value' => get_string($options->onlygraded ? 'reportresultonlygraded' : 'reportresultallselected',
                        'quiz')],
        ];
        if (is_array($options->states)) {
            $filters[] = ['label' => get_string('reportresultstates', 'quiz'),
                'value' => get_string('reportresultstatecount', 'quiz', count($options->states))];
        }
        if (!$options->usercanseegrades) {
            $filters[] = ['label' => get_string('reportresultgradevisibility', 'quiz'),
                'value' => get_string('reportresultgradeshidden', 'quiz')];
        }

        return self::filters($filters);
    }

    /**
     * Render translated scope labels as a definition list.
     *
     * @param array $filters Each item is a label and scalar display value.
     * @return string Semantic HTML, or empty string for an empty scope.
     */
    public static function filters(array $filters): string {
        if (!$filters) {
            return '';
        }
        $content = '';
        foreach ($filters as $item) {
            $content .= html_writer::tag('div',
                html_writer::tag('dt', s((string) $item['label'])) .
                html_writer::tag('dd', s((string) $item['value'])),
                ['class' => 'mod-quiz-results-scope__item']);
        }

        $title = get_string('reportresultfilters', 'quiz');
        return html_writer::tag('section',
            html_writer::tag('h3', s($title), ['class' => 'h5']) .
            html_writer::tag('dl', $content, ['class' => 'mod-quiz-results-scope__list']),
            ['class' => 'mod-quiz-results-scope', 'aria-label' => $title,
                'data-region' => 'quiz-results-scope']);
    }

    /**
     * Display figures already computed and authorised by the calling report.
     *
     * @param array $metrics Each item has label, value and optional note.
     * @param string $title A translated heading.
     * @return string Read-only summary, suitable for empty and populated reports.
     */
    public static function metrics(array $metrics, string $title): string {
        if (!$metrics) {
            return '';
        }
        $items = '';
        foreach ($metrics as $metric) {
            $item = html_writer::tag('dt', s((string) $metric['label']),
                    ['class' => 'mod-quiz-results-card__label']);
            $item .= html_writer::tag('dd', s((string) $metric['value']),
                    ['class' => 'mod-quiz-results-card__value']);
            if (!empty($metric['note'])) {
                $item .= html_writer::tag('p', s((string) $metric['note']),
                    ['class' => 'mod-quiz-results-card__note']);
            }
            $items .= html_writer::tag('div', $item, ['class' => 'mod-quiz-results-card']);
        }

        return html_writer::tag('section',
            html_writer::tag('h3', s($title), ['class' => 'h5']) .
            html_writer::tag('dl', $items, ['class' => 'mod-quiz-results-cards']),
            ['class' => 'mod-quiz-results-summary', 'aria-label' => $title,
                'data-region' => 'quiz-results-summary']);
    }

    /**
     * Give existing grade distribution charts an accessible data equivalent.
     *
     * @param array $labels Native chart axis labels.
     * @param array $values Already computed grade-band counts.
     * @param string $title The exact chart's already authorised title.
     * @return string Expandable HTML table with no extra database queries.
     */
    public static function chart_data(array $labels, array $values, string $title): string {
        $rows = '';
        $labels = array_values($labels);
        $values = array_values($values);
        foreach ($labels as $index => $label) {
            if (!array_key_exists($index, $values)) {
                break;
            }
            $rows .= html_writer::tag('tr',
                html_writer::tag('th', s((string) $label), ['scope' => 'row']) .
                html_writer::tag('td', s((string) $values[$index])));
        }

        if ($rows === '') {
            return '';
        }

        $headers = html_writer::tag('tr',
            html_writer::tag('th', get_string('reportresultgraderange', 'quiz'), ['scope' => 'col']) .
            html_writer::tag('th', get_string('reportresultparticipants', 'quiz'), ['scope' => 'col']));
        $table = html_writer::tag('table',
            html_writer::tag('caption', s($title)) .
            html_writer::tag('thead', $headers) .
            html_writer::tag('tbody', $rows),
            ['class' => 'table table-sm generaltable']);
        return html_writer::tag('details',
            html_writer::tag('summary', get_string('reportresultcharttable', 'quiz')) . $table,
            ['class' => 'mod-quiz-results-chartdata', 'data-region' => 'quiz-results-chartdata']);
    }

    /**
     * Expose the values of a native multi-series question chart in a table.
     *
     * @param array $labels Question position labels.
     * @param array $series Pairs of translated name and already-calculated values.
     * @param string $title Chart title.
     * @return string Accessible chart data without new queries.
     */
    public static function series_chart_data(array $labels, array $series, string $title): string {
        if (!$labels || !$series) {
            return '';
        }

        $heading = html_writer::tag('th', get_string('reportresultquestionposition', 'quiz'),
                ['scope' => 'col']);
        $columns = [];
        foreach ($series as $item) {
            $heading .= html_writer::tag('th', s((string) $item['label']), ['scope' => 'col']);
            $columns[] = array_values($item['values']);
        }
        $rows = '';
        foreach (array_values($labels) as $index => $label) {
            $cells = html_writer::tag('th', s((string) $label), ['scope' => 'row']);
            foreach ($columns as $values) {
                $value = $values[$index] ?? get_string('reportresultna', 'quiz');
                $cells .= html_writer::tag('td', s((string) $value));
            }
            $rows .= html_writer::tag('tr', $cells);
        }

        $table = html_writer::tag('table',
            html_writer::tag('caption', s($title)) .
            html_writer::tag('thead', html_writer::tag('tr', $heading)) .
            html_writer::tag('tbody', $rows),
            ['class' => 'table table-sm generaltable']);
        return html_writer::tag('details',
            html_writer::tag('summary', get_string('reportresultcharttable', 'quiz')) . $table,
            ['class' => 'mod-quiz-results-chartdata', 'data-region' => 'quiz-results-chartdata']);
    }

    /**
     * Open an optional horizontal-scrolling region around the existing table.
     *
     * @param string $title Translated name of the table.
     * @return string Opening HTML; the caller must balance using table_end().
     */
    public static function table_start(string $title): string {
        return html_writer::start_tag('div', [
            'class' => 'mod-quiz-results-table-scroll',
            'data-region' => 'quiz-results-table-scroll',
            'data-overflow-hint' => get_string('reportresultscrollhint', 'quiz'),
            'role' => 'region',
            'aria-label' => $title,
            'tabindex' => '0',
        ]) . html_writer::tag('p', '',
                ['class' => 'mod-quiz-results-scroll-hint',
                 'data-region' => 'quiz-results-scroll-hint']);
    }

    /**
     * Close the table region without altering the native table implementation.
     *
     * @return string Closing HTML.
     */
    public static function table_end(): string {
        return html_writer::end_tag('div');
    }
}
