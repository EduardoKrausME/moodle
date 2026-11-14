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

/**
 * Test the actual AMD build that Moodle serves, not a rewritten copy.
 */
const loadModule = (document = {}) => {
    const filepath = path.join(__dirname, '..', '..', 'amd', 'build', 'manual_grading_navigation.min.js');
    const code = fs.readFileSync(filepath, 'utf8');
    const exports = {};
    vm.runInNewContext(code, {
        document,
        define(name, dependencies, factory) {
            assert.equal(name, 'mod_quiz/manual_grading_navigation');
            assert.equal(dependencies.length, 1);
            assert.equal(dependencies[0], 'exports');
            factory(exports);
        },
    }, {filename: filepath});
    return exports;
};

const field = (value = '', type = 'text') => ({type, value, checked: false});

const makeCard = controls => {
    const status = {dataset: {cleanMessage: 'Unchanged', dirtyMessage: 'Unsaved'}, textContent: ''};
    return {
        status,
        querySelectorAll() {
            return controls;
        },
        querySelector() {
            return status;
        },
    };
};

const makeForm = (cards, template = 'Edits: %EDITED% of %TOTAL%') => {
    const progress = {dataset: {progressTemplate: template}, textContent: ''};
    return {
        progress,
        querySelector() {
            return progress;
        },
        querySelectorAll() {
            return cards;
        },
    };
};

test('native mark and comment controls are snapshotted without mutation', () => {
    const {controlSnapshot} = loadModule();
    const mark = field('3.5');
    const comment = field('Existing comment', 'textarea');
    const radio = field('yes', 'radio');
    assert.equal(controlSnapshot(mark), '3.5');
    assert.equal(controlSnapshot(comment), 'Existing comment');
    assert.equal(controlSnapshot(radio), 'false');
    radio.checked = true;
    assert.equal(controlSnapshot(radio), 'true');
    assert.equal(mark.value, '3.5');
    assert.equal(comment.value, 'Existing comment');
});

test('select-multiple and file controls are tracked without interpreting their contents', () => {
    const {controlSnapshot} = loadModule();
    const select = {type: 'select-multiple', options: [{selected: false}, {selected: true}]};
    const file = {type: 'file', files: [{name: 'private.pdf'}]};
    assert.equal(controlSnapshot(select), '[false,true]');
    assert.equal(controlSnapshot(file), '1');
    assert.ok(!controlSnapshot(file).includes('private.pdf'));
});

test('one edited response is distinguished from a saved response and can be reverted', () => {
    const {controlSnapshot, responseChanged, updateProgress} = loadModule();
    const mark = field('');
    const comment = field('old comment', 'textarea');
    const card = makeCard([mark, comment]);
    const form = makeForm([card]);
    const initial = new WeakMap([[mark, controlSnapshot(mark)], [comment, controlSnapshot(comment)]]);

    assert.equal(responseChanged(card, initial), false);
    assert.equal(updateProgress(form, [card], initial), 0);
    assert.equal(card.status.textContent, 'Unchanged');
    mark.value = '2';
    assert.equal(updateProgress(form, [card], initial), 1);
    assert.equal(card.status.textContent, 'Unsaved');
    assert.equal(card.status.dataset.dirty, 'true');
    assert.equal(form.progress.textContent, 'Edits: 1 of 1');
    mark.value = '';
    assert.equal(updateProgress(form, [card], initial), 0);
    assert.equal(form.progress.textContent, 'Edits: 0 of 1');
    assert.equal(card.status.dataset.dirty, 'false');
});

test('progress counts responses, not the number of edited controls', () => {
    const {controlSnapshot, updateProgress} = loadModule();
    const firstMark = field(''), firstComment = field('');
    const secondMark = field('');
    const cards = [makeCard([firstMark, firstComment]), makeCard([secondMark])];
    const form = makeForm(cards);
    const baseline = new WeakMap([[firstMark, controlSnapshot(firstMark)],
        [firstComment, controlSnapshot(firstComment)], [secondMark, controlSnapshot(secondMark)]]);
    firstMark.value = '0.4';
    firstComment.value = 'Comment';
    assert.equal(updateProgress(form, cards, baseline), 1);
    assert.equal(form.progress.textContent, 'Edits: 1 of 2');
    secondMark.value = '0.8';
    assert.equal(updateProgress(form, cards, baseline), 2);
    assert.equal(form.progress.textContent, 'Edits: 2 of 2');
});

test('initialisation is idempotent and does not submit grades automatically', () => {
    const input = field('');
    const card = makeCard([input]);
    const progress = {dataset: {progressTemplate: '%EDITED% of %TOTAL%'}, textContent: ''};
    const handlers = {};
    const form = {
        dataset: {savingLabel: 'Saving...'},
        querySelectorAll() {
            return [card];
        },
        querySelector(selector) {
            if (selector === 'input[type="submit"]') {
                return {value: 'Save and show next'};
            }
            return progress;
        },
        addEventListener(name, callback) {
            if (!handlers[name]) {
                handlers[name] = [];
            }
            handlers[name].push(callback);
        },
    };
    const {init} = loadModule({getElementById: () => form});
    init();
    init();
    assert.equal(handlers.input.length, 1);
    assert.equal(handlers.change.length, 1);
    assert.equal(handlers.submit.length, 1);
    input.value = '0.5';
    handlers.input[0]();
    assert.equal(progress.textContent, '1 of 1');
    assert.equal(form.dataset.manualGradingSubmitting, undefined);
    let prevented = 0;
    handlers.submit[0]({preventDefault: () => prevented++});
    handlers.submit[0]({preventDefault: () => prevented++});
    assert.equal(prevented, 1);
    assert.equal(input.value, '0.5');
});
