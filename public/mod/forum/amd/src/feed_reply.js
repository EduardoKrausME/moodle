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

const MAX_MENTIONS = 5;

/**
 * Provide native forum replies, citations and visible-participant mentions
 * without changing the cross-course feed's navigation or permission model.
 *
 * @param {HTMLElement} root Existing forum feed.
 * @param {Function} published Refresh callback after a successful native post.
 */
export const init = (root, published) => {
    const list = root.querySelector('[data-region="feed-items"]');
    const drafts = new Map();
    let requestSequence = 0;

    const stateFor = postid => {
        if (!drafts.has(postid)) {
            drafts.set(postid, {message: '', quoted: false, mentionids: new Set()});
        }
        return drafts.get(postid);
    };

    const getCard = element => element.closest('[data-post-id]');
    const getId = card => Number(card && card.dataset.postId);
    const getPanel = card => card.querySelector('[data-region="feed-reply-panel"]');
    const setPanel = (card, expanded) => {
        const panel = getPanel(card);
        if (!panel) {
            return;
        }
        panel.hidden = !expanded;
        card.querySelectorAll('[aria-controls="' + panel.id + '"]').forEach(button => {
            button.setAttribute('aria-expanded', String(expanded));
        });
    };

    /**
     * Re-check the exact source post before displaying a quote or editor.
     *
     * @param {HTMLElement} button Triggering action.
     * @param {boolean} quoted Include a server-validated citation.
     * @param {boolean} mentioning Insert an @ search marker.
     */
    const open = async(button, quoted, mentioning) => {
        const card = getCard(button);
        const postid = getId(card);
        if (!Number.isSafeInteger(postid) || postid <= 0 || button.disabled) {
            return;
        }
        const panel = getPanel(card);
        if (!panel) {
            return;
        }
        const feedback = panel.querySelector('[data-region="feed-reply-feedback"]');
        const seq = ++requestSequence;
        button.disabled = true;
        try {
            feedback.textContent = await getString('recentfeedreplyloading', 'mod_forum');
            const context = await Ajax.call([{
                methodname: 'mod_forum_get_feed_reply_context',
                args: {postid},
            }])[0];
            if (!card.isConnected || seq !== requestSequence || !context.canreply) {
                return;
            }

            // Closing the old panel retains its draft in memory, not storage.
            list.querySelectorAll('[data-region="feed-reply-panel"]').forEach(other => {
                if (other !== panel && !other.hidden) {
                    setPanel(getCard(other), false);
                }
            });
            const draft = stateFor(postid);
            draft.quoted = quoted || draft.quoted;
            setPanel(card, true);
            const textarea = panel.querySelector('[data-region="feed-reply-message"]');
            textarea.value = draft.message;
            const quotation = panel.querySelector('[data-region="feed-reply-quote"]');
            quotation.hidden = !draft.quoted;
            if (draft.quoted) {
                // No HTML from the source post enters the DOM.
                quotation.textContent = context.author + ': "' + context.quote + '"';
            }
            feedback.textContent = '';
            if (mentioning && !textarea.value.endsWith('@')) {
                textarea.setRangeText('@', textarea.selectionStart, textarea.selectionEnd, 'end');
                draft.message = textarea.value;
            }
            textarea.focus();
        } catch (error) {
            if (card.isConnected) {
                feedback.textContent = await getString('recentfeedreplyfailed', 'mod_forum');
                Notification.exception(error);
            }
        } finally {
            if (card.isConnected) {
                button.disabled = false;
            }
        }
    };

    /**
     * Present safe results using DOM text nodes, never innerHTML.
     *
     * @param {HTMLElement} panel Open reply panel.
     * @param {Array<object>} users Filtered native discussion participants.
     * @param {number} seq Request generation.
     */
    const presentMentions = (panel, users, seq) => {
        if (!panel.isConnected || seq !== requestSequence) {
            return;
        }
        const choices = panel.querySelector('[data-region="feed-mention-suggestions"]');
        choices.replaceChildren();
        choices.hidden = !users.length;
        for (const user of users) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-secondary btn-sm me-2 mb-2';
            button.dataset.action = 'feed-mention-select';
            button.dataset.mentionUserId = String(user.id);
            button.dataset.mentionName = user.name;
            button.textContent = '@' + user.name;
            choices.append(button);
        }
    };

    const search = async(textarea) => {
        const card = getCard(textarea);
        const panel = getPanel(card);
        const caret = textarea.selectionStart;
        const match = textarea.value.slice(0, caret).match(/@([^\s@]{2,70})$/u);
        const seq = ++requestSequence;
        if (!match) {
            presentMentions(panel, [], seq);
            return;
        }
        try {
            const response = await Ajax.call([{
                methodname: 'mod_forum_search_feed_mentions',
                args: {postid: getId(card), query: match[1]},
            }])[0];
            presentMentions(panel, response.users, seq);
        } catch (error) {
            if (card.isConnected && seq === requestSequence) {
                presentMentions(panel, [], seq);
                Notification.exception(error);
            }
        }
    };

    const selectMention = button => {
        const card = getCard(button);
        const postid = getId(card);
        const panel = getPanel(card);
        const textarea = panel.querySelector('[data-region="feed-reply-message"]');
        const draft = stateFor(postid);
        const userId = Number(button.dataset.mentionUserId);
        if (!Number.isSafeInteger(userId) || userId <= 0 ||
                (draft.mentionids.size >= MAX_MENTIONS && !draft.mentionids.has(userId))) {
            return;
        }
        const caret = textarea.selectionStart;
        const match = textarea.value.slice(0, caret).match(/@([^\s@]*)$/u);
        const begin = match ? caret - match[0].length : caret;
        textarea.setRangeText('@' + button.dataset.mentionName + ' ', begin, caret, 'end');
        draft.mentionids.add(userId);
        draft.message = textarea.value;
        panel.querySelector('[data-region="feed-mention-suggestions"]').hidden = true;
        textarea.focus();
    };

    /**
     * Publish through the existing native add_discussion_post service wrapper.
     *
     * @param {HTMLFormElement} form Inline reply form.
     */
    const send = async(form) => {
        const card = getCard(form);
        const postid = getId(card);
        const draft = stateFor(postid);
        const textarea = form.querySelector('[data-region="feed-reply-message"]');
        const feedback = form.querySelector('[data-region="feed-reply-feedback"]');
        const submit = form.querySelector('[data-action="feed-reply-send"]');
        const message = textarea.value.trim();
        if (!message || submit.disabled) {
            feedback.textContent = await getString('recentfeedreplyrequired', 'mod_forum');
            textarea.focus();
            return;
        }
        draft.message = textarea.value;
        submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        try {
            const response = await Ajax.call([{
                methodname: 'mod_forum_reply_from_feed',
                args: {
                    postid, message, quote: draft.quoted,
                    mentionids: [...draft.mentionids],
                },
            }])[0];
            drafts.delete(postid);
            if (card.isConnected) {
                setPanel(card, false);
            }
            // Publication already succeeded. Refresh failure is not a post
            // failure, because asking the user to retry would duplicate it.
            try {
                await published(response);
            } catch (refresherror) {
                Notification.exception(refresherror);
            }
            return;
        } catch (error) {
            if (card.isConnected) {
                feedback.textContent = await getString('recentfeedreplyfailed', 'mod_forum');
                Notification.exception(error);
            }
        } finally {
            if (card.isConnected) {
                submit.disabled = false;
                form.removeAttribute('aria-busy');
            }
        }
    };

    list.addEventListener('click', event => {
        const button = event.target.closest('[data-action]');
        if (!button) {
            return;
        }
        switch (button.dataset.action) {
            case 'feed-reply-open':
                open(button, false, false);
                break;
            case 'feed-quote-open':
                open(button, true, false);
                break;
            case 'feed-mention-open':
                open(button, false, true);
                break;
            case 'feed-reply-cancel': {
                const card = getCard(button);
                setPanel(card, false);
                card.querySelector('[data-action="feed-reply-open"]').focus();
                break;
            }
            case 'feed-mention-select':
                selectMention(button);
                break;
            default:
                break;
        }
    });

    list.addEventListener('input', event => {
        const textarea = event.target.closest('[data-region="feed-reply-message"]');
        if (textarea) {
            const postid = getId(getCard(textarea));
            stateFor(postid).message = textarea.value;
            search(textarea);
        }
    });

    list.addEventListener('submit', event => {
        const form = event.target.closest('[data-action="feed-reply-form"]');
        if (form) {
            event.preventDefault();
            send(form);
        }
    });
};
