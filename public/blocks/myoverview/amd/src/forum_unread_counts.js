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
const MAX_BATCH = 96;
const MAX_CONCURRENT = 2;
const ROOT_MARGIN = '250px 0px';

/**
 * Render an access-checked unread count, without applying obsolete translations.
 *
 * @param {HTMLElement} node Attached course summary.
 * @param {object} data Verified count from the current request.
 * @param {Function} isCurrent Whether the response is still valid for this node.
 */
const display = async(node, data, isCurrent) => {
    if (!isCurrent(node)) {
        return;
    }

    const badge = node.querySelector('[data-region="forum-unread-count"]');
    const status = node.querySelector('[data-region="forum-unread-status"]');
    if (!badge || !status) {
        return;
    }
    badge.hidden = true;
    status.hidden = true;
    badge.textContent = '';
    status.textContent = '';

    if (!data || data.state === 'none') {
        return;
    }
    if (data.state === 'activity') {
        const message = await Str.get_string('forumunreadactivity', 'block_myoverview');
        if (isCurrent(node)) {
            status.textContent = message;
            status.hidden = false;
        }
        return;
    }

    const singular = data.count === 1;
    const partial = data.state === 'partial';
    const key = partial ? (singular ? 'forumunreadpartialone' : 'forumunreadpartialmany')
        : (singular ? 'forumunreadone' : 'forumunreadmany');
    const message = await Str.get_string(key, 'block_myoverview', data.count);
    if (!isCurrent(node)) {
        return;
    }
    badge.textContent = message;
    badge.hidden = false;

    if (!partial && data.forumcount > 0) {
        const keyForums = data.forumcount === 1 ? 'forumunreadforumsone' : 'forumunreadforumsmany';
        const description = await Str.get_string(keyForums, 'block_myoverview', data.forumcount);
        if (isCurrent(node)) {
            status.textContent = description;
            status.hidden = false;
        }
    }
};

/**
 * Observe the visible course summaries and resolve their counters in bounded
 * AJAX batches. A result is shared by matching cards within the same page,
 * but never persisted or shared between users.
 *
 * @param {HTMLElement|jQuery} root My courses block.
 */
