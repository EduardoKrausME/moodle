// Isolated regression tests for the native Moodle Quiz YUI countdown.
// Run from public/mod/quiz: node tests/js/timer_ux.test.js
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const moduleSource = fs.readFileSync(path.resolve(__dirname, '../../module.js'), 'utf8');

function fixture(seconds, options = {}) {
    const clock = {now: 1700000000000};
    const actions = {scheduled: new Map(), announcements: [], preferences: [], submits: 0, marked: 0};
    const nodes = new Map();
    let nextId = 0;
    const callbacks = {};
    let delayedPreference = null;
    function node(selector) {
        if (nodes.has(selector)) {
            return nodes.get(selector);
        }
        const n = {
            attributes: {},
            content: '',
            writes: 0,
            visible: true,
            on: (event, fn) => { callbacks['node:' + selector + ':' + event] = fn; },
            setStyle: function(name, value) { this.attributes['style:' + name] = value; },
            setAttribute: function(name, value) { this.attributes[name] = value; },
            removeAttribute: function(name) { delete this.attributes[name]; },
            getAttribute: function(name) { return this.attributes[name]; },
            setContent: function(value) { this.content = value; this.writes++; },
            set: function(key, value) {
                if (key === 'text') {
                    this.content = value;
                    if (selector === '#quiz-timer-announcement') {
                        actions.announcements.push(value);
                    }
                } else {
                    this.attributes[key] = value;
                }
            },
            show: function() { this.visible = true; },
            hide: function() { this.visible = false; },
        };
        nodes.set(selector, n);
        return n;
    }
    const form = {
        one: (selector) => selector === 'input[name=finishattempt]' ? options.finishField || null : null,
        submit: () => { actions.submits++; },
    };
    node('input[name=timeup]').ancestor = () => form;
    node('input[name=timeup]').getDOMNode = () => ({});
    class FakeDate extends Date {
        constructor(...args) {
            super(...(args.length ? args : [clock.now]));
        }
        static now() { return clock.now; }
    }
    const Y = {one: node, log: () => {}};
    const M = {
        mod_quiz: {},
        pageloadstarttime: new Date(clock.now),
        util: {get_string: (key) => key},
    };
    const document = {
        hidden: false,
        addEventListener: (event, fn) => { callbacks[event] = fn; },
    };
    const fakeRequire = (names, callback) => {
        if (names[0] === 'core_form/changechecker') {
            if (!options.delayChangeChecker) {
                callback({markFormSubmitted: () => { actions.marked++; }});
            }
        } else if (names[0] === 'core_user/repository') {
            callback({
                getUserPreference: () => ({
                    then: (fn) => {
                        if (options.delayPreference) {
                            delayedPreference = fn;
                        } else {
                            fn(options.preference || '0');
                        }
                        return {catch: () => {}};
                    },
                }),
                setUserPreference: (key, value) => { actions.preferences.push([key, value]); },
            });
        }
    };
    const context = {
        M, Y, document, window: {Y}, require: fakeRequire, Date: FakeDate,
        setTimeout: (fn) => {
            const id = ++nextId;
            actions.scheduled.set(id, fn);
            return id;
        },
        clearTimeout: (id) => { actions.scheduled.delete(id); },
    };
    vm.runInNewContext(moduleSource, context, {filename: 'module.js'});
    const timer = context.M.mod_quiz.timer;
    timer.init(Y, seconds, !!options.preview);
    return {
        clock, actions, timer, nodes, callbacks, document,
        node,
        advance: (milliseconds) => { clock.now += milliseconds; timer.update(); },
        sync: (timeleft) => timer.updateEndTime(timeleft),
        respondPreference: (value) => { if (delayedPreference) { delayedPreference(value); } },
    };
}

let passed = 0;
function test(name, fn) {
    fn();
    passed++;
    console.log('PASS ' + name);
}

test('normal state and stable hh:mm:ss with one scheduled loop', () => {
    const f = fixture(800);
    assert.equal(f.timer.state, 'normal');
    assert.equal(f.node('#quiz-time-left').content, '0:13:20');
    assert.equal(f.actions.scheduled.size, 1);
    const before = f.node('#quiz-time-left').writes;
    f.timer.update();
    assert.equal(f.node('#quiz-time-left').writes, before);
    assert.equal(f.actions.scheduled.size, 1);
});

