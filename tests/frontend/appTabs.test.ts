import assert from 'node:assert/strict';
import test from 'node:test';
import {
    createTab,
    removeTab,
    tabKey,
    upsertTab,
} from '../../resources/js/lib/appTabs.ts';

test('upserts a menu once and updates its latest query string', () => {
    const dashboard = createTab('/dashboard', 'AUC 工作台');
    const users = createTab('/users', '用户管理');
    const tabs = upsertTab(
        upsertTab([dashboard], users),
        createTab('/users?page=2', '用户管理'),
    );

    assert.deepEqual(tabs, [dashboard, createTab('/users?page=2', '用户管理')]);
    assert.equal(tabKey('/users?page=2'), '/users');
});

test('closes the active tab and selects the closest tab on the left', () => {
    const tabs = [
        createTab('/dashboard', 'AUC 工作台'),
        createTab('/users', '用户管理'),
        createTab('/roles', '角色管理'),
    ];

    const result = removeTab(tabs, '/roles', '/roles');

    assert.deepEqual(
        result.tabs.map((tab) => tab.key),
        ['/dashboard', '/users'],
    );
    assert.equal(result.nextActiveKey, '/users');
});

test('selects the next tab when closing the first active tab', () => {
    const tabs = [
        createTab('/dashboard', 'AUC 工作台'),
        createTab('/users', '用户管理'),
    ];

    const result = removeTab(tabs, '/dashboard', '/dashboard');

    assert.deepEqual(
        result.tabs.map((tab) => tab.key),
        ['/users'],
    );
    assert.equal(result.nextActiveKey, '/users');
});

test('keeps the active tab when closing another tab', () => {
    const tabs = [
        createTab('/dashboard', 'AUC 工作台'),
        createTab('/users', '用户管理'),
        createTab('/roles', '角色管理'),
    ];

    const result = removeTab(tabs, '/roles', '/users');

    assert.deepEqual(result.tabs.map((tab) => tab.key), [
        '/dashboard',
        '/users',
    ]);
    assert.equal(result.nextActiveKey, '/users');
});

test('returns an empty active key when the last tab is closed', () => {
    const result = removeTab(
        [createTab('/dashboard', 'AUC 工作台')],
        '/dashboard',
        '/dashboard',
    );

    assert.deepEqual(result.tabs, []);
    assert.equal(result.nextActiveKey, null);
});
