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

import $ from 'jquery';
import Ajax from 'core/ajax';
import * as Templates from 'core/templates';
import * as Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import * as ForumUnreadCounts from 'block_myoverview/forum_unread_counts';

/**
 * Handle lazy loading, including after course pagination or display changes.
 *
 * @param {HTMLElement} root My courses block.
 */
export const init = root => {
    ForumUnreadCounts.init(root);
    $(root).on('click', '[data-action="toggle-forum-discussions"]', async event => {
        const button = event.currentTarget;
        const wrapper = button.closest('[data-region="forum-course-summary"]');
        const content = wrapper.querySelector('[data-region="forum-discussions-content"]');
        if (button.disabled) {
            return;
        }
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        content.hidden = expanded;
        if (expanded || content.dataset.loaded === 'true') {
            return;
        }
        button.disabled = true;
        try {
            content.textContent = await getString('recentforumloading', 'block_myoverview');
            const request = {
                methodname: 'mod_forum_get_course_recent_discussions',
                args: {courseid: Number(wrapper.dataset.courseId)},
            };
            const response = await Ajax.call([request])[0];
            const [html, js] = await Templates.render('block_myoverview/recent-forum-discussions-list', response);
            await Templates.replaceNodeContents($(content), html, js);
            content.dataset.loaded = 'true';
        } catch (error) {
            button.setAttribute('aria-expanded', 'false');
            content.hidden = true;
            Notification.exception(error);
        } finally {
            button.disabled = false;
        }
    });
};
