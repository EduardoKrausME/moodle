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
 * Presentation-only interpretation of the native assignment submission states.
 *
 * This class must never determine whether a user may edit, save or submit;
 * the assign class continues to own permissions and all state transitions.
 *
 * @package mod_assign
 */
final class submission_workflow {
    /**
     * Build an explanation for the learner, never a submission permission.
     *
     * @param string $status Native submission status.
     * @param bool $requiresconfirmation The activity uses the draft/submit flow.
     * @param bool $locked Native submission locked flag.
     * @param bool $canfinalise Native availability of the Submit assignment action.
     * @return array Descriptive state for the template.
     */
    public static function describe(
        string $status,
        bool $requiresconfirmation,
        bool $locked,
        bool $canfinalise
    ): array {
        $sent = $status === 'submitted';
        $draft = $status === 'draft';
        $reopened = $status === 'reopened';

        if ($sent) {
            $state = 'sent';
        } else if ($locked) {
            $state = 'locked';
        } else if ($draft && $requiresconfirmation) {
            $state = $canfinalise ? 'draftready' : 'draftpending';
        } else if ($reopened) {
            $state = 'reopened';
        } else if ($draft) {
            // A draft with draft mode disabled may be legacy data; do not imply it is final.
            $state = 'draftpending';
        } else {
            $state = 'notstarted';
        }

        return [
            'state' => $state,
            'requiresconfirmation' => $requiresconfirmation,
            'preparationcomplete' => $draft || $sent,
            'finalcomplete' => $sent,
            'canfinalise' => $canfinalise && !$locked && !$sent && $requiresconfirmation,
        ];
    }
}
