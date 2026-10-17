import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

test('the assignee change submits its form without requiring DataTables', () => {
    let submitted = 0;
    const listeners = new Map();
    const select = {
        form: { requestSubmit: () => { submitted += 1; } },
        addEventListener: (event, callback) => listeners.set(event, callback),
    };
    const document = {
        addEventListener: (event, callback) => {
            assert.equal(event, 'DOMContentLoaded');
            callback();
        },
        querySelectorAll: (selector) => selector === 'select[data-auto-submit]' ? [select] : [],
    };

    vm.runInNewContext(readFileSync(new URL('../../public/assets/js/app.js', import.meta.url), 'utf8'), {
        document,
        window: {},
    });

    assert.equal(submitted, 0);
    assert.equal(typeof listeners.get('change'), 'function');
    listeners.get('change')();
    assert.equal(submitted, 1);
});

test('changing the creation project reloads choices with the validation-free load button', () => {
    let submitted;
    let onChange;
    const loadButton = { name: 'load', formNoValidate: true };
    const select = {
        form: {
            querySelector: (selector) => {
                assert.equal(selector, 'button[name="load"]');
                return loadButton;
            },
            requestSubmit: (button) => { submitted = button; },
        },
        addEventListener: (event, callback) => {
            assert.equal(event, 'change');
            onChange = callback;
        },
    };
    vm.runInNewContext(readFileSync(new URL('../../public/assets/js/app.js', import.meta.url), 'utf8'), {
        document: {
            addEventListener: (_, callback) => callback(),
            querySelectorAll: (selector) => selector === 'select[data-context-select]' ? [select] : [],
        },
        window: {},
    });
    assert.equal(submitted, undefined);
    onChange();
    assert.equal(submitted, loadButton);
});
