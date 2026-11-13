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
 * A single-flight state machine for a native Moodle assignment grade save.
 *
 * Only request-local context is kept. No grades, user identities, or feedback
 * are persisted, and this module deliberately does not implement a new API.
 *
 * @module mod_assign/grading_save_state
 */
define([], function() {
    'use strict';

    class GradingSaveState {
        constructor() {
            this.active = null;
            this.sequence = 0;
        }

        /**
         * Claim an exclusive save slot.
         *
         * @param {Number} userId Currently loaded participant ID.
         * @param {Number} attempt Current submission attempt number.
         * @returns {Object|null} Immutable request context or null if busy.
         */
        begin(userId, attempt) {
            if (this.active || !Number.isInteger(Number(userId)) || Number(userId) <= 0) {
                return null;
            }

            this.sequence += 1;
            this.active = Object.freeze({
                token: this.sequence,
                userId: Number(userId),
                attempt: Number(attempt),
            });
            return this.active;
        }

        /**
         * Check that an asynchronous response belongs to the active request.
         *
         * @param {Object} request Request context from begin().
         * @returns {Boolean}
         */
        isCurrent(request) {
            return Boolean(this.active && request && this.active.token === request.token &&
                this.active.userId === request.userId && this.active.attempt === request.attempt);
        }

        /**
         * Release the slot once, ignoring stale/duplicate callbacks.
         *
         * @param {Object} request Request context from begin().
         * @returns {Boolean} Whether the request was the active operation.
         */
        finish(request) {
            if (!this.isCurrent(request)) {
                return false;
            }
            this.active = null;
            return true;
        }

        /**
         * @returns {Boolean} Whether a save is still in progress.
         */
        isBusy() {
            return this.active !== null;
        }
    }

    return GradingSaveState;
});
