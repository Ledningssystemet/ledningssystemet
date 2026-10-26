import assert from 'node:assert/strict';
import { test } from 'node:test';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { runInNewContext } from 'node:vm';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import axios from 'axios';

async function components(state) {
    const result = await build({
        entryPoints: ['resources\\js\\components\\projects\\AnnualWheel.tsx'],
        bundle: true, platform: 'node', format: 'cjs', packages: 'external', write: false, loader: { '.css': 'empty' },
    });
    const module = { exports: {} };
    const require = createRequire(import.meta.url);
    runInNewContext(result.outputFiles[0].text, {
        module, exports: module.exports, Intl, Date, AbortController,
        require: name => name === 'axios' ? axios : name === 'react' && state
            ? { ...React, useState: initial => [state(typeof initial === 'function' ? initial() : initial), () => {}], useEffect: () => {} } : require(name),
    });
    return module.exports;
}

const plain = value => JSON.parse(JSON.stringify(value));
const projects = [
    { id: 7, name: 'Safe care', responsible_user_id: 3, users: [], archived_at: null },
    { id: 8, name: 'Shorter queues', responsible_user_id: 9, users: [{ id: 3 }], archived_at: null },
    { id: 9, name: 'Not mine', responsible_user_id: 9, users: [], archived_at: null },
    { id: 10, name: 'Archived', responsible_user_id: 3, users: [], archived_at: '2026-01-01' },
];
const activities = [
    { id: 1, project_id: 7, name: 'Risk analysis', frequency: 'monthly', weekday: null, months: [1, 6], day_of_month: null },
    { id: 2, project_id: 8, name: 'Team stand-up', frequency: 'weekly', weekday: 1, months: [1], day_of_month: null },
    { id: 3, project_id: 7, name: 'Daily check', frequency: 'daily', weekday: null, months: [1], day_of_month: null },
];

test('occurrences per month follow the recurrence schedule for the given year', async () => {
    const { occurrencesInMonth } = await components();
    assert.equal(occurrencesInMonth(activities[0], 2026, 1), 1);
    assert.equal(occurrencesInMonth(activities[0], 2026, 2), 0);
    // Weekly and daily recurrences ignore the months setting, matching the scheduler.
    assert.equal(occurrencesInMonth(activities[1], 2026, 6), 5);
    assert.equal(occurrencesInMonth(activities[1], 2026, 2), 4);
    assert.equal(occurrencesInMonth(activities[2], 2024, 2), 29);
    assert.equal(occurrencesInMonth(activities[2], 2026, 2), 28);
});

test('wheel entries group activities per month ordered by project and show multiple occurrences in parentheses', async () => {
    const { wheelEntries, wheelLabel } = await components();
    const entries = plain(wheelEntries(activities, 2026, [8, 7]));
    assert.equal(entries.length, 12);
    assert.deepEqual(entries[0].map(entry => [entry.name, entry.count]), [['Team stand-up', 4], ['Daily check', 31], ['Risk analysis', 1]]);
    assert.deepEqual(entries[1].map(entry => entry.name), ['Team stand-up', 'Daily check']);
    assert.equal(wheelLabel(entries[0][0]), 'Team stand-up (4)');
    assert.equal(wheelLabel(entries[0][2]), 'Risk analysis');
    assert.equal(wheelLabel({ name: 'x'.repeat(40), count: 2 }), `${'x'.repeat(31)}… (2)`);
});

test('schedule text describes daily, weekly and monthly recurrences', async () => {
    const { scheduleText } = await components();
    assert.equal(scheduleText({ frequency: 'daily' }), 'Daily');
    assert.equal(scheduleText({ frequency: 'weekly', weekday: 3 }), 'Weekly, wednesday');
    assert.equal(scheduleText({ frequency: 'monthly', months: [6, 1], day_of_month: 15 }), 'Monthly: January, June. Day of month 15');
    assert.equal(scheduleText({ frequency: 'monthly', months: Array.from({ length: 12 }, (_, i) => i + 1), day_of_month: null }), 'Monthly: Every month. Last day of the month');
});

