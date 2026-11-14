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
 * Read-only quiz report results presentation tests.
 *
 * @package mod_quiz
 */

namespace mod_quiz\output;

use advanced_testcase;
use mod_quiz\local\reports\attempts_report;
use mod_quiz\local\reports\attempts_report_options;

/**
 * Ensure report decoration does not change native data or permissions.
 */
final class report_insights_test extends advanced_testcase {
    /**
     * Values from quizzes and groups must never create executable HTML.
     */
    public function test_scope_and_metrics_escape_untrusted_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $html = report_insights::filters([
            ['label' => 'Group <script>', 'value' => '<img src=x onerror=alert(1)>'],
        ]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('data-region="quiz-results-scope"', $html);

        $cards = report_insights::metrics([
            ['label' => 'Attempts', 'value' => '0'],
            ['label' => 'Average', 'value' => get_string('reportresultna', 'quiz')],
        ], 'Result summary');
        $this->assertStringContainsString('>0<', $cards);
        $this->assertStringContainsString(get_string('reportresultna', 'quiz'), $cards);
        $this->assertStringContainsString('<dt', $cards);
        $this->assertStringContainsString('<dd', $cards);
        $this->assertStringNotContainsString('<input', $cards);
    }

    /**
     * The summary cannot convert a filtered report into a different attempt set.
     */
    public function test_native_attempt_options_drive_filter_labels(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $reflection = new \ReflectionClass(attempts_report_options::class);
        $options = $reflection->newInstanceWithoutConstructor();
        $options->attempts = attempts_report::ENROLLED_WITHOUT;
        $options->onlygraded = false;
        $options->states = ['finished', 'overdue'];
        $options->usercanseegrades = false;

        $html = report_insights::attempt_scope($options, 0);
        $this->assertStringContainsString(
                get_string('reportresultenrolledwithout', 'quiz'), $html);
        $this->assertStringContainsString(
                get_string('reportresultgradeshidden', 'quiz'), $html);
        $this->assertStringContainsString(
                get_string('reportresultstatecount', 'quiz', 2), $html);
        $this->assertStringNotContainsString('Student One', $html);
    }

    /**
     * The chart table must use exactly the labels and values supplied by the native chart.
     */
    public function test_grade_band_table_preserves_zero_and_nonzero_values(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $table = report_insights::chart_data(
                ['0-20%', '20-40%', '40-60%'], [0, 2, 5], 'Grade distribution');
        $this->assertStringContainsString('<caption>Grade distribution</caption>', $table);
        $this->assertStringContainsString('0-20%', $table);
        $this->assertStringContainsString('>0</td>', $table);
        $this->assertStringContainsString('>5</td>', $table);
        $this->assertStringContainsString('<details', $table);
        $this->assertSame('', report_insights::chart_data([], [], 'No grades'));
    }

    /**
     * Multi-series statistics must use native chart data without inventing rows.
     */
    public function test_question_statistics_table_uses_authorised_series(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $table = report_insights::series_chart_data(
                ['1', '2a'],
                [
                    ['label' => 'Facility', 'values' => ['25.00', '60.00']],
                    ['label' => 'Discrimination', 'values' => ['10.00', '20.00']],
                ],
                'Question statistics');
        $this->assertStringContainsString('>2a</th>', $table);
        $this->assertStringContainsString('>60.00</td>', $table);
        $this->assertStringContainsString('>20.00</td>', $table);
        $this->assertStringContainsString('>Facility</th>', $table);
        $this->assertSame('', report_insights::series_chart_data([], [], 'No statistics'));
    }

    /**
     * Scroll regions add semantics without mutating the table implementation.
     */
    public function test_table_region_is_balanced_and_keyboard_accessible(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $markup = report_insights::table_start('Student attempts') .
                '<table id="attempts"><tbody><tr><td>Answer</td></tr></tbody></table>' .
                report_insights::table_end();
        $this->assertStringContainsString('data-region="quiz-results-table-scroll"', $markup);
        $this->assertStringContainsString('tabindex="0"', $markup);
        $this->assertStringContainsString('role="region"', $markup);
        $this->assertStringContainsString('<table id="attempts">', $markup);
        $this->assertStringEndsWith('</div>', $markup);
    }
}
