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

'use strict';

const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function loadBuild() {
    const filename = path.join(__dirname, '..', '..', 'amd', 'build', 'settings_guidance.min.js');
    const code = fs.readFileSync(filename, 'utf8');
    let result;
    vm.runInNewContext(code, {
        define(name, dependencies, factory) {
            assert.equal(name, 'mod_quiz/settings_guidance');
            const exports = {};
            assert.deepEqual(Array.from(dependencies), ['exports']);
            factory(exports);
            result = exports;
        },
    }, {filename});
    return result;
}

function input(value = '', checked = false, disabled = false, hidden = false) {
    const item = {hidden, style: {display: hidden ? 'none' : ''}};
    return {
        value: String(value),
        checked,
        disabled,
        tagName: 'INPUT',
        closest(selector) {
            if (selector === '.fitem') {
                return item;
            }
            return hidden ? {} : null;
        },
    };
}

function form(fields = {}) {
    return {elements: {namedItem: name => fields[name] ?? null}};
}

function region(area, messages = {}) {
    const output = {textContent: ''};
    const root = {
        dataset: {guidance: area},
        getAttribute(name) {
            return name.startsWith('data-message-') ? messages[name.slice(13)] ?? null : null;
        },
        querySelector() {
            return output;
        },
    };
    return {root, output};
}

test('default guidance is readable without any configured options', () => {
    const {renderGuidance} = loadBuild();
    const {root, output} = region('timing', {base: 'Opening and closing are different.'});
    renderGuidance(form(), root);
    assert.equal(output.textContent, 'Opening and closing are different.');
});

test('timing combines effective enabled controls without rewriting them', () => {
    const {renderGuidance} = loadBuild();
    const fields = {
        'timeopen[enabled]': input(1, true),
        'duedate[enabled]': input(1, true),
        'timeclose[enabled]': input(1, false),
        'timelimit[enabled]': input(1, true),
        overduehandling: input('graceperiod'),
    };
    const before = JSON.stringify(Object.fromEntries(Object.entries(fields).map(([name, el]) =>
        [name, {value: el.value, checked: el.checked, disabled: el.disabled}])));
    const {root, output} = region('timing', {
        base: 'Basics', open: 'Open enabled', due: 'Due is not close',
        close: 'Close enabled', limit: 'Limit enabled', grace: 'Grace enabled',
    });
    renderGuidance(form(fields), root);
    assert.match(output.textContent, /Due is not close/);
    assert.match(output.textContent, /Limit enabled/);
    assert.doesNotMatch(output.textContent, /Close enabled/);
    assert.equal(JSON.stringify(Object.fromEntries(Object.entries(fields).map(([name, el]) =>
        [name, {value: el.value, checked: el.checked, disabled: el.disabled}]))), before);
});

test('grading distinguishes one, multiple and unlimited defaults', () => {
    const {renderGuidance} = loadBuild();
    const {root, output} = region('grading', {
        base: 'Overrides still apply', single: 'One', multiple: 'Several', unlimited: 'Unlimited',
    });
    const attempts = input(1);
    const model = form({attempts});
    for (const [value, expected] of [['1', 'One'], ['3', 'Several'], ['0', 'Unlimited']]) {
        attempts.value = value;
        renderGuidance(model, root);
        assert.equal(output.textContent, 'Overrides still apply ' + expected);
    }
});

test('native hidden and disabled inputs do not trigger contextual claims', () => {
    const {renderGuidance} = loadBuild();
    const {root, output} = region('layout', {
        base: 'Layout help', sequential: 'Sequential', repaginate: 'Repaginate',
    });
    const fields = {
        navmethod: input('sequential', false, true),
        repaginatenow: input(1, true, false, true),
    };
    renderGuidance(form(fields), root);
    assert.equal(output.textContent, 'Layout help');
});

test('review respects disabled options and absent closing time', () => {
    const {renderGuidance} = loadBuild();
    const {root, output} = region('review', {
        base: 'Review rules', closeoff: 'No close', answers: 'Answers available', marks: 'Marks available',
    });
    const fields = {
        'timeclose[enabled]': input(0, false),
        rightanswerclosed: input(1, true),
        marksopen: input(1, true, true),
    };
    renderGuidance(form(fields), root);
    assert.equal(output.textContent, 'Review rules No close');
    fields.rightansweropen = input(1, true);
    renderGuidance(form(fields), root);
    assert.equal(output.textContent, 'Review rules No close Answers available');
});

test('secret and network values are never included in the output', () => {
    const {renderGuidance} = loadBuild();
    const {root, output} = region('restrictions', {
        base: 'Restrictions', password: 'Password enabled', subnet: 'Network enabled',
        browser: 'Browser configured',
    });
    renderGuidance(form({
        quizpassword: input('Super-secret-123'),
        subnet: input('192.168.1.100'),
        browsersecurity: input('none'),
    }), root);
    assert.equal(output.textContent, 'Restrictions Password enabled Network enabled');
    assert.doesNotMatch(output.textContent, /Super-secret-123|192\.168\.1\.100/);
});

test('guide is informational and has no form submission or mutation dependencies', () => {
    const {renderGuidance} = loadBuild();
    const {root, output} = region('behaviour', {base: 'Native behaviour', redo: 'Can redo', reuse: 'Reuse'});
    renderGuidance(form({canredoquestions: input('1'), attemptonlast: input('1')}), root);
    assert.equal(output.textContent, 'Native behaviour Can redo Reuse');
    const {root: grade} = region('feedback', {base: 'Feedback', nograde: 'Zero grade'});
    renderGuidance(form({grade: input('0')}), grade);
    assert.equal(grade.querySelector().textContent, 'Feedback Zero grade');
});
