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
 * Lazy-load conversations for an expanded course. Duplicate course cards share
 * the same in-flight request; cached results live in this page only.
 *
 * @param {HTMLElement|jQuery} root My courses block.
 */
export const init = root => {
    ForumUnreadCounts.init(root);

    const container = root instanceof Element ? root : root[0];
    const inFlight = new Map();
    const cache = new Map();
    let generation = 0;
    let renderSequence = 0;

    const isAttached = wrapper => wrapper.isConnected && container.contains(wrapper);

    /**
     * Update feed links when the course overview changes its display mode.
     */
    const updateFeedLinks = () => {
        container.querySelectorAll('[data-action="open-recent-feed"]').forEach(link => {
            const summary = link.closest('[data-region="forum-course-summary"]');
            if (summary) {
                link.href = M.cfg.wwwroot + '/mod/forum/recent.php?courseid=' +
                    encodeURIComponent(summary.dataset.courseId);
            }
        });
    };
    const observer = new MutationObserver(updateFeedLinks);
    observer.observe(container, {childList: true, subtree: true});
    updateFeedLinks();

    /**
     * Reflect expansion in both the accessible control and its target.
     *
     * @param {HTMLElement} button Toggle button.
     * @param {HTMLElement} content Expandable content.
     * @param {boolean} expanded Desired state.
     */
    const setExpanded = (button, content, expanded) => {
        button.setAttribute('aria-expanded', String(expanded));
        content.hidden = !expanded;
        const label = button.querySelector('[data-region="forum-summary-toggle-label"]');
        if (!label) {
            return;
        }
        const key = expanded ? 'recentforumcollapse' : 'recentforumexpand';
        getString(key, 'block_myoverview').then(value => {
            if (button.isConnected && button.getAttribute('aria-expanded') === String(expanded)) {
                label.textContent = value;
            }
        }).catch(Notification.exception);
    };

    /**
     * Deduplicate concurrent requests by course ID. The server still validates
     * enrolments, activity visibility, groups and Q&A for every fetch.
     *
     * @param {number} courseid Selected course ID.
     * @returns {Promise<object>} Validated recent discussions.
     */
    const fetchCourse = courseid => {
        if (cache.has(courseid)) {
            return Promise.resolve(cache.get(courseid));
        }
        if (inFlight.has(courseid)) {
            return inFlight.get(courseid);
        }
        const version = generation;
        const promise = Promise.resolve().then(() => Ajax.call([{
            methodname: 'mod_forum_get_course_recent_discussions',
            args: {courseid},
        }])[0]).then(response => {
            if (version === generation) {
                cache.set(courseid, response);
            }
            return response;
        }).finally(() => {
            if (inFlight.get(courseid) === promise) {
                inFlight.delete(courseid);
            }
        });
        inFlight.set(courseid, promise);
        return promise;
    };

    /**
     * Load one summary, without rendering into a card removed by pagination or
     * into a panel that has since started another request.
     *
     * @param {HTMLElement} wrapper Course summary.
     * @param {HTMLElement} button Disclosure button.
     * @param {HTMLElement} content Content area.
     * @returns {Promise<void>}
     */
    const loadSummary = async(wrapper, button, content) => {
        if (content.dataset.loaded === 'true' || content.dataset.loading === 'true') {
            return;
        }

        const version = generation;
        const token = String(++renderSequence);
        content.dataset.loading = 'true';
        content.dataset.renderToken = token;
        content.setAttribute('aria-busy', 'true');
        const isCurrent = () => version === generation && isAttached(wrapper) &&
            content.dataset.renderToken === token;

        try {
            const loading = await getString('recentforumloading', 'block_myoverview');
            if (!isCurrent()) {
                return;
            }
            content.textContent = loading;

            const courseid = Number(wrapper.dataset.courseId);
            const response = await fetchCourse(courseid);
            if (!isCurrent()) {
                return;
            }
            const [html, js] = await Templates.render('block_myoverview/recent-forum-discussions-list', response);
            if (!isCurrent()) {
                return;
            }
            await Templates.replaceNodeContents($(content), html, js);
            if (isCurrent()) {
                content.dataset.loaded = 'true';
            }
        } catch (error) {
            if (isCurrent()) {
                setExpanded(button, content, false);
                Notification.exception(error);
            }
        } finally {
            if (isCurrent()) {
                delete content.dataset.loading;
                delete content.dataset.renderToken;
                content.setAttribute('aria-busy', 'false');
            }
        }
    };

    $(root).on('click', '[data-action="toggle-forum-discussions"]', event => {
        const button = event.currentTarget;
        const wrapper = button.closest('[data-region="forum-course-summary"]');
        const content = wrapper.querySelector('[data-region="forum-discussions-content"]');
        const expanded = button.getAttribute('aria-expanded') === 'true';
        setExpanded(button, content, !expanded);

        if (!expanded) {
            loadSummary(wrapper, button, content).catch(Notification.exception);
        }
    });

    // A bfcache restore may follow reading a discussion. Discard old messages
    // and reload expanded panels so that read states and permissions stay fresh.
    window.addEventListener('pageshow', event => {
        if (!event.persisted) {
            return;
        }
        generation++;
        cache.clear();
        inFlight.clear();
        for (const wrapper of container.querySelectorAll('[data-region="forum-course-summary"]')) {
            const content = wrapper.querySelector('[data-region="forum-discussions-content"]');
            const button = wrapper.querySelector('[data-action="toggle-forum-discussions"]');
            if (!button || !content) {
                continue;
            }
            delete content.dataset.loaded;
            delete content.dataset.loading;
            delete content.dataset.renderToken;
            content.setAttribute('aria-busy', 'false');
            content.replaceChildren();

            if (button.getAttribute('aria-expanded') === 'true') {
                loadSummary(wrapper, button, content).catch(Notification.exception);
            }
        }
    });
};
