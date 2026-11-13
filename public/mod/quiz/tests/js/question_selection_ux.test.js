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

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

/**
 * Load the production AMD build with minimal native Moodle dependencies.
 *
 * @param {string} name AMD file name.
 * @param {object} dependencies Stubs keyed by AMD dependency.
 * @param {object} globals Additional VM globals.
 * @returns {object} AMD exports.
 */
function loadAmd(name, dependencies, globals = {}) {
    const filepath = path.join(__dirname, '..', '..', 'amd', 'build', name + '.min.js');
    const code = fs.readFileSync(filepath, 'utf8');
    let moduleExports;
    const environment = {
        ...globals,
        console,
        define(moduleName, required, factory) {
            assert.equal(moduleName, 'mod_quiz/' + name);
            const exports = {};
            const values = required.map(dep => dep === 'exports' ? exports : dependencies[dep]);
            factory(...values);
            moduleExports = exports;
        },
    };
    vm.runInNewContext(code, environment, {filename: filepath});
    return moduleExports.default;
}

class BaseModal {
    constructor(root) {
        this.root = root;
    }
    static registerModalType() {}
    getBody() {
        return this.root;
    }
    getContextId() {
        return 10;
    }
    hideFooter() {}
    setTitle() {}
    setBody(body) {
        this.body = body;
    }
}

function makeRandomStubs(request) {
    const notifications = [];
    let pendingResolved = 0;
    const dependencies = {
        jquery: {default: {}},
        './add_question_modal': {default: BaseModal},
        'core/notification': {exception(error) { notifications.push(error); }},
        'core/fragment': {},
        'core/templates': {},
        'core_form/changechecker': {},
        'core/ajax': {call() { return [request()]; }},
        'core/pending': {default: class {
            resolve() {
                pendingResolved++;
            }
        }},
        'core_question/bank_switcher': {default: class {}},
    };
    return {dependencies, notifications, getPendingResolved: () => pendingResolved};
}

function makeForm() {
    let submitCount = 0;
    const buttons = [{disabled: false}, {disabled: false}];
    const status = {dataset: {busyMessage: 'Saving...'}, textContent: ''};
    const message = {value: ''};
    const form = {
        querySelectorAll() { return buttons; },
        querySelector(selector) {
            if (selector.includes('quiz-selection-status')) {
                return status;
            }
            if (selector.includes('message')) {
                return message;
            }
            return null;
        },
        submit() {
            submitCount++;
        },
    };
    return {form, buttons, status, message, getSubmitCount: () => submitCount};
}

function randomInstance(Class, form) {
    const modal = Object.create(Class.prototype);
    modal.submitting = false;
    modal.getBody = () => ({find: () => ({get: () => form})});
    return modal;
}

test('random question service sends only once when clicked repeatedly', async () => {
    let resolveRequest;
    let calls = 0;
    const request = () => {
        calls++;
        return new Promise(resolve => { resolveRequest = resolve; });
    };
    const stubs = makeRandomStubs(request);
    const Modal = loadAmd('modal_add_random_question', stubs.dependencies);
    const form = makeForm();
    const modal = randomInstance(Modal, form.form);
    const first = modal.addQuestions(30, 2, 1, '{"filter":{}}', '', '');
    const second = modal.addQuestions(30, 2, 1, '{"filter":{}}', '', '');
    assert.equal(calls, 1);
    assert.equal(modal.submitting, true);
    assert.equal(form.status.textContent, 'Saving...');
    assert.ok(form.buttons.every(button => button.disabled));
    resolveRequest({message: 'Added'});
    await Promise.all([first, second]);
    assert.equal(form.message.value, 'Added');
    assert.equal(form.getSubmitCount(), 1);
    assert.equal(stubs.notifications.length, 0);
});

test('random service failure releases the lock and allows a retry', async () => {
    let calls = 0;
    const stubs = makeRandomStubs(() => {
        calls++;
        if (calls === 1) {
            return Promise.reject(new Error('Denied'));
        }
        return Promise.resolve({message: 'Updated'});
    });
    const Modal = loadAmd('modal_add_random_question', stubs.dependencies);
    const form = makeForm();
    const modal = randomInstance(Modal, form.form);

    await modal.updateFilterCondition(30, 8, '{"filter":{}}');
    assert.equal(modal.submitting, false);
    assert.equal(stubs.getPendingResolved(), 1);
    assert.equal(form.status.textContent, '');
    assert.ok(form.buttons.every(button => !button.disabled));
    assert.equal(stubs.notifications.length, 1);

    await modal.updateFilterCondition(30, 8, '{"filter":{}}');
    assert.equal(calls, 2);
    assert.equal(form.message.value, 'Updated');
    assert.equal(form.getSubmitCount(), 1);
});

function fakeDeferred() {
    let status = 'pending';
    let content = null;
    return {
        state() { return status; },
        resolve(html, js) {
            status = 'resolved';
            content = {html, js};
        },
        getContent() { return content; },
        promise() { return this; },
    };
}

function makeBankStubs() {
    const storage = new Map();
    const notifications = [];
    const jq = {Deferred: fakeDeferred};
    const dependencies = {
        jquery: {default: jq},
        './add_question_modal': {default: BaseModal},
        'core/fragment': {},
        'core_form/changechecker': {},
        'core/modal_events': {},
        'core/notification': {exception(error) { notifications.push(error); }},
        'core_question/bank_switcher': {default: class {}},
    };
    const globals = {
        window: {
            sessionStorage: {
                getItem(key) { return storage.get(key) ?? null; },
                setItem(key, value) { storage.set(key, value); },
                removeItem(key) { storage.delete(key); },
            }
        }
    };
    return {dependencies, globals, storage, notifications};
}

