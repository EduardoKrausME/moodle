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

/**
 * Unit regressions for the single-flight grading request lifecycle.
 *
 * @module mod_assign/grading_save_state_test
 */
define(['mod_assign/grading_save_state'], function(GradingSaveState) {
    'use strict';

    const assert = (condition, message) => {
        if (!condition) {
            throw new Error(message);
        }
    };

    describe('mod_assign/grading_save_state', function() {
        it('begins idle and accepts a valid participant', function() {
            const state = new GradingSaveState();
            assert(!state.isBusy(), 'state should begin idle');
            const request = state.begin(42, -1);
            assert(request.userId === 42, 'participant must be retained');
            assert(request.attempt === -1, 'attempt must be retained');
            assert(state.isBusy(), 'request should be active');
        });

        it('rejects a second concurrent grading request', function() {
            const state = new GradingSaveState();
            state.begin(42, 1);
            assert(state.begin(42, 1) === null, 'duplicate must not be accepted');
            assert(state.begin(51, 1) === null, 'different participant must not start in parallel');
        });

        it('rejects missing or invalid participant identifiers', function() {
            const state = new GradingSaveState();
            assert(state.begin(0, 1) === null, 'zero must be rejected');
            assert(state.begin(-1, 1) === null, 'negative ID must be rejected');
            assert(state.begin('abc', 1) === null, 'non-numeric ID must be rejected');
            assert(!state.isBusy(), 'invalid requests must not set busy');
        });

        it('matches only the original request token, user and attempt', function() {
            const state = new GradingSaveState();
            const first = state.begin(42, 1);
            assert(state.isCurrent(first), 'original request should match');
            assert(!state.isCurrent({...first, userId: 51}), 'other user must not match');
            assert(!state.isCurrent({...first, attempt: 2}), 'other attempt must not match');
            assert(!state.isCurrent({...first, token: first.token + 1}), 'other token must not match');
        });

        it('releases the save slot exactly once', function() {
            const state = new GradingSaveState();
            const first = state.begin(42, 1);
            assert(state.finish(first), 'first finish should succeed');
            assert(!state.finish(first), 'duplicate callback must be ignored');
            assert(!state.isBusy(), 'state should be idle');
        });

        it('allows retry after a completed or failed operation', function() {
            const state = new GradingSaveState();
            const first = state.begin(42, 1);
            state.finish(first);
            const second = state.begin(42, 1);
            assert(second !== null, 'retry should be allowed');
            assert(second.token > first.token, 'token must be new');
        });

        it('does not release the new request for an old callback', function() {
            const state = new GradingSaveState();
            const first = state.begin(42, 1);
            state.finish(first);
            const second = state.begin(51, 2);
            assert(!state.finish(first), 'stale completion must be ignored');
            assert(state.isBusy(), 'new request must remain active');
            assert(state.isCurrent(second), 'new request must remain current');
        });

        it('freezes request identity for the asynchronous response', function() {
            const state = new GradingSaveState();
            const first = state.begin(42, 1);
            try {
                first.userId = 10;
            } catch (error) {
                // Strict-mode engines reject writes to frozen properties.
            }
            assert(first.userId === 42, 'request must not be mutated');
            assert(Object.isFrozen(first), 'context must be immutable');
        });

        it('does not keep grade data or feedback in its state', function() {
            const state = new GradingSaveState();
            const first = state.begin(42, -1);
            assert(Object.keys(first).join(',') === 'token,userId,attempt', 'only request identity should be stored');
            assert(!('grade' in first), 'no grade payload');
            assert(!('feedback' in first), 'no feedback payload');
        });
    });
});
