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

const buildModule = (regions = [], overrides = {}) => {
    const file = path.join(__dirname, '..', '..', 'amd', 'build', 'report_insights.min.js');
    const script = fs.readFileSync(file, 'utf8');
    const exports = {};
    const handlers = {};
    const sandbox = {
        document: {querySelectorAll: () => regions},
        window: {
            addEventListener(name, listener) {
                handlers[name] = listener;
            },
        },
        define(name, dependencies, factory) {
            assert.equal(name, 'mod_quiz/report_insights');
            assert.deepEqual(Array.from(dependencies), ['exports']);
            factory(exports);
        },
        ...overrides,
    };
    vm.runInNewContext(script, sandbox, {filename: file});
    return {exports, handlers};
};

const makeRegion = (clientWidth = 400, scrollWidth = 400) => {
    const hint = {textContent: ''};
    const table = {};
    const region = {
        clientWidth, scrollWidth,
        dataset: {overflowHint: 'Use horizontal scrolling to see all columns'},
        querySelector(selector) {
            return selector === 'table' ? table : hint;
        },
    };
    return {region, hint, table};
};

test('wide report receives an explanatory accessible scrolling hint', () => {
    const {exports} = buildModule();
    const {region, hint} = makeRegion(320, 730);
    assert.equal(exports.updateOverflow(region), true);
    assert.equal(hint.textContent, 'Use horizontal scrolling to see all columns');
});

test('no misleading hint when a report fits or has one-pixel rounding', () => {
    const {exports} = buildModule();
    const {region, hint} = makeRegion(400, 400);
    assert.equal(exports.updateOverflow(region), false);
    region.scrollWidth = 401;
    assert.equal(exports.updateOverflow(region), false);
    assert.equal(hint.textContent, '');
});

test('hint disappears after resizing a formerly wide table', () => {
    const {exports} = buildModule();
    const {region, hint} = makeRegion(250, 900);
    exports.updateOverflow(region);
    assert.notEqual(hint.textContent, '');
    region.clientWidth = 900;
    exports.updateOverflow(region);
    assert.equal(hint.textContent, '');
});

test('initialisation is idempotent and responds to normal window resizing', () => {
    const {region, hint} = makeRegion(350, 650);
    const {exports, handlers} = buildModule([region]);
    exports.init();
    exports.init();
    assert.equal(region.dataset.overflowInitialised, '1');
    assert.equal(typeof handlers.resize, 'function');
    assert.notEqual(hint.textContent, '');
    region.scrollWidth = 300;
    handlers.resize();
    assert.equal(hint.textContent, '');
});

test('ResizeObserver follows native table width without touching data', () => {
    const observers = [];
    class TestResizeObserver {
        constructor(callback) {
            this.callback = callback;
            this.observed = [];
            observers.push(this);
        }
        observe(element) {
            this.observed.push(element);
        }
    }
    const {region, hint, table} = makeRegion(250, 1000);
    const {exports} = buildModule([region], {ResizeObserver: TestResizeObserver});
    exports.init();
    assert.equal(observers.length, 1);
    assert.equal(observers[0].observed.length, 2);
    assert.equal(observers[0].observed[1], table);
    region.scrollWidth = 100;
    observers[0].callback();
    assert.equal(hint.textContent, '');
    assert.equal(region.clientWidth, 250);
});