test('five-minute warning is announced only at the boundary', () => {
    const f = fixture(301);
    f.advance(1000);
    assert.equal(f.timer.state, 'warning');
    assert.equal(f.node('#quiz-timer').attributes['data-time-state'], 'warning');
    assert.deepEqual(f.actions.announcements.filter(Boolean), ['timerwarning']);
    f.timer.update();
    assert.equal(f.actions.announcements.filter(Boolean).length, 1);
});

test('critical state forces visibility and disables the toggle', () => {
    const f = fixture(120, {preference: '1'});
    assert.equal(f.node('#quiz-time-left').visible, false);
    f.advance(21000);
    assert.equal(f.timer.state, 'critical');
    assert.equal(f.node('#quiz-time-left').visible, true);
    assert.equal(f.node('#toggle-timer').attributes.disabled, true);
    assert.equal(f.node('#toggle-timer').attributes['aria-expanded'], 'true');
    f.timer.toggleVisibility();
    assert.equal(f.node('#quiz-time-left').visible, true);
    assert.equal(f.actions.preferences.length, 0);
});

test('late hidden preference cannot hide the critical countdown', () => {
    const f = fixture(70, {delayPreference: true});
    f.respondPreference('1');
    assert.equal(f.timer.state, 'critical');
    assert.equal(f.node('#quiz-time-left').visible, true);
});

test('normal show and hide preserve user preference and ARIA', () => {
    const f = fixture(600);
    f.timer.toggleVisibility();
    assert.equal(f.node('#quiz-time-left').visible, false);
    assert.equal(f.node('#toggle-timer').attributes['aria-expanded'], 'false');
    f.timer.toggleVisibility();
    assert.equal(f.node('#quiz-time-left').visible, true);
    assert.deepEqual(f.actions.preferences.map(a => a[1]), ['1', '0']);
});

test('server autosave correction reuses the single timer', () => {
    const f = fixture(500);
    f.advance(100000);
    f.sync(800);
    assert.equal(f.timer.state, 'normal');
    assert.equal(f.actions.scheduled.size, 1);
    assert.equal(f.node('#quiz-time-left').content, '0:13:20');
});

test('corrections smaller than three seconds retain the native threshold', () => {
    const f = fixture(400);
    const before = f.timer.endtime;
    f.sync(402);
    assert.equal(f.timer.endtime, before);
    assert.equal(f.actions.scheduled.size, 1);
});

test('expiry submits exactly once with native timeup flag and checker', () => {
    const f = fixture(2);
    f.advance(3000);
    assert.equal(f.timer.state, 'expired');
    assert.equal(f.node('input[name=timeup]').attributes.value, 1);
    assert.equal(f.actions.submits, 1);
    assert.equal(f.actions.marked, 1);
    assert.equal(f.actions.scheduled.size, 0);
    f.timer.update();
    f.sync(10);
    assert.equal(f.actions.submits, 1);
});

test('expiry still submits if changechecker is not loaded yet', () => {
    const f = fixture(1, {delayChangeChecker: true});
    f.advance(2000);
    assert.equal(f.actions.submits, 1);
    assert.equal(f.actions.marked, 0);
});

test('tab visibility rechecks elapsed wall-clock time', () => {
    const f = fixture(180);
    f.clock.now += 85000;
    f.document.hidden = true;
    f.callbacks.visibilitychange();
    assert.equal(f.timer.state, 'warning');
    f.document.hidden = false;
    f.callbacks.visibilitychange();
    assert.equal(f.timer.state, 'critical');
    assert.equal(f.actions.scheduled.size, 1);
});

test('preview flag remains native and has no new clock policy', () => {
    const f = fixture(200, {preview: true});
    assert.equal(f.timer.preview, true);
    assert.equal(f.timer.state, 'warning');
});

test('timer stop prevents later autosave corrections or submissions', () => {
    const f = fixture(50);
    f.timer.stop();
    f.clock.now += 60000;
    f.sync(300);
    f.timer.update();
    assert.equal(f.actions.submits, 0);
    assert.equal(f.actions.scheduled.size, 0);
});

test('deadline extension restores the toggle without silently changing preference', () => {
    const f = fixture(80);
    assert.equal(f.node('#toggle-timer').attributes.disabled, true);
    f.sync(800);
    assert.equal(f.timer.state, 'normal');
    assert.equal(f.node('#toggle-timer').attributes.disabled, undefined);
    assert.equal(f.actions.preferences.length, 0);
});

console.log('PASS ' + passed + ' countdown scenarios');