export const init = root => {
    const container = root instanceof Element ? root : root[0];
    const nodes = new Set();
    const byCourse = new Map();
    const eligible = new Set();
    const queued = new Set();
    const requesting = new Set();
    const cache = new Map();
    let active = 0;
    let generation = 0;
    let scanPending = false;
    let pumpPending = false;

    const isAttached = node => node.isConnected && container.contains(node);
    const currentNodes = courseid => byCourse.get(courseid) || new Set();

    const clearCount = node => {
        const badge = node.querySelector('[data-region="forum-unread-count"]');
        const status = node.querySelector('[data-region="forum-unread-status"]');
        if (badge) {
            badge.hidden = true;
            badge.textContent = '';
        }
        if (status) {
            status.hidden = true;
            status.textContent = '';
        }
    };

    /**
     * Remove references to cards replaced by My courses pagination.
     */
    const prune = () => {
        for (const node of nodes) {
            if (isAttached(node)) {
                continue;
            }
            nodes.delete(node);
            if (visibilityObserver) {
                visibilityObserver.unobserve(node);
            }
            const courseid = Number(node.dataset.courseId);
            const matches = byCourse.get(courseid);
            if (matches) {
                matches.delete(node);
                if (!matches.size) {
                    byCourse.delete(courseid);
                    eligible.delete(courseid);
                    queued.delete(courseid);
                }
            }
        }
    };

    /**
     * Send at most two simultaneous requests, each containing up to 96 courses.
     */
    const pump = () => {
        prune();
        while (active < MAX_CONCURRENT && queued.size) {
            const batch = [];
            for (const courseid of queued) {
                queued.delete(courseid);
                if (cache.has(courseid) || requesting.has(courseid) ||
                        !eligible.has(courseid) || !byCourse.has(courseid)) {
                    continue;
                }
                batch.push(courseid);
                if (batch.length === MAX_BATCH) {
                    break;
                }
            }
            if (!batch.length) {
                break;
            }

            const requestGeneration = generation;
            active++;
            batch.forEach(id => requesting.add(id));
            Promise.resolve().then(() => Ajax.call([{
                methodname: 'mod_forum_get_course_unread_counts',
                args: {courseids: batch},
            }])[0]).then(response => {
                if (requestGeneration !== generation) {
                    return;
                }
                const counts = new Map(response.courses.map(entry => [Number(entry.courseid), entry]));
                for (const courseid of batch) {
                    if (!counts.has(courseid)) {
                        continue;
                    }
                    const value = counts.get(courseid);
                    cache.set(courseid, value);
                    const isCurrent = node => requestGeneration === generation &&
                        isAttached(node) && Number(node.dataset.courseId) === courseid;
                    for (const node of currentNodes(courseid)) {
                        display(node, value, isCurrent).catch(Notification.exception);
                    }
                }
            }).catch(error => {
                if (requestGeneration === generation) {
                    Notification.exception(error);
                }
            }).finally(() => {
                if (requestGeneration === generation) {
                    active--;
                    batch.forEach(id => requesting.delete(id));
                    schedulePump();
                }
            });
        }
    };

    const schedulePump = () => {
        if (pumpPending) {
            return;
        }
        pumpPending = true;
        Promise.resolve().then(() => {
            pumpPending = false;
            pump();
        });
    };

    const markEligible = node => {
        if (!isAttached(node)) {
            return;
        }
        const courseid = Number(node.dataset.courseId);
        if (!Number.isSafeInteger(courseid) || courseid <= 0) {
            return;
        }
        eligible.add(courseid);
        if (cache.has(courseid)) {
            const value = cache.get(courseid);
            const isCurrent = current => isAttached(current) &&
                Number(current.dataset.courseId) === courseid && cache.get(courseid) === value;
            display(node, value, isCurrent).catch(Notification.exception);
        } else if (!requesting.has(courseid)) {
            queued.add(courseid);
            schedulePump();
        }
    };

    const visibilityObserver = typeof IntersectionObserver === 'function'
        ? new IntersectionObserver(entries => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    markEligible(entry.target);
                }
            }
        }, {rootMargin: ROOT_MARGIN})
        : null;

    const scan = () => {
        prune();
        for (const node of container.querySelectorAll(SELECTOR)) {
            if (nodes.has(node)) {
                continue;
            }
            const courseid = Number(node.dataset.courseId);
            if (!Number.isSafeInteger(courseid) || courseid <= 0) {
                continue;
            }
            nodes.add(node);
            if (!byCourse.has(courseid)) {
                byCourse.set(courseid, new Set());
            }
            byCourse.get(courseid).add(node);
            if (cache.has(courseid)) {
                const value = cache.get(courseid);
                const current = candidate => isAttached(candidate) && nodes.has(candidate) &&
                    cache.get(courseid) === value;
                display(node, value, current).catch(Notification.exception);
            } else if (visibilityObserver) {
                visibilityObserver.observe(node);
            } else {
                // Older browsers retain batched loading without requiring an
                // IntersectionObserver polyfill.
                markEligible(node);
            }
        }
    };

    const scheduleScan = () => {
        if (scanPending) {
            return;
        }
        scanPending = true;
        Promise.resolve().then(() => {
            scanPending = false;
            scan();
        });
    };

    // Updating a badge also mutates text nodes. Only rescan when a course
    // summary (or its ancestor) is actually added or removed.
    const affectsSummary = node => node.nodeType === 1 &&
        ((node.matches && node.matches(SELECTOR)) ||
            (node.querySelector && node.querySelector(SELECTOR)));
    const mutationObserver = new MutationObserver(records => {
        if (records.some(record => [...record.addedNodes, ...record.removedNodes].some(affectsSummary))) {
            scheduleScan();
        }
    });
    mutationObserver.observe(container, {childList: true, subtree: true});
    scheduleScan();

    // Returning from a discussion may change read counts and even permissions.
    // Drop all page-local results and ignore any pending older requests.
    window.addEventListener('pageshow', event => {
        if (!event.persisted) {
            return;
        }
        generation++;
        active = 0;
        eligible.clear();
        queued.clear();
        requesting.clear();
        cache.clear();
        prune();
        for (const node of nodes) {
            clearCount(node);
            if (visibilityObserver) {
                visibilityObserver.unobserve(node);
                visibilityObserver.observe(node);
            } else {
                markEligible(node);
            }
        }
    });
};
