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

import Ajax from 'core/ajax';
import * as Str from 'core/str';
import * as Notification from 'core/notification';

const SELECTOR = '[data-region="forum-course-summary"]';
const CACHE = new WeakSet();

/**
 * Update one course summary from a validated service result.
 *
 * @param {HTMLElement} node Course summary node.
 * @param {object} data Verified count for the course.
 */
const display = async(node, data) => {
    if (!node.isConnected) {
        return;
    }
    const badge = node.querySelector('[data-region="forum-unread-count"]');
    const status = node.querySelector('[data-region="forum-unread-status"]');
    badge.hidden = true;
    status.hidden = true;
    badge.textContent = '';
    status.textContent = '';

    if (!data || data.state === 'none') {
        return;
    }
    if (data.state === 'activity') {
        status.textContent = await Str.get_string('forumunreadactivity', 'block_myoverview');
        status.hidden = false;
        return;
    }

    const singular = data.count === 1;
    const partial = data.state === 'partial';
    const key = partial ? (singular ? 'forumunreadpartialone' : 'forumunreadpartialmany')
        : (singular ? 'forumunreadone' : 'forumunreadmany');
    badge.textContent = await Str.get_string(key, 'block_myoverview', data.count);
    badge.hidden = false;

    if (!partial && data.forumcount > 0) {
        const keyForums = data.forumcount === 1 ? 'forumunreadforumsone' : 'forumunreadforumsmany';
        status.textContent = await Str.get_string(keyForums, 'block_myoverview', data.forumcount);
        status.hidden = false;
    }
};

/**
 * Bind one observer per block and send the course IDs in a single AJAX request.
 * New pages and layout changes are detected without an N+1 request per card.
 *
 * @param {HTMLElement} root Course overview block element.
 */
export const init = root => {
    const container = root instanceof Element ? root : root[0];
    let pending = false;

    const scan = () => {
        const newNodes = [...container.querySelectorAll(SELECTOR)].filter(node => !CACHE.has(node));
        if (!newNodes.length) {
            return;
        }
        for (const node of newNodes) {
            CACHE.add(node);
        }
        fetchCounts(newNodes);
    };

    const fetchCounts = nodes => {
        const ids = [...new Set(nodes.map(node => Number(node.dataset.courseId)))].filter(id => id > 0);
        if (!ids.length) {
            return;
        }
        const request = {
            methodname: 'mod_forum_get_course_unread_counts',
            args: {courseids: ids},
        };
        Ajax.call([request])[0].then(response => {
            const counts = new Map(response.courses.map(entry => [entry.courseid, entry]));
            return Promise.all(nodes.map(node => display(node, counts.get(Number(node.dataset.courseId)))));
        }).catch(Notification.exception);
    };

    const scheduleScan = () => {
        if (pending) {
            return;
        }
        pending = true;
        Promise.resolve().then(() => {
            pending = false;
            scan();
        });
    };

    const observer = new MutationObserver(scheduleScan);
    observer.observe(container, {childList: true, subtree: true});
    scheduleScan();

    // Re-fetch when the browser restores a cached dashboard after visiting a discussion.
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            fetchCounts([...container.querySelectorAll(SELECTOR)]);
        }
    });
};
