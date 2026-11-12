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
//
// @module mod_book/courseindex

/**
 * Add the chapters to the Book activity in the course index, without moving the
 * original TOC. The original remains available if the course index is absent.
 *
 * @param {Object} config Information supplied by mod/book/view.php.
 * @returns {boolean} Whether an activity was found and mounted.
 */
const mount = config => {
    const index = document.getElementById('course-index');
    const activity = index?.querySelector('#course-index-cm-' + config.cmid);
    if (!activity) {
        return false;
    }
    if (activity.querySelector(':scope > .book-courseindex-tree')) {
        return true;
    }

    const activitylink = activity.querySelector(':scope > [data-for="cm_name"]');
    if (!activitylink) {
        return false;
    }

    activity.insertAdjacentHTML('beforeend', config.html);
    const tree = activity.querySelector(':scope > .book-courseindex-tree');
    const toggle = activity.querySelector(':scope > .book-courseindex-toggle');
    if (!tree || !toggle) {
        return false;
    }

    const activitytitle = activitylink.textContent.trim();
    toggle.setAttribute('aria-label', config.collapselabel + ': ' + activitytitle);
    toggle.addEventListener('click', event => {
        event.stopPropagation();
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        tree.hidden = isExpanded;
        toggle.setAttribute('aria-expanded', String(!isExpanded));
        toggle.setAttribute('aria-label',
            (isExpanded ? config.expandlabel : config.collapselabel) + ': ' + activitytitle);
    });
    toggle.addEventListener('keydown', event => event.stopPropagation());

    // Prevent the Moodle course tree from consuming the chapter links' keyboard and click events.
    tree.addEventListener('click', event => event.stopPropagation());
    tree.addEventListener('keydown', event => {
        if (event.key !== 'Escape' && event.key !== 'Tab') {
            event.stopPropagation();
        }
    });

    activity.classList.add('book-courseindex-parent');
    return true;
};

/**
 * Keep the Book chapters mounted through reactive course-index rerenders.
 * Unlike observing only the first course-index element, this also handles the
 * element being replaced and restores the standard TOC if the index disappears.
 *
 * @param {Object} config Chapter template HTML and localized labels.
 */
export const init = config => {
    if (!config?.html) {
        return;
    }

    const sync = () => {
        const inserted = mount(config);
        document.body.classList.toggle('mod-book-courseindex-active',
            inserted && Boolean(config.hideoriginaltoc));
    };

    sync();
    // Moodle course formats can replace the entire index on editing or chapter
    // updates. Observe the stable page root, not a detached index instance.
    const root = document.getElementById('page') ?? document.body;
    const observer = new MutationObserver(sync);
    observer.observe(root, {childList: true, subtree: true});
};
