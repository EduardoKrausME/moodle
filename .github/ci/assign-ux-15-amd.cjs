'use strict';
// Run mod_assign's Mocha-style AMD tests using jsdom and real assertions.
// Root Jest config only discovers ESM tests, not these AMD regression files.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const {JSDOM} = require(require.resolve('jsdom', {paths: [require.resolve('jest-environment-jsdom')]}));
const manifest = require('./assign-ux-15.json');
const sources = {
  'mod_assign/grading_save_state': 'public/mod/assign/amd/src/grading_save_state.js',
  'mod_assign/grading_assessment': 'public/mod/assign/amd/src/grading_assessment.js',
  'mod_assign/bulkactions/grading/selection_ui': 'public/mod/assign/amd/src/bulkactions/grading/selection_ui.js',
};
const files = [...new Set(manifest.items.flatMap(item => item.amd))];
const dom = new JSDOM('<!doctype html><html><body></body></html>', {url: 'http://localhost/', pretendToBeVisual: true});
const scope = vm.createContext({window: dom.window, document: dom.window.document, console, Promise, Set,
    MutationObserver: dom.window.MutationObserver});
const cache = new Map();
const load = name => {
    if (name === 'core/str') {
        return {get_string: () => Promise.resolve('value')};
    }
    if (cache.has(name)) {
        return cache.get(name);
    }
    if (!sources[name]) {
        throw new Error('Unknown AMD module: ' + name);
    }
    let value;
    scope.define = (deps, factory) => {value = factory(...deps.map(load));};
    vm.runInContext(fs.readFileSync(sources[name], 'utf8'), scope, {filename: sources[name]});
    if (!value) {
        throw new Error('No AMD export: ' + name);
    }
    cache.set(name, value);
    return value;
};
let passed = 0;
let failed = 0;
for (const file of files) {
    if (!file.startsWith('public/mod/assign/amd/tests/') || !file.endsWith('_test.js')) {
        throw new Error('Invalid test path: ' + file);
    }
    let before = [], after = [];
    const tests = [];
    scope.chai = {assert: {
        deepEqual: (a, b) => assert.deepStrictEqual(JSON.parse(JSON.stringify(a)), JSON.parse(JSON.stringify(b))),
        isTrue: value => assert.strictEqual(value, true),
        isFalse: value => assert.strictEqual(value, false),
        equal: (a, b) => assert.strictEqual(a, b),
    }};
    scope.describe = (title, fn) => {before = []; after = []; fn();};
    scope.beforeEach = fn => before.push(fn);
    scope.afterEach = fn => after.push(fn);
    scope.it = (title, fn) => tests.push({title, fn, before: [...before], after: [...after]});
    scope.define = (deps, factory) => factory(...deps.map(load));
    vm.runInContext(fs.readFileSync(file, 'utf8'), scope, {filename: file});
    if (!tests.length) {
        throw new Error('No tests in ' + file);
    }
    for (const test of tests) {
        let failure;
        try {
            for (const fn of test.before) {
                fn();
            }
            test.fn();
        } catch (error) {
            failure = error;
        } finally {
            for (const fn of test.after) {
                try {
                    fn();
                } catch (error) {
                    failure = failure || error;
                }
            }
        }
        if (failure) {
            failed++;
            console.error('FAIL ' + file + ' :: ' + test.title, failure);
        } else {
            passed++;
            console.log('PASS ' + file + ' :: ' + test.title);
        }
    }
}
dom.window.close();
console.log('AMD regressions: ' + passed + ' passed; ' + failed + ' failed');
if (!passed || failed) {
    process.exitCode = 1;
}
