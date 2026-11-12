// Tests for the AMD module used by the Quiz final submission confirmation.
// Run: node --test public/mod/quiz/tests/js/submission_confirmation.test.js
'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const code = fs.readFileSync(path.resolve(__dirname,
    '../../amd/build/submission_confirmation.min.js'), 'utf8');
const source = fs.readFileSync(path.resolve(__dirname,
    '../../amd/src/submission_confirmation.js'), 'utf8');
const template = fs.readFileSync(path.resolve(__dirname,
    '../../templates/submission_confirmation.mustache'), 'utf8');

const flush = () => new Promise(resolve => setImmediate(resolve));
const event = () => ({prevented: false, preventDefault() { this.prevented = true; }});

function makeHarness() {
    const calls = {opened: 0, submitted: 0, rendered: [], focused: 0};
    let modalResolve;
    let modalReject;
    const submitAction = {
        listeners: {},
        disabled: false,
        addEventListener(name, cb) { this.listeners[name] = cb; },
        focus() { calls.focused++; },
    };
    const form = {
        listeners: {},
        dataset: {},
        attributes: {},
        addEventListener(name, cb) { this.listeners[name] = cb; },
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
        // Direct form.submit() intentionally bypasses the submit event.
        submit() { calls.submitted++; },
    };
    const document = {
        querySelector(selector) {
            if (selector === '.path-mod-quiz .btn-finishattempt button') {
                return submitAction;
            }
            if (selector === 'form#frm-finishattempt') {
                return form;
            }
            return null;
        },
    };
    const exports = {};
    const notification = {
        saveCancelPromise() {
            calls.opened++;
            return new Promise((resolve, reject) => {
                modalResolve = resolve;
                modalReject = reject;
            });
        },
    };
    const prefetch = {default: {prefetchStrings() {}, prefetchTemplate() {}}};
    const templates = {default: {
        render(name, data) {
            calls.rendered.push({name, data});
            return 'rendered';
        },
    }};
    const strings = {getString: key => key};
    vm.runInNewContext(code, {
        document,
        define(name, dependencies, execute) {
            assert.equal(name, 'mod_quiz/submission_confirmation');
            execute(exports, notification, prefetch, templates, strings);
        },
    });
    return {
        exports, submitAction, form, calls,
        resolveModal: () => modalResolve(),
        rejectModal: () => modalReject(new Error('cancel')),
    };
}

const progress = {
    total: 5, answered: 3, pending: 2, flagged: 1, navmethod: 'free',
};

test('Source and generated AMD module preserve native selectors and fields', () => {
    assert.match(source, /form#frm-finishattempt/);
    assert.match(code, /form#frm-finishattempt/);
    assert.match(template, /\{\{#str\}\} confirmclose, mod_quiz/);
    assert.doesNotMatch(template, /<form\b/i);
});

test('Progress counts and native free-navigation warning are independent', () => {
    const h = makeHarness();
    h.exports.init(progress);
    h.submitAction.listeners.click(event());
    assert.deepEqual(JSON.parse(JSON.stringify(h.calls.rendered[0].data)), {
        hasprogress: true,
        total: 5, answered: 3, pending: 2, flagged: 1,
        haspending: true, hasunanswered: true, totalunanswered: 2,
        issequential: false,
    });
});

test('Sequential mode provides pending counts without navigation warning', () => {
    const h = makeHarness();
    h.exports.init({...progress, navmethod: 'sequential'});
    h.submitAction.listeners.click(event());
    const context = h.calls.rendered[0].data;
    assert.equal(context.hasunanswered, false);
    assert.equal(context.issequential, true);
    assert.equal(context.pending, 2);
});

test('Double-click and pending modal never create two confirmations', async() => {
    const h = makeHarness();
    h.exports.init(progress);
    const click = event();
    h.submitAction.listeners.click(click);
    h.submitAction.listeners.click(event());
    assert.equal(click.prevented, true);
    assert.equal(h.calls.opened, 1);
    h.resolveModal();
    await flush();
    assert.equal(h.calls.submitted, 1);
    assert.equal(h.submitAction.disabled, true);
    assert.equal(h.form.attributes['aria-busy'], 'true');
    h.submitAction.listeners.click(event());
    assert.equal(h.calls.opened, 1);
    assert.equal(h.calls.submitted, 1);
});

test('Keyboard/form submission cannot bypass confirmation', async() => {
    const h = makeHarness();
    h.exports.init(progress);
    const submit = event();
    h.form.listeners.submit(submit);
    assert.equal(submit.prevented, true);
    assert.equal(h.calls.submitted, 0);
    assert.equal(h.calls.opened, 1);
    h.resolveModal();
    await flush();
    assert.equal(h.calls.submitted, 1);
});

test('Cancellation resets guard and restores focus', async() => {
    const h = makeHarness();
    h.exports.init(progress);
    h.submitAction.listeners.click(event());
    h.rejectModal();
    await flush();
    assert.equal(h.calls.submitted, 0);
    assert.equal(h.submitAction.disabled, false);
    assert.equal(h.calls.focused, 1);
    h.submitAction.listeners.click(event());
    assert.equal(h.calls.opened, 2);
});

test('Timer direct form.submit is never intercepted', () => {
    const h = makeHarness();
    h.exports.init(progress);
    h.submitAction.listeners.click(event());
    h.form.submit();
    assert.equal(h.calls.submitted, 1);
});

test('Repeated initialisation does not install duplicate handlers', () => {
    const h = makeHarness();
    h.exports.init(progress);
    h.exports.init(progress);
    h.submitAction.listeners.click(event());
    assert.equal(h.calls.opened, 1);
});

test('Legacy numeric count is still accepted', () => {
    const h = makeHarness();
    h.exports.init(4);
    h.submitAction.listeners.click(event());
    const context = h.calls.rendered[0].data;
    assert.equal(context.hasprogress, false);
    assert.equal(context.totalunanswered, 4);
});

test('Inconsistent or untrusted counts are never shown', () => {
    for (const bad of [
        {total: 2, answered: 3, pending: 0, flagged: 0},
        {total: '2', answered: 1, pending: 1, flagged: 0},
        {total: 2, answered: 1, pending: 1, flagged: 4},
    ]) {
        const h = makeHarness();
        h.exports.init(bad);
        h.submitAction.listeners.click(event());
        assert.equal(h.calls.rendered[0].data.hasprogress, false);
    }
});

test('Zero pending answers has no unanswered warning', () => {
    const h = makeHarness();
    h.exports.init({...progress, answered: 5, pending: 0});
    h.submitAction.listeners.click(event());
    assert.equal(h.calls.rendered[0].data.hasunanswered, false);
    assert.equal(h.calls.rendered[0].data.haspending, false);
});
