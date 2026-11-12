// Regression tests for server-confirmed quiz question progress.
// Run from public/mod/quiz with: node tests/js/attempt_progress.test.js
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const moduleCode = fs.readFileSync(path.resolve(__dirname, '../../module.js'), 'utf8');
const sourceFiles = [
    '../../yui/src/autosave/js/autosave.js',
    '../../yui/build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave.js',
    '../../yui/build/moodle-mod_quiz-autosave/moodle-mod_quiz-autosave-min.js',
];

function makeUi() {
    const values = Object.fromEntries(['answered', 'pending', 'flagged']
        .map(key => [key, {textContent: '0'}]));
    const bar = {
        attrs: {},
        setAttribute(name, value) { this.attrs[name] = value; },
    };
    const fill = {style: {width: '0%'}};
    const root = {
        attrs: {},
        setAttribute(name, value) { this.attrs[name] = value; },
        querySelector(selector) {
            if (selector === '[role="progressbar"]') {
                return bar;
            }
            if (selector === '.mod-quiz-progress-fill') {
                return fill;
            }
            const match = selector.match(/\[data-progress-count="(\w+)"\]/);
            return match ? values[match[1]] : null;
        },
    };
    let flagcount = 0;
    const doc = {
        getElementById: selector => selector === 'mod-quiz-progress' ? root : null,
        querySelectorAll: selector => {
            assert.match(selector, /data-progress-real="1"/);
            return Array.from({length: flagcount});
        },
    };
    const context = {
        M: {mod_quiz: {}, util: {get_string: (key, component, a) => JSON.stringify(a)}},
        document: doc,
        window: {},
        require: () => {},
        Y: {},
        setTimeout: () => 1,
        clearTimeout: () => {},
    };
    vm.runInNewContext(moduleCode, context);
    return {context, root, bar, fill, values, setFlags: value => { flagcount = value; }};
}

function makeAutosave(ui, pathToSource) {
    const status = [];
    const m = ui.context.M;
    m.cfg = {wwwroot: 'https://example.invalid'};
    m.mod_quiz.timer = {updateEndTime: () => {}};
    const Y = {
        log: () => {},
        one: () => ({hide: () => {}, show: () => {}}),
    };
    const source = fs.readFileSync(path.resolve(__dirname, pathToSource), 'utf8');
    const context = {
        M: m,
        Y,
        window: {},
        require: () => {},
        YUI: {add: (name, fn) => fn(Y, name)},
    };
    vm.runInNewContext(source, context);
    const autosave = m.mod_quiz.autosave;
    autosave.update_saved_time_display = () => {};
    autosave.start_save_timer = () => {};
    autosave.set_save_status = state => status.push(state);
    return {autosave, status};
}

const ui = makeUi();
const p = ui.context.M.mod_quiz.progress;
p.update({total: 5, answered: 2, pending: 3, flagged: 1});
assert.equal(ui.values.answered.textContent, 2);
assert.equal(ui.values.pending.textContent, 3);
assert.equal(ui.values.flagged.textContent, 1);
assert.equal(ui.bar.attrs['aria-valuemax'], 5);
assert.equal(ui.bar.attrs['aria-valuenow'], 2);
assert.equal(ui.fill.style.width, '40%');
console.log('PASS confirmed summary and accessible bar');

for (const invalid of [
    {total: 4, answered: 5, pending: 0, flagged: 0},
    {total: 4, answered: '2', pending: 2, flagged: 0},
    {total: 4, answered: 2.5, pending: 1.5, flagged: 0},
    {total: 4, answered: 2, pending: 2, flagged: 5},
]) {
    p.update(invalid);
    assert.equal(ui.values.answered.textContent, 2);
}
console.log('PASS invalid payloads never change progress');

p.update({total: 0, answered: 0, pending: 0, flagged: 0});
assert.equal(ui.fill.style.width, '0%');
p.update({total: 4, answered: 3, pending: 1, flagged: 2});
assert.equal(ui.fill.style.width, '75%');
console.log('PASS zero totals and changed totals');

ui.setFlags(3);
p.syncFlags();
assert.equal(ui.values.flagged.textContent, 3);
assert.equal(ui.values.answered.textContent, 3);
console.log('PASS independent confirmed flag count');

for (const filename of sourceFiles) {
    const local = makeUi();
    const {autosave} = makeAutosave(local, filename);
    autosave.save_transaction = {};
    autosave.save_done(1, {responseText: JSON.stringify({
        status: 'OK',
        progress: {total: 2, answered: 1, pending: 1, flagged: 0},
    })});
    assert.equal(local.values.answered.textContent, 1);

    local.values.answered.textContent = 0;
    autosave.save_transaction = {};
    autosave.dirty = true;
    autosave.save_done(1, {responseText: JSON.stringify({
        status: 'OK', progress: {total: 2, answered: 2, pending: 0, flagged: 0},
    })});
    assert.equal(local.values.answered.textContent, 0);
    autosave.dirty = false;

    autosave.upload_unconfirmed = true;
    autosave.save_transaction = {};
    autosave.save_done(1, {responseText: JSON.stringify({
        status: 'OK', progress: {total: 2, answered: 2, pending: 0, flagged: 0},
    })});
    assert.equal(local.values.answered.textContent, 0);
    autosave.upload_unconfirmed = false;

    autosave.save_transaction = {};
    autosave.save_done(1, {responseText: 'invalid json'});
    assert.equal(local.values.answered.textContent, 0);
    console.log('PASS valid/dirty/upload/error autosave integration: ' + filename);
}

console.log('PASS 7 groups of progress assertions');
