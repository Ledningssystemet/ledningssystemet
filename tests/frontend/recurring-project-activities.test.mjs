import assert from 'node:assert/strict';
import { test } from 'node:test';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { runInNewContext } from 'node:vm';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import axios from 'axios';

async function components() {
    const result = await build({
        entryPoints: ['resources\\js\\components\\projects\\RecurringActivities.tsx'],
        bundle: true, platform: 'node', format: 'cjs', packages: 'external', write: false, loader: { '.css': 'empty' },
    });
    const module = { exports: {} };
    const require = createRequire(import.meta.url);
    runInNewContext(result.outputFiles[0].text, {
        module, exports: module.exports,
        require: name => name === 'axios' ? axios : name === 'react'
            ? { ...React, useState: initial => [typeof initial === 'function' ? initial() : initial, () => {}] } : require(name),
    });
    return module.exports;
}

const plain = value => JSON.parse(JSON.stringify(value));
const project = { id: 7, archived_at: null, access: { write: true } };
const config = { currentUserId: 3, users: [{ id: 3, name: 'Owner' }], canCreateAction: true };

test('recurrence defaults to every month and its last day, with typed schedule payloads', async () => {
    const { recurrenceValues, recurrencePayload } = await components();
    const defaults = recurrenceValues();
    assert.equal(defaults.frequency, 'monthly');
    assert.deepEqual(plain(defaults.months), Array.from({ length: 12 }, (_, index) => index + 1));
    assert.equal(defaults.day_of_month, '');
    assert.deepEqual(plain(recurrencePayload(defaults)), {
        frequency: 'monthly', months: plain(defaults.months), weekday: null, day_of_month: null,
    });
    assert.deepEqual(plain(recurrencePayload({ ...defaults, frequency: 'weekly', weekday: '7' })), {
        frequency: 'weekly', months: plain(defaults.months), weekday: 7, day_of_month: null,
    });
    assert.equal(recurrencePayload({ ...defaults, months: [2, 6], day_of_month: '31' }).day_of_month, 31);
    assert.equal(recurrencePayload({ ...defaults, frequency: 'daily', day_of_month: '31' }).day_of_month, null);
});

test('recurring activity forms expose the correct schedule controls without fixed start and due dates', async () => {
    const { RecurringActivityForm } = await components();
    for (const frequency of ['daily', 'weekly', 'monthly']) {
        const item = { id: 4, name: 'Review', frequency, weekday: 7, months: [2, 6], day_of_month: null };
        const markup = renderToStaticMarkup(RecurringActivityForm({
            item, project, config, run: async () => false, onSaved() {}, onCancel() {},
        }));
        assert.doesNotMatch(markup, /type="date"/);
        assert.match(markup, /Estimated cost/);
        assert.match(markup, /Estimated hours/);
        if (frequency === 'weekly') {
            assert.match(markup, /Weekday/);
            assert.match(markup, /value="7" selected="">Sunday/);
        } else assert.doesNotMatch(markup, /Weekday/);
        if (frequency === 'monthly') {
            assert.match(markup, /Last day of the month/);
            assert.match(markup, /value="2" selected="">February/);
            assert.match(markup, /value="6" selected="">June/);
        } else assert.doesNotMatch(markup, /Day of month/);
    }
});

test('recurring forms create and update through CRUD without sending activity-only fields', async () => {
    const { RecurringActivityForm } = await components();
    const original = axios.defaults.adapter;
    const requests = [];
    axios.defaults.adapter = async config => {
        requests.push(config);
        return { data: {}, status: 200, statusText: 'OK', headers: {}, config };
    };
    try {
        let saves = 0;
        const props = { project, config, run: async operation => { await operation(); return true; }, onSaved() { saves++; }, onCancel() {} };
        await RecurringActivityForm(props).props.onSubmit({ preventDefault() {} });
        await RecurringActivityForm({ ...props, item: {
            id: 4, name: 'Review', description: 'Details', responsible_user_id: null,
            frequency: 'weekly', weekday: 2, months: [1, 6], day_of_month: null,
            estimated_cost: '12.50', estimated_hours: 3,
        } }).props.onSubmit({ preventDefault() {} });
        assert.equal(saves, 2);
        assert.deepEqual(requests.map(({ method, url }) => [method, url]), [
            ['post', '/api/v1/crud/RecurringProjectActivity'], ['patch', '/api/v1/crud/RecurringProjectActivity/4'],
        ]);
        const created = JSON.parse(requests[0].data);
        const updated = JSON.parse(requests[1].data);
        assert.equal(created.project_id, 7);
        assert.equal(created.day_of_month, null);
        assert.equal(updated.weekday, 2);
        assert.equal(updated.responsible_user_id, null);
        assert.equal(updated.estimated_cost, 12.5);
        for (const payload of [created, updated]) {
            for (const key of ['startdate', 'due', 'risks', 'completed_at', 'last_generated_on']) assert.equal(key in payload, false, key);
        }
        await RecurringActivityForm({ ...props, run: async () => false }).props.onSubmit({ preventDefault() {} });
        assert.equal(saves, 2);
    } finally {
        axios.defaults.adapter = original;
    }
});

test('recurring activity creation is hidden from read-only and archived projects', async () => {
    const { RecurringActivities } = await components();
    const render = project => renderToStaticMarkup(React.createElement(RecurringActivities, { project, config, run: async () => true }));
    assert.match(render(project), /Add recurring activity/);
    assert.doesNotMatch(render({ ...project, access: { write: false } }), /Add recurring activity/);
    assert.doesNotMatch(render({ ...project, archived_at: '2026-10-26' }), /Add recurring activity/);
});