test('clicking an activity opens an information box with project, responsible, description and schedule', async () => {
    const entry = { id: 2, name: 'Team stand-up', project_id: 8, month: 6, count: 5 };
    const { Wheel, wheelEntries } = await components(initial => initial === null ? { key: '2-6', entry, x: 70, y: 80 } : initial);
    const markup = renderToStaticMarkup(React.createElement(Wheel, {
        entries: wheelEntries(activities, 2026), projects: projects.slice(0, 2), year: 2026, today: new Date(2026, 9, 26), locale: 'en',
        details: () => ({ project: 'Shorter queues', responsible: 'Owner', description: 'Line one\n<b>two</b>', schedule: 'Weekly, monday' }),
    }));
    assert.match(markup, /role="group" aria-label="Year wheel 2026"/);
    assert.match(markup, /class="pm-wheel-entry" role="button" tabindex="0" aria-label="Risk analysis/);
    assert.match(markup, /class="pm-wheel-entry pm-wheel-entry-active" role="button" tabindex="0" aria-label="Team stand-up \(5\) – Shorter queues, June" aria-expanded="true"/);
    assert.match(markup, /class="pm-wheel-info" role="dialog" aria-label="Team stand-up" style="left:70%;top:80%;transform:translate\(calc\(-100% - 10px\), calc\(-100% - 10px\)\)"/);
    assert.match(markup, /<dt>Project<\/dt><dd>Shorter queues<\/dd><dt>Responsible<\/dt><dd>Owner<\/dd>/);
    assert.ok(markup.includes('Line one\n&lt;b&gt;two&lt;/b&gt;'));
    assert.match(markup, /<dt>Schedule<\/dt><dd>Weekly, monday<div class="text-muted">June: 5 times<\/div><\/dd>/);
    assert.match(markup, /aria-label="Close"/);

    const { Wheel: Closed } = await components(initial => initial);
    const closed = renderToStaticMarkup(React.createElement(Closed, { entries: wheelEntries(activities, 2026), projects, year: 2026 }));
    assert.doesNotMatch(closed, /pm-wheel-info|role="button"/);
});

test('the year wheel loads recurring activities for participating projects and renders all of them by default', async () => {
    const requests = [];
    const original = axios.defaults.adapter;
    axios.defaults.adapter = async config => { requests.push(config); return { data: [], status: 200, statusText: 'OK', headers: {}, config }; };
    try {
        const { wheelActivities } = await components();
        await wheelActivities([7, 8]);
        assert.equal(requests[0].url, '/api/v1/crud/RecurringProjectActivity');
        assert.equal(requests[0].params['filter[project_id]'], '7,8');
        assert.equal(requests[0].params['fields[recurring_project_activities]'],
            'id,project_id,name,description,responsible_user_id,responsible_user_name,frequency,weekday,months,day_of_month');
        assert.equal(requests.length, 1);
        assert.deepEqual(plain(await wheelActivities([])), []);
        assert.equal(requests.length, 1);
    } finally {
        axios.defaults.adapter = original;
    }

    const { AnnualWheel } = await components(initial => initial === null && !globalThis.loaded ? (globalThis.loaded = true, activities) : initial);
    try {
        const markup = renderToStaticMarkup(React.createElement(AnnualWheel, {
            projects, config: { currentUserId: 3, canReadActions: true }, today: new Date(2026, 9, 26),
        }));
        assert.match(markup, /All projects/);
        assert.match(markup, /Only recurring project activities are shown/);
        assert.ok(markup.indexOf('pm-wheel-description') < markup.indexOf('pm-wheel-toolbar'));
        assert.match(markup, /role="group" aria-label="Year wheel 2026"/);
        assert.match(markup, /class="pm-wheel-entry" role="button" tabindex="0" aria-label="Team stand-up \(4\) – Shorter queues, January"/);
        assert.match(markup, /pm-wheel-sector-current/);
        assert.ok(markup.includes('Team stand-up (4)'));
        assert.ok(markup.includes('Daily check (31)'));
        assert.match(markup, />Risk analysis</);
        assert.match(markup, /26 activities/);
        assert.doesNotMatch(markup, /Not mine|Archived/);
    } finally {
        delete globalThis.loaded;
    }
});

test('the project picker filters the wheel and offers show all and select search results', async () => {
    const { ProjectPicker } = await components(initial => initial === false ? true : initial);
    const changes = [];
    const render = selected => ProjectPicker({ projects: projects.slice(0, 2), counts: { 7: 2 }, selected, onChange: value => changes.push(value) });
    const markup = renderToStaticMarkup(React.createElement(() => render(null)));
    assert.match(markup, /Search projects…/);
    assert.match(markup, /aria-pressed="true"[^>]*>.*Safe care.*>2<\/span>/);
    assert.match(markup, /Shorter queues<\/span><span class="pm-wheel-picker-count">0/);
    assert.match(markup, /Show all/);
    assert.match(markup, /Select search results/);
    assert.match(renderToStaticMarkup(React.createElement(() => render([8]))), /Shorter queues<span class="material-symbols-rounded/);
    assert.match(renderToStaticMarkup(React.createElement(() => render([]))), /No projects selected/);
});
