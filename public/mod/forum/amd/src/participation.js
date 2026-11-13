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

/**
 * Keep dependent forum and group filters valid when changing their scope.
 * The report remains entirely functional with ordinary GET forms.
 *
 * @module mod_forum/participation
 */
export const init = () => {
    const root = document.querySelector('[data-region="forum-participation"]');
    if (!root) {
        return;
    }
    const course = root.querySelector('[name="courseid"]');
    const forum = root.querySelector('[name="forumid"]');
    const group = root.querySelector('[name="groupid"]');
    course?.addEventListener('change', () => {
        forum.value = '0';
        if (group) {
            group.value = '0';
        }
        course.form.requestSubmit();
    });
    forum?.addEventListener('change', () => {
        if (group) {
            group.value = '0';
        }
        forum.form.requestSubmit();
    });
};