function bankInstance(Class) {
    const modal = Object.create(Class.prototype);
    modal.originalTitle = 'Question bank';
    modal.bankCmId = 55;
    modal.quizCmId = 30;
    modal.getBody = () => ({
        attr() { return this; },
    });
    return modal;
}

test('only latest question bank fragment can replace the modal body', async () => {
    const stubs = makeBankStubs();
    const Modal = loadAmd('modal_quiz_question_bank', stubs.dependencies, stubs.globals);
    const modal = bankInstance(Modal);
    const pending = [];
    modal.bodyFragment = () => new Promise(resolve => pending.push(resolve));

    modal.reloadBodyContent('?bank=old');
    modal.reloadBodyContent('?bank=new');
    assert.equal(pending.length, 2);
    pending[1]('<div>new bank</div>');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(modal.pendingBody.getContent().html, '<div>new bank</div>');
    pending[0]('<div>old bank</div>');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(modal.pendingBody.getContent().html, '<div>new bank</div>');
});

test('inaccessible remembered bank is forgotten and reloaded from the quiz', async () => {
    const stubs = makeBankStubs();
    stubs.storage.set('mod_quiz:lastBankCmid:30', '77');
    const Modal = loadAmd('modal_quiz_question_bank', stubs.dependencies, stubs.globals);
    const modal = bankInstance(Modal);
    const loaded = [];
    modal.bodyFragment = (query, bank) => {
        loaded.push(bank);
        if (String(bank) === '77') {
            return Promise.reject({errorcode: 'nopermissions'});
        }
        return Promise.resolve('<div>quiz bank</div>');
    };
    modal.reloadBodyContent('?q=1');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(stubs.storage.has('mod_quiz:lastBankCmid:30'), false);
    assert.deepEqual(loaded, ['77', 55]);
    assert.equal(modal.pendingBody.getContent().html, '<div>quiz bank</div>');
});

test('native selection counter counts only checked q-ID inputs', () => {
    const stubs = makeBankStubs();
    const Modal = loadAmd('modal_quiz_question_bank', stubs.dependencies, stubs.globals);
    const modal = bankInstance(Modal);
    const counter = {dataset: {countTemplate: 'Selected on this page: %COUNT%'}, textContent: ''};
    const checked = [{name: 'q21'}, {name: 'q33'}, {name: 'qbank-toggle-all'}, {name: 'filter'}];
    const form = {querySelectorAll() { return checked; }};
    modal.getBody = () => ({
        get() {
            return {querySelector(selector) {
                return selector === 'form#questionsubmit' ? form : counter;
            }};
        }
    });

    modal.updateSelectedCount();
    assert.equal(counter.textContent, 'Selected on this page: 2');
    checked.splice(0, 2);
    modal.updateSelectedCount();
    assert.equal(counter.textContent, 'Selected on this page: 0');
});

test('remembered question bank is scoped to one quiz module', async () => {
    const stubs = makeBankStubs();
    stubs.storage.set('mod_quiz:lastBankCmid:31', '77');
    const Modal = loadAmd('modal_quiz_question_bank', stubs.dependencies, stubs.globals);
    const modal = bankInstance(Modal);
    const banks = [];
    modal.bodyFragment = (query, cmid) => {
        banks.push(cmid);
        return Promise.resolve('<div>own quiz bank</div>');
    };

    modal.reloadBodyContent('?q=1');
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(banks, [55]);
    assert.equal(stubs.storage.get('mod_quiz:lastBankCmid:31'), '77');
});

test('editing one random slot does not remove new-category tab for subsequent additions', () => {
    const stubs = makeRandomStubs(() => Promise.resolve({message: 'ok'}));
    const handlers = [];
    const Modal = loadAmd('modal_add_random_question', stubs.dependencies, {
        document: {addEventListener(type, callback) { handlers.push(callback); }},
    });
    const configs = [];
    Modal.create = (config) => { configs.push(config); };
    Modal.init(10, 20, '7,10', '/mod/quiz/edit.php', 30, true, 1);

    const click = (dataset) => handlers[0]({
        target: {closest() { return {dataset}; }},
        preventDefault() {},
    });
    click({slotid: '42', header: 'Edit random filters'});
    click({header: 'Add random question', addonpage: '1'});

    assert.equal(configs[0].showNewCategory, false);
    assert.equal(configs[0].templateContext.hidden, false);
    assert.equal(configs[1].showNewCategory, true);
    assert.equal(configs[1].templateContext.hidden, true);
});

test('production random selection build uses modal-scoped form fields', () => {
    const filepath = path.join(__dirname, '..', '..', 'amd', 'build',
        'modal_add_random_question.min.js');
    const code = fs.readFileSync(filepath, 'utf8');
    assert.ok(code.includes('this.getBody().get(0).querySelector(SELECTORS.FILTER_CONDITION_ELEMENT)'));
    assert.ok(!code.includes('document.querySelector(SELECTORS.SELECT_NUMBER_TO_ADD)'));
    assert.ok(!code.includes('setAttribute(\'name\', this.quizCmId)'));
});
