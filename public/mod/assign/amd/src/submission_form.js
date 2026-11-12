// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Progressive UI improvements for Moodle's native assignment submission form.
 *
 * No new inputs or submission handlers are introduced. Moodle's editor,
 * filemanager, validation, autosave and save buttons remain authoritative.
 *
 * @module     mod_assign/submission_form
 * @copyright  2026 Eduardo Kraus
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Find only the native submission form, not the activity configuration form.
 *
 * @returns {HTMLFormElement|null}
 */
const getSubmissionForm = () => {
    const input = document.querySelector('input[name="action"][value="savesubmission"]');
    return input?.closest('form') || null;
};

/**
 * Add styling hooks to the existing filemanager and online text field wrappers.
 *
 * @param {HTMLFormElement} form Native Moodle form.
 */
const markNativeFields = form => {
    const fields = [
        ['files_filemanager', 'filemanager', 'assign-submission-file-field'],
        ['onlinetext_editor', 'editor', 'assign-submission-text-field'],
    ];
    fields.forEach(([id, type, className]) => {
        const element = form.querySelector('[id="' + 'id_' + id + '"]') ||
            form.querySelector('.felement[data-fieldtype="' + type + '"]');
        element?.closest('.fitem')?.classList.add(className);
    });
};

/**
 * A non-authoritative word count. PHP's count_words() is the final validator.
 *
 * @param {HTMLFormElement} form Native Moodle form.
 */
const setupWordCount = form => {
    const region = form.querySelector('[data-region="assign-submission-wordcount"]');
    const output = region?.querySelector('[data-region="assign-submission-wordcount-value"]');
    const textarea = form.querySelector('textarea[name="onlinetext_editor[text]"]') ||
        form.querySelector('textarea[id="id_onlinetext_editor"]');
    if (!region || !output || !textarea) {
        return;
    }

    const editorObject = () => {
        if (typeof window === 'undefined' || !window.tinymce || !textarea.id) {
            return null;
        }
        return window.tinymce.get(textarea.id);
    };
    const update = () => {
        const editor = editorObject();
        // Never call save() or trigger input events on the native editor.
        const value = editor ? editor.getContent({format: 'text'}) : textarea.value;
        const plain = String(value || '').replace(/<[^>]*>/g, ' ').replace(/&[a-z0-9#]+;/gi, ' ');
        output.textContent = String(plain.trim() ? plain.trim().split(/\s+/u).length : 0);
    };
    let attached = null;
    const connectEditor = () => {
        const editor = editorObject();
        if (!editor || editor === attached) {
            return;
        }
        attached = editor;
        editor.on('input change keyup SetContent Undo Redo', update);
        update();
    };

    region.hidden = false;
    textarea.addEventListener('input', update);
    textarea.addEventListener('change', update);
    form.addEventListener('focusin', connectEditor);
    // The TinyMCE instance may initialise after the AMD module; listen for its
    // insertion without creating another autosave, polling loop or form control.
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(() => {
            connectEditor();
            if (attached) {
                observer.disconnect();
            }
        });
        observer.observe(textarea.closest('.fitem') || form, {childList: true, subtree: true});
        setTimeout(() => observer.disconnect(), 10000);
    }
    connectEditor();
    update();
};

/**
 * Bring focus to the first native form validation error when it exists.
 *
 * @param {HTMLFormElement} form Native Moodle form.
 */
const focusFirstError = form => {
    const error = form.querySelector('[aria-invalid="true"], .fitem .invalid-feedback, .fitem .error');
    if (!error) {
        return;
    }
    const row = error.closest('.fitem');
    const control = error.matches('[aria-invalid="true"]') ? error :
        row?.querySelector('[aria-invalid="true"], input:not([type="hidden"]), textarea, [role="textbox"], button');
    if (control && typeof control.focus === 'function') {
        control.focus({preventScroll: true});
        control.scrollIntoView({block: 'center'});
    }
};

/**
 * Initialise presentation only. With JS disabled, the native form is unchanged.
 */
export const init = () => {
    const form = getSubmissionForm();
    if (!form) {
        return;
    }

    form.classList.add('assign-modern-submission-form');
    markNativeFields(form);
    setupWordCount(form);
    focusFirstError(form);
};
