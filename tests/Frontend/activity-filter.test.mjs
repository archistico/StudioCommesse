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
