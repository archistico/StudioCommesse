import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

function mount(count, width) {
    let resize;
    const actions = Array.from({ length: count }, (_, index) => ({
        id: index,
        classList: { contains: () => false },
    }));
    const bar = {
        clientWidth: width,
        children: [{ classList: { contains: (name) => name === 'btn-list' }, children: actions }],
        replaceChildren: (...rows) => { bar.children = rows; },
    };
    vm.runInNewContext(readFileSync(new URL('../../public/assets/js/app.js', import.meta.url), 'utf8'), {
        document: {
            addEventListener: (_, callback) => callback(),
            querySelectorAll: (selector) => selector === '.section-actions' ? [bar] : [],
            createElement: () => ({ children: [], append(...items) { this.children.push(...items); } }),
        },
        window: { ResizeObserver: class {
            constructor(callback) { resize = callback; }
            observe(target) { assert.equal(target, bar); }
        } },
    });
    return {
        rows: () => bar.children.map(row => [...row.children].map(item => item.id)),
        resize(width) { bar.clientWidth = width; resize(); },
    };
}

test('action rows never exceed six and balance overflow instead of leaving one isolated action', () => {
    for (const [count, lengths] of [[1, [1]], [6, [6]], [7, [4, 3]], [12, [6, 6]], [13, [5, 4, 4]]]) {
        const bar = mount(count, 1800);
        assert.deepEqual(bar.rows().map(row => row.length), lengths);
        assert.deepEqual(bar.rows().flat(), Array.from({ length: count }, (_, i) => i));
    }
});

test('resizing preserves all actions and their order across phone, tablet and desktop layouts', () => {
    const bar = mount(7, 1000);
    assert.deepEqual(bar.rows().map(row => row.length), [4, 3]);
    bar.resize(290);
    assert.deepEqual(bar.rows().map(row => row.length), [1, 1, 1, 1, 1, 1, 1]);
    bar.resize(350);
    assert.deepEqual(bar.rows().map(row => row.length), [2, 2, 2, 1]);
    bar.resize(740);
    assert.deepEqual(bar.rows().map(row => row.length), [4, 3]);
    bar.resize(1800);
    assert.deepEqual(bar.rows().flat(), [0, 1, 2, 3, 4, 5, 6]);
});
