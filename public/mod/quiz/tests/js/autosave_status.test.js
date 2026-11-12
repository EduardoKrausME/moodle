// Tests for the YUI autosave state display; run with: node tests/js/autosave_status.test.js
// No test runner or Moodle database required.
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const paths = [
    '../../yui/src/autosave/js/autosave.js',
    '../../yui/build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js',
    '../../yui/build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave-min.js',
];

function makeAutosave(filename) {
    const messages = [];
    const alerts = [];
    const statusnode = {
        setAttribute: (name, value) => messages.push(['attribute', name, value]),
        set: (name, value) => messages.push(['text', name, value]),
    };
    const nodes = {
        '#mod-quiz-autosave-status': statusnode,
        '#connection-error': {
            show: () => alerts.push('error shown'),
            hide: () => alerts.push('error hidden'),
        },
        '#connection-ok': {
            show: () => alerts.push('ok shown'),
            hide: () => alerts.push('ok hidden'),
        },
    };
    const context = {
        M: {
            cfg: {wwwroot: 'https://example.invalid'},
            mod_quiz: {timer: {endtime: 0, updateEndTime: () => {}}},
            util: {get_string: (name) => name},
        },
        Y: {
            one: (selector) => nodes[selector] ?? null,
            log: () => {},
        },
        require: () => {},
        window: {},
    };
    context.YUI = {
        add: (name, factory) => factory(context.Y, name),
    };
    vm.runInNewContext(fs.readFileSync(path.resolve(__dirname, filename), 'utf8'), context, {filename});
    const autosave = context.M.mod_quiz.autosave;
    autosave.update_saved_time_display = () => {};
    autosave.timerstarts = 0;
    autosave.start_save_timer = () => { autosave.timerstarts++; };
    return {autosave, context, messages, alerts};
}

function check(filename) {
    {
        const {autosave} = makeAutosave(filename);
        autosave.save_transaction = {};
        autosave.save_done(1, {responseText: '{"status":"OK"}'});
        assert.equal(autosave.current_save_status, 'saved');
        assert.equal(autosave.save_transaction, null);
    }
    {
        const {autosave} = makeAutosave(filename);
        autosave.start_save_timer_if_necessary();
        assert.equal(autosave.dirty, true);
        assert.equal(autosave.current_save_status, 'pending');
        assert.equal(autosave.timerstarts, 1);
    }
    {
        const {autosave} = makeAutosave(filename);
        autosave.dirty = true;
        autosave.save_transaction = {};
        autosave.save_done(1, {responseText: '{"status":"OK"}'});
        assert.equal(autosave.current_save_status, 'pending');
        assert.equal(autosave.timerstarts, 1);
    }
    for (const key of ['uploads_in_progress', 'upload_unconfirmed']) {
        const {autosave} = makeAutosave(filename);
        autosave[key] = key === 'uploads_in_progress' ? 1 : true;
        autosave.save_transaction = {};
        autosave.save_done(1, {responseText: '{"status":"OK"}'});
        assert.equal(autosave.current_save_status, 'pending');
    }
    for (const invalid of ['{"status":"FAILED"}', 'invalid json', 'null']) {
        const {autosave} = makeAutosave(filename);
        autosave.save_transaction = {};
        autosave.save_done(1, {responseText: invalid});
        assert.equal(autosave.current_save_status, 'error');
        assert.equal(autosave.timerstarts, 1);
    }
    {
        const {autosave, alerts} = makeAutosave(filename);
        autosave.savefailures = 1;
        autosave.save_done(1, {responseText: '{"status":"OK"}'});
        assert.equal(autosave.current_save_status, 'saved');
        assert.ok(alerts.includes('error hidden'));
    }
    {
        const {autosave} = makeAutosave(filename);
        autosave.stopped = true;
        autosave.save_done(1, {responseText: '{"status":"OK"}'});
        assert.equal(autosave.current_save_status, 'ready');
    }
    {
        const {autosave} = makeAutosave(filename);
        autosave.is_time_nearly_over = () => true;
        autosave.dirty = true;
        autosave.save_changes();
        assert.equal(autosave.current_save_status, 'unavailable');
        assert.equal(autosave.stopped, true);
    }
    {
        const {autosave, context} = makeAutosave(filename);
        let submitted = false;
        const controls = {setAttribute: () => { submitted = true; }};
        autosave.form = {all: () => controls};
        autosave.is_time_nearly_over = () => false;
        context.Y.io = (handler, config) => {
            assert.match(handler, /autosave\.ajax\.php$/);
            assert.equal(config.method, 'POST');
            assert.equal(config.form.id, autosave.form);
            return {id: 1};
        };
        autosave.save_changes();
        assert.equal(autosave.current_save_status, 'saving');
        assert.equal(autosave.dirty, false);
        assert.equal(submitted, true);
    }
    {
        const {autosave, context} = makeAutosave(filename);
        const listeners = {};
        const originalOne = context.Y.one;
        const fakeForm = {delegate: () => {}, on: () => {}};
        context.Y.one = (selector) => selector === '#responseform' ? fakeForm : originalOne(selector);
        context.window.addEventListener = (name, callback) => { listeners[name] = callback; };
        context.require = (dependencies, callback) => callback({
            eventTypes: {
                uploadStarted: 'uploadStarted',
                uploadChanged: 'uploadChanged',
                uploadCompleted: 'uploadCompleted',
            },
        });
        autosave.init_tinymce = () => {};
        autosave.save_hidden_field_values = () => {};
        autosave.watch_hidden_fields = () => {};
        autosave.init(60);
        listeners.uploadStarted();
        assert.equal(autosave.uploads_in_progress, 1);
        assert.equal(autosave.upload_unconfirmed, true);
        listeners.uploadChanged({target: {getAttribute: (name) => name === 'name' ? 'answer' : null}});
        listeners.uploadCompleted();
        assert.equal(autosave.uploads_in_progress, 0);
        assert.equal(autosave.upload_unconfirmed, false);
        assert.equal(autosave.dirty, true);
        assert.equal(autosave.current_save_status, 'pending');
    }
    console.log('PASS ' + filename + ' (13 scenarios)');
}

for (const filename of paths) {
    check(filename);
}
