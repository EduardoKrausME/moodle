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

/**
 * Progressive enhancement for the server-rendered teacher queue.
 *
 * @module mod_forum/unanswered
 */
export const init = () => {
    const root = document.querySelector('[data-region="forum-unanswered"]');
    if (!root) {
        return;
    }
    const form = root.querySelector('[data-region="unanswered-filters"]');
    const list = root.querySelector('[data-region="unanswered-items"]');
    const feedback = root.querySelector('[data-region="unanswered-feedback"]');
    const more = root.querySelector('[data-action="unanswered-more"]');
    const retry = root.querySelector('[data-action="unanswered-retry"]');
    const course = form.querySelector('[name="courseid"]');
    const forum = form.querySelector('[name="forumid"]');
    const group = form.querySelector('[name="groupid"]');
    const status = form.querySelector('[name="status"]');
    const sort = form.querySelector('[name="sort"]');
    const keys = ['unanswered', 'awaiting', 'accepted', 'answered'];
    const state = {
        generation: 0,
        loading: false,
        cursor: root.dataset.cursor || '',
        hasMore: root.dataset.hasMore === '1',
        counts: {},
    };
    for (const key of keys) {
        state.counts[key] = Number(root.querySelector('[data-region="unanswered-count-' + key + '"]').textContent) || 0;
    }
    const filters = () => ({
        courseid: Number(course.value) || 0,
        forumid: Number(forum.value) || 0,
        groupid: Number(group.disabled ? 0 : group.value) || 0,
        status: status.value,
        sort: sort.value,
    });
    const urlForFilters = () => {
        const url = new URL(window.location.href);
        url.search = new URLSearchParams(filters()).toString();
        return url;
    };
    const updateCounts = () => {
        for (const key of keys) {
            root.querySelector('[data-region="unanswered-count-' + key + '"]').textContent = state.counts[key];
        }
    };
    const load = async(reset = false) => {
        if (state.loading && !reset || !reset && !state.hasMore) {
            return;
        }
        if (reset) {
            state.generation++;
            state.cursor = '';
            state.hasMore = true;
            state.counts = {unanswered: 0, awaiting: 0, accepted: 0, answered: 0};
            list.replaceChildren();
            updateCounts();
        }
        const generation = state.generation;
        const cursor = state.cursor;
        const previous = list.children.length;
        state.loading = true;
        root.setAttribute('aria-busy', 'true');
        more.disabled = true;
        retry.disabled = true;
        try {
            feedback.textContent = await getString('unansweredloading', 'mod_forum');
            const response = await Ajax.call([{
                methodname: 'mod_forum_get_unanswered_discussions',
                args: {...filters(), cursor},
            }])[0];
            if (generation !== state.generation) {
                return;
            }
            if (response.items.length) {
                const [html, js] = await Templates.render('mod_forum/unanswered_items', response);
                if (generation !== state.generation) {
                    return;
                }
                const detached = document.createElement('div');
                await Templates.appendNodeContents($(detached), html, js);
                if (generation !== state.generation) {
                    return;
                }
                list.append(...detached.childNodes);
            }
            for (const key of keys) {
                state.counts[key] += Number(response.counts[key]) || 0;
            }
            updateCounts();
            state.hasMore = Boolean(response.hasmore && response.nextcursor &&
                response.nextcursor !== cursor);
            state.cursor = state.hasMore ? response.nextcursor : '';
            more.hidden = !state.hasMore;
            retry.hidden = true;
            feedback.textContent = list.children.length === 0 && !state.hasMore ?
                await getString('unansweredempty', 'mod_forum') :
                await getString('unansweredpagecount', 'mod_forum', list.children.length);
            if (generation !== state.generation) {
                return;
            }
            if (!reset && previous) {
                const first = list.children[previous];
                first?.querySelector('a')?.focus();
            } else if (reset) {
                feedback.focus();
            }
        } catch (error) {
            if (generation === state.generation) {
                feedback.textContent = await getString('unanswerederror', 'mod_forum');
                retry.hidden = false;
                more.hidden = true;
                Notification.exception(error);
            }
        } finally {
            if (generation === state.generation) {
                state.loading = false;
                root.removeAttribute('aria-busy');
                more.disabled = false;
                retry.disabled = false;
            }
        }
    };
    // A course change needs fresh authorised forum and group choices from PHP.
    course.addEventListener('change', () => {
        forum.value = '0';
        group.value = '0';
        window.location.assign(urlForFilters().toString());
    });
    // Group choices may differ between forums in the same course.
    forum.addEventListener('change', () => {
        group.value = '0';
        window.location.assign(urlForFilters().toString());
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        window.history.pushState({forumUnanswered: true}, '', urlForFilters().toString());
        load(true);
    });
    more.addEventListener('click', () => load());
    retry.addEventListener('click', () => load());
    window.addEventListener('popstate', () => {
        const params = new URL(window.location.href).searchParams;
        if (Number(params.get('courseid') || 0) !== Number(course.value) ||
                Number(params.get('forumid') || 0) !== Number(forum.value)) {
            window.location.reload();
            return;
        }
        for (const input of [group, status, sort]) {
            const defaults = {groupid: '0', status: 'all', sort: 'oldest'};
            const value = params.get(input.name) || defaults[input.name];
            if ([...input.options].some(option => option.value === value)) {
                input.value = value;
            }
        }
        load(true);
    });
};
