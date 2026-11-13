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
import * as Notification from 'core/notification';
import {get_string as getString} from 'core/str';

/**
 * Native discussion and unified-feed controls for accepted solutions.
 * DOM state is only a hint; every write is authorised by solution_manager.
 *
 * @module mod_forum/solutions
 */

const send = async(discussionid, postid) => Ajax.call([{
    methodname: 'mod_forum_set_accepted_solution',
    args: {discussionid, postid},
}])[0];

const read = async discussionid => Ajax.call([{
    methodname: 'mod_forum_get_solution_state',
    args: {discussionid},
}])[0];

/**
 * Add acceptance controls to native forum posts after the core renderer.
 *
 * @param {number} discussionid Discussion currently rendered by Moodle.
 */
export const initDiscussion = async discussionid => {
    const id = Number(discussionid);
    if (!Number.isSafeInteger(id) || id <= 0) {
        return;
    }
    let state;
    try {
        state = await read(id);
    } catch (error) {
        Notification.exception(error);
        return;
    }
    if (!state.enabled) {
        return;
    }

    const posts = [...document.querySelectorAll('[data-region="post"][data-post-id]')];
    const roots = posts.length ? posts : [...document.querySelectorAll('.forumpost[data-post-id]')];
    let livePosts = [...new Map(roots.map(post => [Number(post.dataset.postId), post])).values()];

    const update = async next => {
        state = next;
        for (const post of livePosts) {
            const postid = Number(post.dataset.postId);
            const accepted = postid === state.postid;
            post.classList.toggle('forum-solution-accepted', accepted);
            const panel = post.querySelector('[data-region="forum-solution-actions"]');
            if (!panel) {
                continue;
            }
            panel.replaceChildren();
            if (accepted) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-success me-2';
                badge.textContent = await getString('acceptedsolution', 'mod_forum');
                panel.append(badge);
            }
            if (state.canmanage && !post.classList.contains('firstpost') &&
                    !post.querySelector('.firstpost')) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-outline-primary';
                button.textContent = await getString(accepted ? 'removesolution' :
                    (state.postid ? 'replacesolution' : 'marksolution'), 'mod_forum');
                button.setAttribute('aria-pressed', String(accepted));
                button.addEventListener('click', async() => {
                    if (button.disabled) {
                        return;
                    }
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                    try {
                        const result = await send(id, accepted ? 0 : postid);
                        await update(result);
                        const refreshed = post.querySelector('[data-region="forum-solution-actions"] button');
                        if (refreshed) {
                            refreshed.focus();
                        }
                    } catch (error) {
                        Notification.exception(error);
                        button.disabled = false;
                        button.removeAttribute('aria-busy');
                    }
                });
                panel.append(button);
            }
        }
        const header = document.querySelector('[data-region="forum-solution-header"]');
        if (header) {
            header.replaceChildren();
            if (!state.postid) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-secondary';
                badge.textContent = await getString('solutionnotaccepted', 'mod_forum');
                header.append(badge);
            }
            if (state.postid && state.url) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-success me-2';
                badge.textContent = await getString('acceptedsolution', 'mod_forum');
                header.append(badge);
                const link = document.createElement('a');
                link.href = state.url;
                link.className = 'btn btn-outline-success';
                link.textContent = await getString('viewsolution', 'mod_forum');
                header.append(link);
            }
        }
    };
    const host = document.querySelector('.discussionheader') || document.querySelector('[data-region="discussion"]');
    if (host && !document.querySelector('[data-region="forum-solution-header"]')) {
        const region = document.createElement('div');
        region.dataset.region = 'forum-solution-header';
        region.className = 'mb-3';
        host.after(region);
    }
    const ensurePanels = async() => {
        const current = [...document.querySelectorAll('[data-region="post"][data-post-id]')];
        const all = current.length ? current : [...document.querySelectorAll('.forumpost[data-post-id]')];
        livePosts = [...new Map(all.map(post => [Number(post.dataset.postId), post])).values()];
        for (const post of livePosts) {
            if (post.querySelector('[data-region="forum-solution-actions"]')) {
                continue;
            }
            const area = post.querySelector('[data-region="post-actions-container"]') || post;
            const panel = document.createElement('div');
            panel.dataset.region = 'forum-solution-actions';
            panel.className = 'forum-solution-actions mt-2';
            panel.setAttribute('role', 'group');
            panel.setAttribute('aria-label', await getString('acceptedsolution', 'mod_forum'));
            area.after(panel);
        }
    };
    await ensurePanels();
    await update(state);

    // Nested and progressively inserted replies are not always present when
    // the initial Moodle discussion renderer finishes.
    const observer = new MutationObserver(async mutations => {
        const hasNewPosts = mutations.some(mutation => [...mutation.addedNodes].some(node =>
            node.nodeType === Node.ELEMENT_NODE &&
            (node.matches?.('[data-region="post"][data-post-id], .forumpost[data-post-id]') ||
            node.querySelector?.('[data-region="post"][data-post-id], .forumpost[data-post-id]'))));
        if (!hasNewPosts) {
            return;
        }
        observer.disconnect();
        try {
            await ensurePanels();
            await update(state);
        } finally {
            observer.observe(document.body, {childList: true, subtree: true});
        }
    });
    observer.observe(document.body, {childList: true, subtree: true});
};

/**
 * Use delegation because the feed replaces posts during filtering/pagination.
 *
 * @param {HTMLElement} root Root feed container.
 * @param {Function} reload Refresh without modifying the selected feed filters.
 */
export const initFeed = (root, reload) => {
    const list = root.querySelector('[data-region="feed-items"]');
    const status = root.querySelector('[data-region="feed-status"]');
    list.addEventListener('click', async event => {
        const button = event.target.closest('[data-action="solution-set"]');
        if (!button || button.disabled) {
            return;
        }
        const discussionid = Number(button.dataset.discussionId);
        const postid = Number(button.dataset.targetPostId);
        if (!Number.isSafeInteger(discussionid) || discussionid <= 0 ||
                !Number.isSafeInteger(postid) || postid < 0) {
            return;
        }
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            await send(discussionid, postid);
            await reload();
            status.textContent = await getString(postid ? 'solutionsaved' : 'solutionremoved', 'mod_forum');
            status.setAttribute('tabindex', '-1');
            status.focus();
        } catch (error) {
            Notification.exception(error);
            if (button.isConnected) {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
            status.textContent = await getString('solutionerror', 'mod_forum');
        }
    });
};
