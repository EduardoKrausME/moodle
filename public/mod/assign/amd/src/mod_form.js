// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Event handler to update derived form fields.
 *
 * @module     mod_assign/mod_form
 * @copyright  2026 Catalyst IT Australia Pty Ltd
 * @author     Benjamin Walker <benjaminwalker@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const Selectors = {
    form: 'form[action*="modedit.php"]',
    fields: {
        markerCount: '#id_markercount',
        optionalMarkerCount: '#id_optionalmarkercount',
    },
    derived: {
        totalMarkerCount: 'input[name="totalmarkercount"]',
    },
};

/**
 * Add a keyboard-accessible index using the native Moodle fieldset headers.
 * No fields are moved and Moodle owns the collapse and validation behaviour.
 *
 * @param {HTMLFormElement} form Assignment edit form.
 */
const initSectionNavigation = (form) => {
    const navigation = form.querySelector('[data-region="assign-settings-navigation"]');
    const list = navigation?.querySelector('[data-region="assign-settings-section-list"]');
    if (!list) {
        return;
    }

    const sections = Array.from(form.querySelectorAll('fieldset[id]')).filter(fieldset =>
        fieldset.querySelector(':scope > legend') && !fieldset.parentElement.closest('fieldset')
    );
    const fragment = document.createDocumentFragment();
    sections.forEach(fieldset => {
        const legend = fieldset.querySelector(':scope > legend');
        const title = legend.textContent.replace(/\s+/g, ' ').trim();
        if (!title) {
            return;
        }
        const item = document.createElement('li');
        const link = document.createElement('a');
        link.href = '#' + encodeURIComponent(fieldset.id);
        link.className = 'assign-settings-section-link';
        link.textContent = title;
        link.addEventListener('click', event => {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }
            event.preventDefault();
            // The native Moodle toggle is a sibling of the visually hidden legend.
            const toggle = fieldset.querySelector('a[data-bs-toggle="collapse"][aria-expanded]');
            const collapsed = fieldset.classList.contains('collapsed') ||
                toggle?.getAttribute('aria-expanded') === 'false';
            if (collapsed && toggle) {
                toggle.click();
            }
            fieldset.scrollIntoView({block: 'start'});
            if (toggle) {
                toggle.focus({preventScroll: true});
            } else {
                legend.setAttribute('tabindex', '-1');
                legend.focus({preventScroll: true});
            }
        });
        item.appendChild(link);
        fragment.appendChild(item);
    });

    if (fragment.childNodes.length) {
        list.appendChild(fragment);
        navigation.hidden = false;
    }
};

/**
 * Update derived hidden fields in real time.
 */
export const init = () => {
    const form = document.querySelector(Selectors.form);
    if (!form) {
        return;
    }

    form.classList.add('mod-assign-settings-form');
    initSectionNavigation(form);

    const updateDerivedFields = () => {
        const totalMarkerCount = form.querySelector(Selectors.derived.totalMarkerCount);
        if (totalMarkerCount) {
            const markerCount = Number(form.querySelector(Selectors.fields.markerCount)?.value ?? 1);
            const optionalMarkerCount = Number(form.querySelector(Selectors.fields.optionalMarkerCount)?.value ?? 0);

            totalMarkerCount.value = markerCount + optionalMarkerCount;
            totalMarkerCount.dispatchEvent(new Event('change', {bubbles: true}));
        }
    };

    // Run once on load.
    updateDerivedFields();

    // Run on any relevant change.
    Object.values(Selectors.fields).forEach(selector => {
        form.querySelector(selector)?.addEventListener('change', updateDerivedFields);
    });
};
