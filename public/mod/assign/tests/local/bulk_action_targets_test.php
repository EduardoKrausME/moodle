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

namespace mod_assign\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure tests for the server-side boundary of native assignment bulk actions.
 *
 * @package mod_assign
 */
#[\PHPUnit\Framework\Attributes\CoversClass(bulk_action_targets::class)]
final class bulk_action_targets_test extends \basic_testcase {
    public function test_single_participant(): void {
        $this->assertSame([9], bulk_action_targets::normalise('9'));
    }

    public function test_valid_selection_keeps_original_order(): void {
        $this->assertSame([42, 8, 91], bulk_action_targets::normalise('42,8,91'));
    }

    public function test_duplicate_ids_are_processed_only_once(): void {
        $this->assertSame([3, 2, 5], bulk_action_targets::normalise('3,2,3,5,2'));
    }

    public function test_empty_selection_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        bulk_action_targets::normalise('');
    }

    public function test_zero_or_negative_ids_are_rejected(): void {
        foreach (['0', '-3', '2,0', '2,-3'] as $value) {
            try {
                bulk_action_targets::normalise($value);
                $this->fail('Invalid ID unexpectedly accepted: ' . $value);
            } catch (\moodle_exception $expected) {
                $this->assertSame('invalidbulkselection', $expected->errorcode);
            }
        }
    }

    public function test_malformed_sequences_are_rejected(): void {
        foreach (['1,', ',2', '1,,2', '01', '1,02', '1 2', '1, 2', '1.0', '1e2', '1<script>'] as $value) {
            try {
                bulk_action_targets::normalise($value);
                $this->fail('Invalid sequence unexpectedly accepted: ' . $value);
            } catch (\moodle_exception $expected) {
                $this->assertSame('invalidbulkselection', $expected->errorcode);
            }
        }
    }

    public function test_integer_overflow_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        bulk_action_targets::normalise('9999999999999999999999999999999');
    }

    public function test_maximum_selection_is_accepted(): void {
        $ids = range(1, bulk_action_targets::MAX_TARGETS);
        $this->assertSame($ids, bulk_action_targets::normalise(implode(',', $ids)));
    }

    public function test_selection_over_limit_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        $ids = range(1, bulk_action_targets::MAX_TARGETS + 1);
        bulk_action_targets::normalise(implode(',', $ids));
    }

    public function test_expected_native_operations_are_supported(): void {
        foreach ([
            'lock', 'unlock', 'downloadselected', 'removesubmission',
            'grantextension', 'addattempt', 'reverttodraft',
            'setmarkingworkflowstate', 'setmarkingallocation',
            'plugingradingbatchoperation_editpdf_download',
            'plugingradingbatchoperation_editpdf_send-report',
        ] as $operation) {
            $this->assertTrue(bulk_action_targets::supported($operation), $operation);
        }
    }

    public function test_unknown_and_malformed_operations_are_not_supported(): void {
        foreach ([
            '', 'deleteuser', 'releasegrades', 'downloadall',
            'plugingradingbatchoperation_', 'plugingradingbatchoperation_editpdf',
            'plugingradingbatchoperation_foo_', 'plugingradingbatchoperation_<x>_download',
        ] as $operation) {
            $this->assertFalse(bulk_action_targets::supported($operation), $operation);
        }
    }

    public function test_normalisation_never_returns_identifying_or_grade_data(): void {
        $this->assertSame([12, 30], bulk_action_targets::normalise('12,30'));
        $this->assertCount(2, bulk_action_targets::normalise('12,30'));
    }
}
