import assert from 'node:assert/strict';
import { test, afterEach } from 'node:test';
import { readFileSync } from 'node:fs';
import axios from 'axios';
import { build } from 'esbuild';
import { createRequire } from 'node:module';
import { runInNewContext } from 'node:vm';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { action, canWriteProject, collection, configuration, confirmAction, confirmCommentAction, create, dateValue, errorMessage, item, mentionUrl, projectList, projectMeetingLink, remove, update } from '../../resources/js/components/projects/api.ts';

const originalAdapter = axios.defaults.adapter;
afterEach(() => { axios.defaults.adapter = originalAdapter; });

test('mention URLs preserve project, meeting and mention IDs and encode the agenda anchor', () => {
    const url = mentionUrl({ id: 21, project_id: 7, project_meeting_id: 3, agenda_item_id: 'section / 1' });
    assert.equal(url, '/projects?project=7&meeting=3&mention=21#meeting-section-section%20%2F%201');
    assert.deepEqual(projectMeetingLink(new URL(url, 'https://example.test').search), {
        projectId: 7, meetingId: 3, mentionId: 21,
    });
    for (const invalid of ['', '?project=7', '?project=7&meeting=3', '?project=-1&meeting=3&mention=21',
        '?project=7&meeting=0&mention=21', '?project=7&meeting=3&mention=invalid',
        '?project=7&meeting=3&mention=9007199254740992']) {
        assert.equal(projectMeetingLink(invalid), null);
    }
});

test('project confirmations use the shared utils dialog and only act after confirmation', () => {
    const originalWindow = globalThis.window;
    let dialog;
    let actions = 0;
    globalThis.window = {
        translateString: key => `translated:${key}`,
        confirmDialog: (title, message, onConfirm, options) => { dialog = { title, message, onConfirm, options }; },
    };
    try {
        confirmAction('The risks related to the project will also be deleted', () => { actions++; return true; }, 'Delete project');
        assert.equal(actions, 0);
        assert.equal(dialog.title, 'translated:Delete project');
        assert.deepEqual(dialog.options, { warning: true });
        assert.equal(dialog.message, 'translated:The risks related to the project will also be deleted');
        assert.equal(dialog.onConfirm(), undefined);
        assert.equal(actions, 1);
        confirmAction('Are you sure that you want to delete this risk?', () => { actions++; }, 'Delete risk');
        assert.equal(dialog.title, 'translated:Delete risk');
        assert.equal(dialog.message, 'translated:Are you sure that you want to delete this risk?');
        assert.deepEqual(dialog.options, { warning: true });
        assert.equal(actions, 1);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('comment confirmations preserve existing text and only submit after confirmation', () => {
    const originalWindow = globalThis.window;
    let dialog;
    const saved = [];
    globalThis.window = {
        translateString: key => `translated:${key}`,
        confirmCommentDialog: (title, message, comment, onConfirm, options) => { dialog = { title, message, comment, onConfirm, options }; },
    };
    try {
        confirmCommentAction('Approve this object?', 'Existing comment', comment => saved.push(comment), 'Approve');
        assert.equal(dialog.comment, 'Existing comment');
        assert.equal(dialog.title, 'translated:Approve');
        assert.deepEqual(dialog.options, { warning: true });
        assert.deepEqual(saved, []);
        dialog.onConfirm('');
        assert.deepEqual(saved, ['']);
        confirmCommentAction('Approve this object?', null, comment => saved.push(comment), 'Approve');
        assert.equal(dialog.comment, '');
        assert.deepEqual(saved, ['']);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('legacy completion and approval views prefill and submit assessment comments', () => {
    const views = [
        ['resources/views/user/activities.blade.php', /userFinishActivity\(data\.record\.id, data\.record\.assessmentcomment/],
        ['resources/views/management/activities.blade.php', /userFinishActivity\(data\.record\.id, data\.record\.assessmentcomment/],
        ['resources/views/assessment/risktablefields.blade.php', /confirmCommentDialog\([\s\S]*?data\.record\.assessmentcomment[\s\S]*?ajaxPost\([\s\S]*?assessmentcomment: assessmentcomment/],
        ['resources/views/assessment/controlactions.blade.php', /confirmCommentDialog\([\s\S]*?data\.record\.assessmentcomment[\s\S]*?ajaxPatch\([\s\S]*?assessmentcomment: assessmentcomment/],
        ['resources/views/user/controlactions.blade.php', /confirmCommentDialog\([\s\S]*?data\.record\.assessmentcomment[\s\S]*?ajaxPatch\([\s\S]*?assessmentcomment: assessmentcomment/],
        ['resources/views/inventory/requirements.blade.php', /confirmCommentDialog\([\s\S]*?data\.record\.assessmentcomment[\s\S]*?ajaxPost\([\s\S]*?assessmentcomment: assessmentcomment/],
    ];
    for (const [path, binding] of views) assert.match(readFileSync(path, 'utf8'), binding, path);
    assert.match(readFileSync('resources/js/utils.js', 'utf8'), /ajaxPost\('\/api\/v1\/items\/Activity\/'\+id\+'\/completed', \{ assessmentcomment: assessmentcomment \}/);
});

function respond(data, requests = []) {
    axios.defaults.adapter = async config => {
        requests.push(config);
        return { data, status: 200, statusText: 'OK', headers: {}, config };
    };
}

test('CRUD collections accept arrays and Laravel collections with preserved keys', async () => {
    const rows = [{ id: 2, name: 'Project' }];
    respond(rows);
    assert.deepEqual(await collection('Project'), rows);
    respond({ 4: rows[0] });
    assert.deepEqual(await collection('Project'), rows);
    respond({});
    assert.deepEqual(await collection('Project'), []);
    respond({ message: 'Unexpected payload' });
    await assert.rejects(collection('Project'), /Unexpected API response/);
});

test('project configuration is composed from CRUD lists and project-specific select fields', async () => {
    const requests = [];
    const dataByUrl = {
        '/api/v1/crud/User': [{ id: 3, name: 'Owner', enabled: true, access_groups: [{ id: 8, name: 'Risk owner' }] }],
        '/api/v1/crud/ProjectType': [{ id: 1, name: 'Type' }],
        '/api/v1/crud/Control': [{ id: 2, name: 'Control' }],
        '/api/v1/crud/ProbabilityLevel': [{ id: 4, name: 'Likely', ordinal: 2 }],
        '/api/v1/crud/ConsequenceLevel': [{ id: 5, name: 'Major', ordinal: 1 }],
        '/api/v1/crud/AccessGroup': [{ id: 8, risk_level_id: 6 }],
        '/api/v1/crud/RiskLevel': [{
            id: 6, name: 'High', color: 'ff0000', ordinal: 3,
            risk_mappings: [{ probability_level_id: 4, consequence_level_id: 5 }],
        }],
    };
    axios.defaults.adapter = async config => {
        requests.push(config);
        return { data: dataByUrl[config.url], status: 200, statusText: 'OK', headers: {}, config };
    };
    const permissions = {
        currentUserId: 3, canCreateProject: true, canCreateRisk: true,
        canReadRisks: true, canReadActions: true, canCreateAction: true,
    };
    const result = await configuration(permissions);
    assert.deepEqual(result.users, [{ id: 3, name: 'Owner', enabled: true }]);
    assert.deepEqual(result.riskOwners, [{ id: 3, name: 'Owner' }]);
    assert.deepEqual(result.riskMappings, [{
        probability_level_id: 4, consequence_level_id: 5, color: 'ff0000', name: 'High',
    }]);
    assert.equal(requests.length, 7);
    assert.ok(requests.every(request => request.url.startsWith('/api/v1/crud/')));
    assert.deepEqual(requests.find(request => request.url.endsWith('/AccessGroup')).params, {
        'fields[access_groups]': 'id,risk_level_id',
    });
    assert.deepEqual(requests.find(request => request.url.endsWith('/RiskLevel')).params, {
        'fields[risk_levels]': 'id,name,color,ordinal,risk_mappings',
    });
});

test('project list uses selected CRUD fields and project details use the CRUD item route', async () => {
    const requests = [];
    respond([{ id: 7, name: 'Project' }], requests);
    const controller = new AbortController();
    await projectList(controller.signal);
    await item('Project', 7, controller.signal);
    assert.deepEqual(requests.map(({ url }) => url), ['/api/v1/crud/Project', '/api/v1/crud/Project/7']);
    assert.deepEqual(requests[0].params, {
        'fields[projects]': 'id,name,description,responsible_user_id,responsible_user_name,start_date,end_date,archived_at,maxrisklevel,users',
        sort: 'name',
    });

    assert.equal(requests[0].signal, controller.signal);
    assert.equal(requests[1].signal, controller.signal);
});

test('project write access comes from project membership and is disabled for archived projects', () => {
    const project = {
        responsible_user_id: 1,
        archived_at: null,
        users: [{ id: 2, name: 'Writer', readonly: false }, { id: 3, name: 'Reader', readonly: true }],
    };
    assert.equal(canWriteProject(project, 1), true);
    assert.equal(canWriteProject(project, 2), true);
    assert.equal(canWriteProject(project, 3), false);
    assert.equal(canWriteProject({ ...project, archived_at: '2026-10-25' }, 1), false);
    assert.equal(canWriteProject({ ...project, archived_at: '2026-10-25' }, 2), false);
});

test('project risks and activities use the project filters and cancellation signals', async () => {
    const requests = [];
    respond([], requests);
    const controller = new AbortController();
    await collection('ProjectRisk', { 'filter[project_id]': 7 }, controller.signal);
    await collection('ProjectActivity', { 'filter[project_id]': 7 });
    assert.equal(requests[0].url, '/api/v1/crud/ProjectRisk');
    assert.deepEqual(requests[0].params, { 'filter[project_id]': 7 });
    assert.equal(requests[0].signal, controller.signal);
    assert.equal(requests[1].url, '/api/v1/crud/ProjectActivity');
    assert.deepEqual(requests[1].params, { 'filter[project_id]': 7 });
});

test('mutations use persisted server IDs and server actions rather than mock state', async () => {
    const requests = [];
    respond({}, requests);
    await create('Project', { name: 'New project' });
    await update('ProjectRisk', 9, { probability_id: 42, consequence_id: 27 });
    await action('ProjectRisk', 9, 'approve');
    await action('Project', 7, 'archive');
    await remove('ProjectActivity', 12);
    assert.deepEqual(requests.map(({ method, url }) => [method, url]), [
        ['post', '/api/v1/crud/Project'],
        ['patch', '/api/v1/crud/ProjectRisk/9'],
        ['post', '/api/v1/crud/ProjectRisk/9/approve'],
        ['post', '/api/v1/crud/Project/7/archive'],
        ['delete', '/api/v1/crud/ProjectActivity/12'],
    ]);
    assert.deepEqual(JSON.parse(requests[1].data), { probability_id: 42, consequence_id: 27 });
});

test('API validation and authorization errors are surfaced', () => {
    const error = new axios.AxiosError('Request failed');
    error.response = { data: { errors: { name: ['Name is required'], due: ['Due is required'] } } };
    assert.equal(errorMessage(error), 'Name is required Due is required');
    error.response = { data: { message: 'Forbidden' } };
    assert.equal(errorMessage(error), 'Forbidden');
    assert.equal(errorMessage(new Error('Network unavailable')), 'Network unavailable');
    assert.equal(dateValue('2026-10-24 10:20:30'), '2026-10-24');
    assert.equal(dateValue(null), '');
});

test('project views use configured risk levels and hide unsupported or unauthorized operations', async () => {
    const result = await build({
        entryPoints: ['resources\\js\\components\\projects\\Views.tsx'],
        bundle: true, platform: 'node', format: 'cjs', packages: 'external', write: false, loader: { '.css': 'empty' },
    });
    const module = { exports: {} };
    runInNewContext(result.outputFiles[0].text, { module, exports: module.exports, require: createRequire(import.meta.url) });
    const { Overview, ProjectForm, ProjectHeader, Risks, Team } = module.exports;
    const config = {
        currentUserId: 3, canCreateRisk: true, canReadRisks: true, canReadActions: false,
        users: [{ id: 3, name: 'Project owner' }], projectTypes: [],
        probabilities: [{ id: 42, name: 'Configured probability' }],
        consequences: [{ id: 27, name: 'Configured consequence' }],
        riskMappings: [{ probability_level_id: 42, consequence_level_id: 27, name: 'Configured level', color: '123456' }],
    };
    const project = {
        id: 7, name: 'Project', description: null, start_date: '2026-10-24', responsible_user_id: 3,
        end_date: '2026-12-31', riskcount: 2, users: [{ id: 4, name: 'Participant' }],
        riskdistribution: { 42: { 27: { count: 2 } } }, access: { update: false },
    };
    const overview = renderToStaticMarkup(React.createElement(Overview, { project, config, risks: [], actions: [] }));
    assert.match(overview, /Configured probability/);
    assert.match(overview, /Configured consequence/);
    assert.match(overview, /color-mix\(in srgb, #123456 25%, white\)/);
    assert.match(overview, /aria-label="Configured probability \/ Configured consequence: 2"/);
    assert.equal((overview.match(/class="pm-matrix-cell"/g) ?? []).length, 1);
    assert.doesNotMatch(overview, /Planned actions|Möten|Arbetspaket|Gantt/);
    const header = renderToStaticMarkup(React.createElement(ProjectHeader, { project, projects: [project], config, tab: 'overview', loading: false, onSelect: () => {}, onTab: () => {} }));
    assert.match(header, /class="pm-header"/);
    assert.match(header, /class="pm-tab" aria-current="page"><span[^>]*aria-hidden="true">dashboard<\/span>Overview/);
    assert.match(header, /class="pm-project-select"/);
    assert.match(header, /Meetings/);
    assert.doesNotMatch(header, /My mentions/);
    assert.doesNotMatch(header, /Work packages|Gantt|Demo/);
    assert.doesNotMatch(header, /Show comments|Show history/);
    const closed = renderToStaticMarkup(React.createElement(Risks, {
        project: { ...project, archived_at: '2026-10-24' }, config, risks: [], actions: [], run: async () => true, revision: 0,
    }));
    assert.doesNotMatch(closed, /Add new risk/);
    const team = renderToStaticMarkup(React.createElement(Team, { project, config }));
    assert.match(team, /Participant/);
    assert.doesNotMatch(team, /<select|<button/);
    const form = renderToStaticMarkup(React.createElement(ProjectForm, {
        config, run: async () => true, onSaved: () => {}, onCancel: () => {},
    }));
    assert.match(form, /type="date"/);
    assert.doesNotMatch(form, /Participants/);
    assert.match(form, /Description/);
});

test('risk owner options are built only from project members', async () => {
    const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const markup = renderToStaticMarkup(React.createElement(RiskForm, {
        project: {
            ...project,
            users: [{ id: 4, name: 'Participant', readonly: false }],
        },
        config: {
            ...config,
            canReadActions: false,
            riskOwners: [
                { id: 3, name: 'Project owner' },
                { id: 6, name: 'Not in project' },
            ],
            users: [
                { id: 3, name: 'Project owner' },
                { id: 4, name: 'Participant' },
                { id: 5, name: 'Not eligible' },
                { id: 6, name: 'Not in project' },
            ],
        },
        run: async () => true, onSaved: () => {}, onCancel: () => {},
    }));
    assert.match(markup, /<option value="3" selected="">Project owner<\/option>/);
    assert.match(markup, /<option value="4">Participant<\/option>/);
    assert.doesNotMatch(markup, /Not eligible|Not in project/);
});

async function loadComponents(entry, state, setState = () => {}, hooks = {}) {
    const result = await build({
        entryPoints: [entry], bundle: true, platform: 'node', format: 'cjs', packages: 'external', write: false, loader: { '.css': 'empty' },
    });

    const module = { exports: {} };
    const require = createRequire(import.meta.url);
    runInNewContext(result.outputFiles[0].text, {
        module, exports: module.exports, Error, AbortController, URLSearchParams, window: globalThis.window, ResizeObserver: globalThis.ResizeObserver,
        require: name => name === 'axios' ? axios : name === 'react' && state
            ? { ...React, useState: initial => [state(typeof initial === 'function' ? initial() : initial), setState], useRef: initial => ({ current: initial }), ...hooks }
            : require(name),
    });
    return module.exports;
}

test('shared UI renders outside the project module and has no project dependencies', async () => {
    const ui = await loadComponents('resources\\js\\components\\ui\\index.tsx');
    const markup = renderToStaticMarkup(React.createElement(ui.Card, { className: 'shared-example' },
        React.createElement(ui.SectionTitle, { title: 'Shared form' }),
        React.createElement(ui.Label, { text: 'Name' }, React.createElement(ui.Input, { name: 'name', required: true })),
        React.createElement(ui.Select, { defaultValue: '1' },
            React.createElement(ui.Options, { items: [{ id: 1, name: 'Choice' }] })),
        React.createElement(ui.Textarea, { defaultValue: 'Description' }),
        React.createElement(ui.Badge, { tone: 'ok' }, 'Ready'),
        React.createElement(ui.Btn, { variant: 'warning', disabled: true },
            React.createElement(ui.Icon, { name: 'archive' }), 'Archive'),
        React.createElement(ui.Btn, { variant: 'danger', type: 'submit' }, 'Delete'),
    ));
    assert.doesNotMatch(markup, /projects-module/);
    assert.match(markup, /class="ls-card shared-example"/);
    assert.match(markup, /<h2 class="ls-section-title">Shared form<\/h2>/);
    assert.match(markup, /<input[^>]*required=""[^>]*name="name"/);
    assert.match(markup, /<option value="">None<\/option>/);
    assert.match(markup, /<option value="1" selected="">Choice<\/option>/);
    assert.match(markup, /ls-badge-ok/);
    assert.match(markup, /disabled="" type="button" class="ls-button ls-button-warning/);
    assert.match(markup, /type="submit" class="ls-button ls-button-danger/);
    assert.match(markup, /aria-hidden="true">archive<\/span>/);
    const bundle = await build({
        entryPoints: ['resources\\js\\components\\ui\\index.tsx'],
        bundle: true, packages: 'external', write: false, metafile: true, loader: { '.css': 'empty' },
    });
    assert.ok(Object.keys(bundle.metafile.inputs).every(path => !path.includes('/projects/')));
});

test('listing projects only fetches projects by default; lookups are deferred to opening or creating', async () => {
    const summary = {
        id: 7, name: 'Project', responsible_user_id: 3, responsible_user_name: 'Project owner',
        users: [], start_date: '2026-10-24', end_date: null, archived_at: null, maxrisklevel: null,
    };
    const permissions = {
        currentUserId: 3, canCreateProject: true, canReadRisks: true, canReadActions: true,
        canReadWorkPackages: true, canCreateRisk: true, canCreateAction: true, canCreateWorkPackage: true,
    };
    for (const mode of ['list', 'open', 'create', 'cached']) {
        const requests = [];
        const effects = [];
        axios.defaults.adapter = async request => {
            requests.push(request);
            const data = request.url === '/api/v1/crud/Project/7' ? summary
                : request.url === '/api/v1/crud/Project' ? [summary] : [];
            return { data, status: 200, statusText: 'OK', headers: {}, config: request };
        };
        let stateIndex = 0;
        const { ProjectsView } = await loadComponents('resources\\js\\components\\projects\\index.tsx', initial => {
            const index = stateIndex++;
            if (index === 0) return [summary];
            if (index === 1) return mode === 'open' || mode === 'cached' ? summary.id : null;
            if (index === 6) return mode === 'create';
            if (index === 11) return false;
            return initial === 'gantt' ? 'none' : initial;
        }, () => {}, { useEffect: effect => effects.push(effect) });
        const markup = renderToStaticMarkup(React.createElement(ProjectsView, {
            config: mode === 'cached' ? { ...permissions, users: [], projectTypes: [], riskOwners: [],
                controls: [], probabilities: [], consequences: [], riskMappings: [] } : permissions,
        }));
        const cleanups = effects.map(effect => effect());
        await new Promise(resolve => setImmediate(resolve));
        const urls = requests.map(request => request.url);
        if (mode === 'list') {
            assert.deepEqual(urls, ['/api/v1/crud/Project']);
            assert.match(markup, /Project owner/);
            assert.match(markup, /<option value="3">Project owner<\/option>/);
        } else {
            assert.equal(urls.includes('/api/v1/crud/User'), mode !== 'cached');
            assert.equal(urls.includes('/api/v1/crud/ProjectType'), mode !== 'cached');
            assert.equal(urls.includes('/api/v1/crud/Project/7'), mode !== 'create');
            assert.equal(urls.includes('/api/v1/crud/ProjectRisk'), mode !== 'create');
            assert.equal(urls.includes('/api/v1/crud/ProjectWorkPackage'), mode !== 'create');
        }
        cleanups.forEach(cleanup => cleanup?.());
    }
});

test('project visualization buttons default to Gantt and switch between none, Gantt and the year wheel', async () => {
    const summary = {
        id: 7, name: 'Project', responsible_user_id: 3, users: [],
        start_date: '2026-10-24', end_date: null, archived_at: null, maxrisklevel: null,
    };
    for (const mode of ['default', 'gantt', 'none', 'year-wheel']) {
        const requests = [];
        const effects = [];
        respond([summary], requests);
        let stateIndex = 0;
        const { ProjectsView } = await loadComponents('resources\\js\\components\\projects\\index.tsx', initial => {
            const index = stateIndex++;
            if (index === 0) return [summary];
            if (index === 11) return false;
            return initial === 'gantt' && mode !== 'default' ? mode : initial;
        }, () => {}, { useEffect: effect => effects.push(effect) });
        const markup = renderToStaticMarkup(React.createElement(ProjectsView, {
            config: { currentUserId: 3, canReadActions: true },
        }));
        assert.match(markup, /aria-label="Project visualization"/);
        assert.match(markup, /class="pm-visualization-buttons" role="group"/);
        const buttons = [...markup.matchAll(/<button aria-pressed="(true|false)"[^>]*>(No overview|Gantt chart|Year wheel)<\/button>/g)];
        assert.deepEqual(buttons.map(button => button[2]), ['No overview', 'Gantt chart', 'Year wheel']);
        assert.deepEqual(buttons.map(button => button[1]), [
            mode === 'none' ? 'true' : 'false',
            mode === 'gantt' || mode === 'default' ? 'true' : 'false',
            mode === 'year-wheel' ? 'true' : 'false',
        ]);
        assert.match(markup, /pm-project-list/);
        assert.equal(markup.includes('Loading your project timeline'), mode === 'gantt' || mode === 'default');
        assert.equal(markup.includes('Loading the year wheel'), mode === 'year-wheel');
        const cleanups = effects.map(effect => effect());
        await new Promise(resolve => setImmediate(resolve));
        assert.equal(requests.some(request => request.url.endsWith('/ProjectActivity')), mode === 'gantt' || mode === 'default');
        assert.equal(requests.some(request => request.url.endsWith('/RecurringProjectActivity')), mode === 'year-wheel');
        cleanups.forEach(cleanup => cleanup?.());
    }
});

test('project descriptions render in the header and project list with preserved line breaks and escaped text', async () => {
    const description = 'First line\nSecond line\n\n<script>not HTML</script>';
    const item = {
        ...project, name: 'Project', description, start_date: '2026-10-24',
        users: [], riskcount: 97, maxrisklevel: null,
    };
    const { ProjectHeader } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const { ProjectsView } = await loadComponents('resources\\js\\components\\projects\\index.tsx',
        initial => Array.isArray(initial) ? [item, { ...item, id: 8, name: 'Second project', description: null, maxrisklevel: { name: 'High', color: 'ffccaa' } }] : initial === true ? false : initial === 'gantt' ? 'none' : initial);
    const header = renderToStaticMarkup(React.createElement(ProjectHeader, {
        project: item, projects: [item], config, tab: 'overview', loading: false, onSelect() {}, onTab() {},
    }));
    const list = renderToStaticMarkup(React.createElement(ProjectsView, { config }));
    assert.match(list, /class="pm-project-rows" aria-label="Projects"/);
    assert.equal((list.match(/class="pm-project-row"/g) ?? []).length, 2);
    assert.match(list, /Second project/);
    assert.match(list, />High<\/span>/);
    assert.match(list, /Search/);
    assert.match(list, /Show only my projects/);
    assert.ok(list.indexOf('pm-visualization-buttons') < list.indexOf('class="ls-card pm-project-list"'));
    assert.doesNotMatch(list, /Loading your project timeline/);
    assert.doesNotMatch(list, />97</);
    assert.doesNotMatch(list, /col-md-6 col-xl-4|Risks:|Show comments|Show history/);
    for (const markup of [header, list]) {
        assert.match(markup, /white-space:pre-wrap/);
        assert.ok(markup.includes('First line\nSecond line\n\n&lt;script&gt;not HTML&lt;/script&gt;'));
        assert.doesNotMatch(markup, /<script>/);
    }
});

test('project detail tabs only render after complete data for the selected project is available', async () => {
    const summary = {
        ...project, name: 'Selected project', description: null, start_date: '2026-10-24',
        end_date: null, archived_at: null, maxrisklevel: null,
    };
    const detail = { ...summary, project_type_id: null, users: [], riskdistribution: {}, riskcount: 0 };
    let stateIndex = 0;
    let states = [];
    const { ProjectsView } = await loadComponents(
        'resources\\js\\components\\projects\\index.tsx',
        initial => stateIndex < states.length ? states[stateIndex++] : initial,
    );
    const render = (tab, projectDetail, detailLoading = false, detailError = null) => {
        stateIndex = 0;
        states = [
            [summary], summary.id, projectDetail, [], [], tab, false, '', '', false, false,
            false, detailLoading, false, null, detailError, 0,
        ];
        return renderToStaticMarkup(React.createElement(ProjectsView, {
            config: { ...config, riskMappings: [] },
        }));
    };
    for (const tab of ['overview', 'risks', 'actions', 'work-packages', 'gantt', 'team', 'settings']) {
        for (const pendingDetail of [null, { ...detail, id: 8 }]) {
            const markup = render(tab, pendingDetail);
            assert.match(markup, /role="status">Loading data/);
            assert.doesNotMatch(markup, /class="ls-card|class="pm-project-rows"/);
        }
        const loading = render(tab, detail, true);
        assert.match(loading, /role="status">Loading data/);
        assert.doesNotMatch(loading, /class="ls-card/);
        const failed = render(tab, null, false, 'Could not load project');
        assert.match(failed, /Could not load project/);
        assert.match(failed, /Retry/);
        assert.doesNotMatch(failed, /class="ls-card/);
    }
    assert.match(render('overview', detail), /class="ls-card pm-stat/);
    assert.match(render('team', detail), /Participants/);
    assert.match(render('work-packages', detail), /No work packages/);
    assert.match(render('gantt', detail), /Project Gantt chart/);
});

test('opening a mention URL selects its project and the meetings tab on initial render', async () => {
    const originalWindow = globalThis.window;
    globalThis.window = { location: { search: '?project=7&meeting=3&mention=21' } };
    try {
        const summary = { ...project, name: 'Bridge', start_date: '2026-10-25', archived_at: null, maxrisklevel: null };
        let slot = 0;
        const { ProjectsView } = await loadComponents('resources\\js\\components\\projects\\index.tsx', initial => {
            const index = slot++;
            if (index === 0) return [summary];
            if (index === 2) return summary;
            if (index === 11) return false;
            return initial;
        });
        const markup = renderToStaticMarkup(React.createElement(ProjectsView, { config }));
        assert.match(markup, /Bridge/);
        assert.match(markup, /aria-current="page"[^>]*>.*?Meetings<\/button>/);
        assert.doesNotMatch(markup, /class="pm-project-rows"/);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

const access = { create: true, read: true, update: true, delete: true };
const project = { id: 7, responsible_user_id: 3, users: [], access: { ...access, write: true, manageparticipants: true } };
const risk = {
    id: 9, name: 'Migration risk', project_id: 7, riskowner_id: 3,
    scenariodescription: 'Scenario from the new table', consequencedescription: 'Consequence from the new table',
    probability_id: 42, consequence_id: 27, post_probability_id: 43, post_consequence_id: 28,
    note: 'Migrated note', assessmentcomment: 'Existing assessment comment',
    risk_controls: [{ id: 5, name: 'Control' }], assessed_at: null, risk_level: null, access,
};
const config = {
    currentUserId: 3, canReadRisks: true, canReadActions: true, canCreateRisk: true, canCreateAction: true,
    canReadWorkPackages: true, canCreateWorkPackage: true,
    users: [{ id: 3, name: 'Owner' }], riskOwners: [{ id: 3, name: 'Owner' }],
    controls: [{ id: 5, name: 'Control' }],
    probabilities: [{ id: 42, name: 'Initial probability' }, { id: 43, name: 'Post probability' }],
    consequences: [{ id: 27, name: 'Initial consequence' }, { id: 28, name: 'Post consequence' }],
};

test('work package tab follows Activities and respects read permissions', async () => {
    const { ProjectHeader } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const props = { project, projects: [project], config, tab: 'work-packages', loading: false, onSelect() {}, onTab() {} };
    const markup = renderToStaticMarkup(React.createElement(ProjectHeader, props));
    assert.match(markup, /Activities<\/button><button[^>]*aria-current="page"[^>]*>.*?Work packages<\/button>/);
    assert.doesNotMatch(renderToStaticMarkup(React.createElement(ProjectHeader, {
        ...props, config: { ...config, canReadWorkPackages: false },
    })), /Work packages/);
});

test('Gantt tab follows work packages and requires access to at least one chart section', async () => {
    const { ProjectHeader } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const props = { project, projects: [project], config, tab: 'gantt', loading: false, onSelect() {}, onTab() {} };
    const markup = renderToStaticMarkup(React.createElement(ProjectHeader, props));
    assert.match(markup, /Work packages<\/button><button[^>]*aria-current="page"[^>]*>.*?Gantt<\/button>/);
    assert.doesNotMatch(renderToStaticMarkup(React.createElement(ProjectHeader, {
        ...props, config: { ...config, canReadWorkPackages: false, canReadActions: false },
    })), />Gantt<\/button>/);
    for (const permissions of [{ canReadWorkPackages: true, canReadActions: false }, { canReadWorkPackages: false, canReadActions: true }]) {
        assert.match(renderToStaticMarkup(React.createElement(ProjectHeader, {
            ...props, config: { ...config, ...permissions },
        })), />Gantt<\/button>/);
    }
});

test('personal Gantt only groups ongoing participating projects and the current users activities', async () => {
    const { personalGanttGroups, participatingProjects, personalActivities } = await loadComponents('resources\\js\\components\\projects\\PersonalGantt.tsx');
    const projects = [
        { id: 1, name: 'Owner project', responsible_user_id: 3, users: [], archived_at: null },
        { id: 2, name: 'Participant project', responsible_user_id: 4, users: [{ id: 3, readonly: true }], archived_at: null },
        { id: 3, name: 'Archived project', responsible_user_id: 3, users: [], archived_at: '2026-10-24' },
        { id: 4, name: 'Administrator only access', responsible_user_id: 4, users: [], archived_at: null },
        { id: 5, name: 'No own activities', responsible_user_id: 4, users: [{ id: 3 }], archived_at: null },
    ];
    const activities = [
        { id: 11, project_id: 1, responsible_user_id: 3, name: 'Own work', startdate: '2026-10-01', due: '2026-11-01', completed_at: null },
        { id: 12, project_id: 2, responsible_user_id: 3, name: 'Finished work', startdate: null, due: '2026-10-24', completed_at: '2026-10-24' },
        { id: 13, project_id: 2, responsible_user_id: 4, name: 'Other persons work' },
        { id: 14, project_id: 3, responsible_user_id: 3, name: 'Archived work' },
        { id: 15, project_id: 4, responsible_user_id: 3, name: 'Not participating' },
        { id: 16, project_id: 5, responsible_user_id: null, name: 'Unassigned work' },
    ];
    assert.deepEqual([...participatingProjects(projects, 3)].map(project => project.id), [1, 2, 5]);
    const groups = personalGanttGroups(projects, activities, 3);
    assert.deepEqual([...groups].map(group => group.label), ['Owner project', 'Participant project', 'No own activities']);
    assert.equal(groups[0].rows[0].kind, 'activity');
    assert.equal(groups[0].rows[0].start, '2026-10-01');
    assert.equal(groups[1].rows[0].completed, true);
    assert.equal(groups[1].rows[0].start, null);
    assert.equal(groups[2].rows.length, 0);
    assert.equal(groups[2].emptyMessage, 'No activities assigned to you');
    assert.deepEqual([...groups].flatMap(group => [...group.rows].map(row => row.id)), [11, 12]);
    const requests = [];
    respond(activities.slice(0, 2), requests);
    const controller = new AbortController();
    await personalActivities(projects, 3, controller.signal);
    assert.equal(requests[0].url, '/api/v1/crud/ProjectActivity');
    assert.deepEqual({ ...requests[0].params }, {
        'fields[project_activities]': 'id,name,project_id,responsible_user_id,startdate,due,completed_at',
        'filter[project_id]': '1,2,5', 'filter[responsible_user_id]': 3, sort: 'due',
    });
    assert.equal(requests[0].signal, controller.signal);
    await personalActivities([], 3);
    assert.equal(requests.length, 1);
});

test('personal Gantt displays loading, empty, error and read permission states', async () => {
    const entry = 'resources\\js\\components\\projects\\PersonalGantt.tsx';
    const { PersonalGantt } = await loadComponents(entry);
    const props = { projects: [], config };
    assert.match(renderToStaticMarkup(React.createElement(PersonalGantt, props)), /Loading your project timeline/);
    assert.equal(renderToStaticMarkup(React.createElement(PersonalGantt, {
        ...props, config: { ...config, canReadActions: false },
    })), '');
    let emptyIndex = 0;
    const { PersonalGantt: Empty } = await loadComponents(entry, initial => emptyIndex++ === 0 ? [] : initial);
    assert.match(renderToStaticMarkup(React.createElement(Empty, props)), /not participating in any ongoing projects/);
    let index = 0;
    const { PersonalGantt: Failed } = await loadComponents(entry, initial => [null, 'Timeline failed', 0][index++]);
    const failed = renderToStaticMarkup(React.createElement(Failed, props));
    assert.match(failed, /role="alert"/);
    assert.match(failed, /Timeline failed/);
    assert.match(failed, /Retry/);
    let loadedIndex = 0;
    const { PersonalGantt: Loaded } = await loadComponents(entry, initial => loadedIndex++ === 0 ? [{
        id: 11, project_id: 1, responsible_user_id: 3, name: 'Personal task',
        startdate: '2026-10-01', due: '2026-10-31', completed_at: null,
    }] : initial);
    const loaded = renderToStaticMarkup(React.createElement(Loaded, {
        config, projects: [{ id: 1, name: 'My ongoing project', responsible_user_id: 3, users: [], archived_at: null }],
    }));
    assert.match(loaded, /My project activities/);
    assert.match(loaded, /class="ls-gantt-description text-muted">Only planned project activities are shown\. Recurring activities are shown in the year wheel\./);
    assert.match(loaded, /My ongoing project/);
    assert.match(loaded, /Personal task/);
    assert.match(loaded, /ls-gantt-activity-line/);
    assert.doesNotMatch(loaded, /ls-gantt-bar/);
});

test('shared Gantt renders arbitrary groups outside projects and imports its own styles without project dependencies', async () => {
    const { Gantt } = await loadComponents('resources\\js\\components\\ui\\index.tsx');
    const props = {
        title: 'Delivery schedule', locale: 'sv', today: '2026-10-24',
        ariaLabel: 'Delivery timeline', startDate: '2026-10-01', endDate: '2026-12-31',
        groups: [
            { id: 'deliveries', label: 'Deliveries', rows: [
                { id: 'first', name: 'First delivery', meta: 'Team A', start: '2026-10-01', end: '2026-10-31', kind: 'bar', completed: true },
                { id: 'review', name: 'Review', end: '2026-10-20', kind: 'activity' },
            ] },
            { id: 'empty', label: 'Other work', rows: [], emptyMessage: 'Nothing scheduled' },
        ],
    };
    const markup = renderToStaticMarkup(React.createElement(Gantt, props));
    assert.match(markup, /class="ls-card ls-gantt-card"/);
    assert.match(markup, /aria-label="Delivery timeline"/);
    assert.match(markup, /Delivery schedule/);
    assert.match(markup, /Deliveries/);
    assert.match(markup, /Team A/);
    assert.match(markup, /Nothing scheduled/);
    assert.match(markup, /okt\.? 26/);
    assert.match(markup, /class="ls-gantt-bar ls-gantt-completed"/);
    assert.match(markup, /class="ls-gantt-dot"/);
    assert.doesNotMatch(markup, /projects-module|pm-gantt|Work packages|Project Gantt chart/);
    const empty = renderToStaticMarkup(React.createElement(Gantt, { title: 'Empty schedule', groups: [], today: '2026-10-24' }));
    assert.match(empty, /aria-label="Gantt chart"/);
    assert.match(empty, /Empty schedule/);
    const bundle = await build({
        entryPoints: ['resources\\js\\components\\ui\\Gantt.tsx'],
        bundle: true, packages: 'external', write: false, metafile: true, loader: { '.css': 'empty' },
    });
    const inputs = Object.keys(bundle.metafile.inputs);
    assert.ok(inputs.every(path => !path.includes('/projects/')));
    assert.ok(inputs.some(path => path.endsWith('/ui/gantt.css')));
    assert.ok(inputs.some(path => path.endsWith('/ui/ui.css')));
});

test('Gantt scale uses real calendar days across month, leap-year and year boundaries', async () => {
    const { ganttScale, ganttSpan } = await loadComponents('resources\\js\\components\\ui\\Gantt.tsx');
    const day = date => Date.parse(`${date}T00:00:00Z`) / 86400000;
    const scale = ganttScale(['2026-09-01', '2027-03-31'], '2026-10-24');
    assert.equal(scale.start, day('2026-09-01'));
    assert.equal(scale.end, day('2027-05-01'));
    assert.equal(scale.months.length, 8);
    assert.equal(scale.position(day('2026-09-01')), 0);
    assert.equal(scale.position(day('2027-05-01')), 100);
    assert.equal(scale.months[0].end - scale.months[0].start, 30);
    assert.equal(scale.months[1].end - scale.months[1].start, 31);
    assert.equal(scale.months[5].end - scale.months[5].start, 28);
    const leap = ganttScale(['2028-02-01', '2028-03-01']);
    assert.equal(leap.months[0].end - leap.months[0].start, 29);
    const empty = ganttScale([], '2026-10-24');
    assert.equal(empty.months.length, 2);
    assert.equal(empty.start, day('2026-10-01'));
    assert.equal(empty.end, day('2026-12-01'));
    const sameMonth = ganttScale(['2026-10-10', '2026-10-24'], '2030-01-01');
    assert.equal(sameMonth.start, day('2026-10-01'));
    assert.equal(sameMonth.end, day('2026-12-01'));
    const yearBoundary = ganttScale(['2026-12-01', '2026-12-31']);
    assert.equal(yearBoundary.end, day('2027-02-01'));
    const singleDay = ganttSpan('2026-09-01', '2026-09-01', scale, true);
    assert.ok(parseFloat(singleDay.width) > 0);
    assert.equal(parseFloat(ganttSpan('2026-09-01', '2026-10-01', scale).width), 30 / (scale.end - scale.start) * 100);
    assert.equal(ganttSpan(null, '2026-10-01', scale), null);
    assert.equal(ganttSpan('2026-10-02', '2026-10-01', scale), null);
});

test('Gantt renders package bars and activity lines ending in due-date dots, with point-only undated starts', async () => {
    const { Gantt } = await loadComponents('resources\\js\\components\\projects\\Gantt.tsx');
    const packages = [
        { id: 1, name: 'Study', start_date: '2026-09-01', end_date: '2026-10-10', completed_at: null, activities: [] },
        { id: 2, name: 'Prototype', start_date: '2026-10-01', end_date: '2026-11-30', completed_at: '2026-10-24', activities: [{ id: 12 }] },
        { id: 3, name: 'Undated package', start_date: null, end_date: null, completed_at: null, activities: [] },
    ];
    const activities = [
        { id: 12, name: 'Review', responsible_user_id: 3, startdate: '2026-10-01', due: '2026-11-15', completed_at: '2026-10-24' },
        { id: 13, name: 'Training', responsible_user_id: null, startdate: null, due: '2026-10-30', completed_at: null },
        { id: 14, name: 'Same day', responsible_user_id: 3, startdate: '2026-11-01', due: '2026-11-01', completed_at: null },
        { id: 15, name: '<script>Missing due</script>', responsible_user_id: null, startdate: '2026-10-01', due: null, completed_at: null },
    ];
    const props = { project: { ...project, name: 'Customer portal', start_date: '2026-09-01', end_date: '2027-03-31' }, workPackages: packages, actions: activities, config, today: '2026-10-24' };
    const markup = renderToStaticMarkup(React.createElement(Gantt, props));
    assert.equal((markup.match(/class="ls-gantt-bar/g) ?? []).length, 2);
    assert.match(markup, /class="ls-gantt-bar ls-gantt-completed"/);
    assert.equal((markup.match(/class="ls-gantt-dot"/g) ?? []).length, 3);
    assert.equal((markup.match(/class="ls-gantt-activity-line"/g) ?? []).length, 2);
    assert.match(markup, /aria-label="Training: — – 2026-10-30 · Planned"[^>]*style="left:[^;]+%;width:0"/);
    assert.match(markup, /ls-gantt-activity-completed/);
    assert.match(markup, /class="ls-gantt-meta">Owner/);
    assert.match(markup, /Incomplete dates/);
    assert.match(markup, /No due date/);
    assert.match(markup, /class="ls-gantt-today"/);
    assert.match(markup, /role="region" aria-label="Project Gantt chart" tabindex="0"/);
    assert.match(markup, /role="img" aria-label="Review: 2026-10-01 – 2026-11-15 · Finished"/);
    assert.match(markup, /&lt;script&gt;Missing due&lt;\/script&gt;/);
    assert.doesNotMatch(markup, /<script>/);
    assert.doesNotMatch(renderToStaticMarkup(React.createElement(Gantt, {
        ...props, today: '2030-01-01',
    })), /class="ls-gantt-today"/);
    const noPackages = renderToStaticMarkup(React.createElement(Gantt, { ...props, config: { ...config, canReadWorkPackages: false } }));
    assert.doesNotMatch(noPackages, /Study|Prototype|Undated package|Work packages/);
    assert.match(noPackages, /Training/);
    const noActivities = renderToStaticMarkup(React.createElement(Gantt, { ...props, config: { ...config, canReadActions: false } }));
    assert.doesNotMatch(noActivities, /Review|Training|Missing due|Same day/);
    assert.match(noActivities, /Prototype/);
    const empty = renderToStaticMarkup(React.createElement(Gantt, { ...props, workPackages: [], actions: [] }));
    assert.match(empty, /No work packages/);
    assert.match(empty, /No actions/);
    const archived = renderToStaticMarkup(React.createElement(Gantt, { ...props, project: { ...props.project, archived_at: '2026-10-24' } }));
    assert.match(archived, /Prototype/);
    assert.doesNotMatch(archived, /<button/);
});

test('project Gantt uses work package headings, repeats shared activities, and lists unlinked activities last', async () => {
    const { Gantt } = await loadComponents('resources\\js\\components\\projects\\Gantt.tsx');
    const activities = [
        { id: 12, name: 'Shared review', responsible_user_id: 3, startdate: '2026-10-01', due: '2026-10-30' },
        { id: 13, name: 'Unlinked task', responsible_user_id: 3, due: '2026-11-15' },
        { id: 14, name: 'Package specific task', due: '2026-11-20' },
    ];
    const props = {
        project, config, today: '2026-10-24', actions: activities,
        workPackages: [
            { id: 1, name: 'First package', activities: [{ id: 12 }, { id: 14 }], start_date: '2026-10-01', end_date: '2026-11-30' },
            { id: 2, name: 'Second package', activities: [{ id: 12 }], start_date: '2026-10-01', end_date: '2026-11-30' },
        ],
    };
    const chart = Gantt(props);
    const groups = chart.props.groups;
    assert.deepEqual(Array.from(groups, group => group.label), ['First package', 'Second package', 'Other project activities']);
    assert.deepEqual(Array.from(groups[0].rows, row => row.id), [12, 14]);
    assert.deepEqual(Array.from(groups[1].rows, row => row.id), [12]);
    assert.deepEqual(Array.from(groups[2].rows, row => row.id), [13]);
    const markup = renderToStaticMarkup(chart);
    assert.match(markup, /<h2 class="ls-gantt-label">First package<\/h2>/);
    assert.match(markup, /<h2 class="ls-gantt-label">Second package<\/h2>/);
    assert.equal((markup.match(/class="ls-gantt-name" title="Shared review"/g) ?? []).length, 2);
    assert.doesNotMatch(markup, /Activities:|ls-gantt-meta">[0-9]/);
    const withoutPackageAccess = Gantt({ ...props, config: { ...config, canReadWorkPackages: false } });
    assert.deepEqual(Array.from(withoutPackageAccess.props.groups[0].rows, row => row.id), [12, 13, 14]);
});

test('Gantt centers today in the scrollable timeline excluding sticky labels and clamps at both ends', async () => {
    const effects = [];
    let resize;
    let disconnected = false;
    const previousObserver = globalThis.ResizeObserver;
    globalThis.ResizeObserver = class {
        constructor(callback) { resize = callback; }
        observe() {}
        disconnect() { disconnected = true; }
    };
    const scroll = { clientWidth: 1000, scrollWidth: 4000, scrollLeft: 0, getBoundingClientRect: () => ({ left: 100 }) };
    let todayOffset = 2400;
    const marker = { getBoundingClientRect: () => ({ left: 100 + todayOffset - scroll.scrollLeft }) };
    const label = { getBoundingClientRect: () => ({ width: 300 }) };
    let slot = 0;
    let Gantt;
    try {
        ({ Gantt } = await loadComponents('resources\\js\\components\\ui\\Gantt.tsx', initial => initial, () => {}, {
            useRef: () => ({ current: [scroll, marker, label][slot++] }),
            useEffect: callback => effects.push(callback),
        }));
    } finally {
        if (previousObserver === undefined) delete globalThis.ResizeObserver;
        else globalThis.ResizeObserver = previousObserver;
    }
    const chart = Gantt({ title: 'Long project', groups: [{ id: 1, label: 'Tasks', rows: [{ id: 1, name: 'Long task', kind: 'activity', start: '2020-01-01', end: '2027-01-01' }] }], today: '2026-10-24' });
    assert.match(renderToStaticMarkup(chart), /class="ls-gantt-today"/);
    const cleanup = effects[0]();
    assert.equal(scroll.scrollLeft, 1750);
    todayOffset = 100;
    resize();
    assert.equal(scroll.scrollLeft, 0);
    todayOffset = 3990;
    resize();
    assert.equal(scroll.scrollLeft, 3000);
    cleanup();
    assert.equal(disconnected, true);
});

test('Gantt range uses only visible package and activity dates, with one full trailing month', async () => {
    const { Gantt } = await loadComponents('resources\\js\\components\\ui\\Gantt.tsx');
    const props = {
        title: 'Visible dates', locale: 'en', today: '2030-01-01', startDate: '2020-01-01', endDate: '2035-01-01',
        groups: [{
            id: 1, label: 'Package',
            summary: { id: 1, name: 'Package', kind: 'bar', start: '2026-09-15', end: '2026-11-30' },
            rows: [{ id: 2, name: 'Task', kind: 'activity', start: '2026-10-10', end: '2026-12-31' }],
        }],
    };
    const months = markup => markup.match(/class="ls-gantt-months">(.*?)<\/div>/)[1];
    const markup = renderToStaticMarkup(React.createElement(Gantt, props));
    const axis = months(markup);
    assert.equal((axis.match(/<span /g) ?? []).length, 5);
    assert.match(axis, /Sep 26.*Oct 26.*Nov 26.*Dec 26.*Jan 27/);
    assert.doesNotMatch(markup, /class="ls-gantt-today"/);
    const filtered = renderToStaticMarkup(React.createElement(Gantt, {
        ...props, groups: [{ id: 2, label: 'Only remaining task', rows: [{ id: 2, name: 'Task', kind: 'activity', end: '2026-10-24' }] }],
    }));
    assert.equal((months(filtered).match(/<span /g) ?? []).length, 2);
    assert.match(months(filtered), /Oct 26.*Nov 26/);
});

test('Gantt highlights unfinished overdue packages and activities, not completed, due-today or undated items', async () => {
    const { Gantt } = await loadComponents('resources\\js\\components\\ui\\Gantt.tsx');
    const markup = renderToStaticMarkup(React.createElement(Gantt, {
        title: 'Overdue schedule', today: '2026-10-25',
        groups: [{
            id: 1, label: 'Late package',
            summary: { id: 1, name: 'Late package', kind: 'bar', start: '2026-10-01', end: '2026-10-24' },
            rows: [
                { id: 2, name: 'Late activity', kind: 'activity', start: '2026-10-01', end: '2026-10-24T23:59:59' },
                { id: 3, name: 'Late dot', kind: 'activity', end: '2026-10-24' },
                { id: 4, name: 'Done', kind: 'activity', end: '2026-10-24', completed: true },
                { id: 5, name: 'Due today', kind: 'activity', end: '2026-10-25' },
                { id: 6, name: 'Future', kind: 'activity', end: '2026-10-26' },
                { id: 7, name: 'No due', kind: 'activity' },
                { id: 8, name: 'Done package', kind: 'bar', start: '2026-10-01', end: '2026-10-24', completed: true },
                { id: 9, name: 'Late undated start', kind: 'bar', end: '2026-10-24' },
            ],
        }],
    }));
    assert.doesNotMatch(markup, /ls-gantt-overdue-badge/);
    assert.equal((markup.match(/class="ls-gantt-row ls-gantt-overdue"/g) ?? []).length, 3);
    assert.match(markup, /class="ls-gantt-group ls-gantt-overdue"/);
    assert.match(markup, /class="ls-gantt-bar ls-gantt-overdue-bar"/);
    assert.match(markup, /aria-label="Late activity: [^"]+ · Overdue"/);
    assert.match(markup, /aria-label="Late dot: [^"]+ · Overdue"/);
    assert.match(markup, /aria-label="Done: [^"]+ · Finished"/);
    assert.match(markup, /aria-label="Due today: [^"]+ · Planned"/);
    assert.match(markup, /aria-label="Future: [^"]+ · Planned"/);
    assert.match(markup, /aria-label="Done package: [^"]+ · Finished"/);
    assert.match(markup, /ls-gantt-key-overdue/);
});

test('work package completion is manual and only enabled for nonempty fully completed packages', async () => {
    const { WorkPackages, canCompleteWorkPackage } = await loadComponents('resources\\js\\components\\projects\\WorkPackages.tsx');
    const packages = [
        { id: 1, project_id: 7, name: 'Empty', description: null, completed_at: null, activities: [], access },
        { id: 2, project_id: 7, name: 'Pending', description: null, completed_at: null, activities: [{ id: 11, name: 'Open activity', completed_at: null }], access },
        { id: 3, project_id: 7, name: 'Ready', description: '<script>description</script>\nSecond line', completed_at: null, activities: [{ id: 12, name: 'Done activity', completed_at: '2026-10-24 12:00:00' }], access },
        { id: 4, project_id: 7, name: 'Closed', description: null, completed_at: '2026-10-24 14:00:00', activities: [{ id: 12, name: 'Done activity', completed_at: '2026-10-24 12:00:00' }], access },
    ];
    assert.deepEqual(packages.map(canCompleteWorkPackage), [false, false, true, true]);
    const props = { project, config, actions: [], workPackages: packages, run: async () => true };
    const markup = renderToStaticMarkup(React.createElement(WorkPackages, props));
    assert.equal((markup.match(/disabled=""[^>]*>Finish<\/button>/g) ?? []).length, 2);
    assert.equal((markup.match(/>Edit<\/button>/g) ?? []).length, 3);
    assert.match(markup, /Completed activities: 1 \/ 1/);
    assert.match(markup, /Completed at: 2026-10-24 14:00:00/);
    assert.match(markup, />Reopen<\/button>/);
    assert.match(markup, /&lt;script&gt;description&lt;\/script&gt;\nSecond line/);
    assert.doesNotMatch(markup, /<script>/);
    for (const restricted of [
        { ...props, project: { ...project, access: { ...project.access, write: false } } },
        { ...props, project: { ...project, archived_at: '2026-10-24' } },
    ]) {
        const readOnly = renderToStaticMarkup(React.createElement(WorkPackages, restricted));
        assert.doesNotMatch(readOnly, /Add work package|>Edit<\/button>|>Finish<\/button>|>Reopen<\/button>|>Delete<\/button>/);
        assert.match(readOnly, /Done activity/);
    }
});

test('work package forms create and update persistent records with activity IDs', async () => {
    const { WorkPackageForm } = await loadComponents('resources\\js\\components\\projects\\WorkPackages.tsx', value => value);
    const item = {
        id: 8, name: ' Package ', description: 'Description', activities: [{ id: 12, name: 'Existing activity', completed_at: null }],
        start_date: '2026-10-01', end_date: '2026-11-30',
    };
    const requests = [];
    respond({}, requests);
    let saved = 0;
    for (const existing of [undefined, item]) {
        const { WorkPackageForm: Form } = existing ? { WorkPackageForm } : await loadComponents(
            'resources\\js\\components\\projects\\WorkPackages.tsx',
            value => ({ ...value, name: item.name, description: item.description, activities: [12], start_date: item.start_date, end_date: item.end_date }),
        );
        const form = Form({ item: existing, project, actions: [{ id: 12, name: 'Existing activity' }], run: async operation => { await operation(); return true; }, onSaved: () => { saved++; }, onCancel() {} });
        await form.props.onSubmit({ preventDefault() {} });
    }
    assert.deepEqual(requests.map(request => [request.method, request.url]), [
        ['post', '/api/v1/crud/ProjectWorkPackage'],
        ['patch', '/api/v1/crud/ProjectWorkPackage/8'],
    ]);
    assert.ok(requests.every(request => {
        const payload = JSON.parse(request.data);
        return payload.name === 'Package' && payload.project_id === 7 && payload.activities.join(',') === '12'
            && payload.start_date === item.start_date && payload.end_date === item.end_date && !('completed_at' in payload);
    }));
    assert.equal(saved, 2);
    const { WorkPackageForm: FailingForm } = await loadComponents('resources\\js\\components\\projects\\WorkPackages.tsx', value => value);
    const form = FailingForm({ item, project, actions: [], run: async () => false, onSaved: () => { saved++; }, onCancel() {} });
    await form.props.onSubmit({ preventDefault() {} });
    assert.equal(saved, 2);
});

test('work package activity selection uses the shared multiselect and persists changes', async () => {
    let state;
    const { WorkPackageForm } = await loadComponents(
        'resources\\js\\components\\projects\\WorkPackages.tsx',
        initial => state ?? initial,
        value => { state = value; },
    );
    const requests = [];
    respond({}, requests);
    const actions = [{ id: 12, name: 'First activity' }, { id: 13, name: 'Second activity' }];
    const props = {
        item: { id: 8, name: 'Package', description: '', activities: [actions[0]] },
        project, actions, run: async operation => { await operation(); return true; }, onSaved() {}, onCancel() {},
    };
    const form = WorkPackageForm(props);
    const selector = form.props.children[2].props.children;
    assert.equal(selector.type.name, 'MultiSelect2');
    assert.deepEqual([...selector.props.values], [12]);
    assert.equal(selector.props.items, actions);
    assert.equal(selector.props.label, 'Linked activities');
    const markup = renderToStaticMarkup(form);
    assert.match(markup, /<select[^>]*aria-label="Linked activities"[^>]*multiple=""/);
    assert.match(markup, /<option value="12" selected="">First activity<\/option>/);
    for (const ids of [[12, 13], []]) {
        selector.props.onChange(ids);
        await WorkPackageForm(props).props.onSubmit({ preventDefault() {} });
        assert.deepEqual(JSON.parse(requests.at(-1).data).activities, ids);
    }
});

test('work package timeline shows dates, status markers, activity chips and completed activity styling', async () => {
    const { WorkPackages } = await loadComponents('resources\\js\\components\\projects\\WorkPackages.tsx');
    const activities = [
        { id: 12, name: 'Completed review', completed_at: '2026-10-24 12:00:00' },
        { id: 13, name: 'Remaining work', completed_at: null },
    ];
    const item = { id: 8, name: 'Prototype', start_date: '2026-10-01', end_date: '2026-11-30', activities, access, completed_at: null };
    const props = { project, config, actions: activities.map(activity => ({ ...activity, due: '2026-11-15' })), workPackages: [item], run: async () => true };
    const markup = renderToStaticMarkup(React.createElement(WorkPackages, props));
    assert.match(markup, /class="pm-wp-quick-add"/);
    assert.match(markup, /class="pm-wp-timeline"/);
    assert.match(markup, /class="pm-wp-row"/);
    assert.match(markup, /class="pm-wp-dates">2026-10-01 – 2026-11-30/);
    assert.match(markup, /class="pm-wp-chips"/);
    assert.match(markup, /class="pm-wp-activity-done">Completed review/);
    assert.match(markup, /class="pm-wp-activity-date">2026-11-15/);
    assert.match(markup, /aria-label="Unlink activity: Completed review"/);
    assert.match(markup, /Link existing activities/);
    assert.match(markup, />New activity<\/button>/);
    assert.doesNotMatch(markup, /pm-wp-finished/);
    const completed = renderToStaticMarkup(React.createElement(WorkPackages, {
        ...props, workPackages: [{ ...item, completed_at: '2026-10-24 14:00:00', activities: [activities[0]] }],
    }));
    assert.match(completed, /class="pm-wp-row pm-wp-finished"/);
    assert.doesNotMatch(completed, /Unlink activity|Link existing activities|>New activity<\/button>/);
    const noCreate = renderToStaticMarkup(React.createElement(WorkPackages, {
        ...props, config: { ...config, canCreateAction: false, canCreateWorkPackage: false },
    }));
    assert.doesNotMatch(noCreate, /pm-wp-quick-add|>New activity<\/button>/);
    assert.match(noCreate, /Link existing activities/);
});

test('work package quick add saves dates and keeps input after a failed save', async () => {
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    let state = { name: ' New package ', start_date: '2026-10-01', end_date: '2026-11-30' };
    const { WorkPackageQuickAdd } = await loadComponents(
        'resources\\js\\components\\projects\\WorkPackages.tsx', () => state, value => { state = value; },
    );
    const requests = [];
    respond({}, requests);
    await WorkPackageQuickAdd({ project, run: async () => false }).props.onSubmit({ preventDefault() {} });
    assert.equal(state.name, ' New package ');
    await WorkPackageQuickAdd({ project, run: async operation => { await operation(); return true; } }).props.onSubmit({ preventDefault() {} });
    assert.equal(requests[0].url, '/api/v1/crud/ProjectWorkPackage');
    assert.deepEqual(JSON.parse(requests[0].data), {
        name: 'New package', project_id: 7, start_date: '2026-10-01', end_date: '2026-11-30',
    });
    assert.equal(state.name, '');
    assert.equal(state.start_date, today);
    assert.equal(state.end_date, today);
    state = { name: 'Undated', start_date: '', end_date: '' };
    await WorkPackageQuickAdd({ project, run: async operation => { await operation(); return true; } }).props.onSubmit({ preventDefault() {} });
    assert.equal(JSON.parse(requests[1].data).start_date, null);
    assert.equal(JSON.parse(requests[1].data).end_date, null);
});

test('new work packages default both dates to today without changing existing dates', async () => {
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    const { WorkPackageQuickAdd, WorkPackageForm } = await loadComponents('resources\\js\\components\\projects\\WorkPackages.tsx', value => value);
    const props = { project, actions: [], run: async () => true, onSaved() {}, onCancel() {} };
    const quickAdd = renderToStaticMarkup(WorkPackageQuickAdd(props));
    assert.equal((quickAdd.match(new RegExp(`value="${today}"`, 'g')) ?? []).length, 2);
    const newPackage = renderToStaticMarkup(WorkPackageForm(props));
    assert.equal((newPackage.match(new RegExp(`value="${today}"`, 'g')) ?? []).length, 2);
    const dated = renderToStaticMarkup(WorkPackageForm({
        ...props, item: { name: 'Existing', activities: [], start_date: '2026-09-01', end_date: '2026-11-30' },
    }));
    assert.match(dated, /value="2026-09-01"/);
    assert.match(dated, /value="2026-11-30"/);
    const undated = renderToStaticMarkup(WorkPackageForm({
        ...props, item: { name: 'Undated', activities: [], start_date: null, end_date: null },
    }));
    assert.doesNotMatch(undated, new RegExp(`value="${today}"`));
});

test('work package inline links use MultiSelect2 and save without overwriting package details', async () => {
    let ids = [12];
    const { WorkPackageLinksForm } = await loadComponents(
        'resources\\js\\components\\projects\\WorkPackages.tsx', () => ids, value => { ids = value; },
    );
    const requests = [];
    respond({}, requests);
    let saved = 0;
    const props = {
        item: { id: 8, activities: [{ id: 12, name: 'First' }] }, actions: [{ id: 12, name: 'First' }, { id: 13, name: 'Second' }],
        run: async operation => { await operation(); return true; }, onSaved: () => { saved++; }, onCancel() {},
    };
    const form = WorkPackageLinksForm(props);
    const selector = form.props.children[0].props.children;
    assert.equal(selector.type.name, 'MultiSelect2');
    assert.deepEqual(selector.props.values, [12]);
    selector.props.onChange([12, 13]);
    await WorkPackageLinksForm(props).props.onSubmit({ preventDefault() {} });
    assert.equal(requests[0].url, '/api/v1/crud/ProjectWorkPackage/8');
    assert.deepEqual(JSON.parse(requests[0].data), { activities: [12, 13] });
    assert.equal(saved, 1);
});

test('work package new activity reuses activity fields and calls the atomic server action', async () => {
    const { WorkPackageActivityForm } = await loadComponents(
        'resources\\js\\components\\projects\\WorkPackages.tsx',
        value => ({ ...value, name: ' New activity ', estimated_cost: '125.50', estimated_hours: '5' }),
    );
    const requests = [];
    respond({}, requests);
    let saved = 0;
    const props = {
        item: { id: 8 }, project, config, run: async operation => { await operation(); return true; },
        onSaved: () => { saved++; }, onCancel() {},
    };
    const form = WorkPackageActivityForm(props);
    assert.equal(form.props.children[1].type.name, 'ActivityFields');
    await form.props.onSubmit({ preventDefault() {} });
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/api/v1/crud/ProjectWorkPackage/8/createactivity');
    assert.equal(requests[0].method, 'post');
    const payload = JSON.parse(requests[0].data);
    assert.equal(payload.name, 'New activity');
    assert.equal(payload.project_id, 7);
    assert.equal(payload.estimated_cost, 125.50);
    assert.equal(payload.estimated_hours, 5);
    assert.equal(saved, 1);
    await WorkPackageActivityForm({ ...props, run: async () => false }).props.onSubmit({ preventDefault() {} });
    assert.equal(saved, 1);
});

test('work package activity chips unlink only the selected activity without deleting it', async () => {
    const { WorkPackageRow } = await loadComponents('resources\\js\\components\\projects\\WorkPackages.tsx', value => value);
    const requests = [];
    respond({}, requests);
    const operations = [];
    const item = { id: 8, name: 'Package', completed_at: null, activities: [{ id: 12, name: 'First' }, { id: 13, name: 'Second' }], access };
    const tree = WorkPackageRow({
        item, project, config, actions: [], run: operation => {
            const result = operation().then(() => true);
            operations.push(result);
            return result;
        },
    });
    const find = node => {
        if (Array.isArray(node)) return node.flatMap(find);
        if (!node?.props) return [];
        return [...(node.props['aria-label'] === 'Unlink activity: First' ? [node] : []), ...find(node.props.children)];
    };
    const [button] = find(tree);
    assert.ok(button);
    button.props.onClick();
    await operations[0];
    assert.equal(requests.length, 1);
    assert.equal(requests[0].method, 'patch');
    assert.equal(requests[0].url, '/api/v1/crud/ProjectWorkPackage/8');
    assert.deepEqual(JSON.parse(requests[0].data), { activities: [13] });
});

test('work package finish, reopen and delete require confirmation and call the CRUD API', async () => {
    const originalWindow = globalThis.window;
    let confirm;
    globalThis.window = { confirmDialog: (title, message, onConfirm) => { confirm = onConfirm; } };
    try {
        const { WorkPackageRow } = await loadComponents('resources\\js\\components\\projects\\WorkPackages.tsx', value => value);
        const requests = [];
        const operations = [];
        respond({}, requests);
        const run = operation => {
            const result = operation().then(() => true);
            operations.push(result);
            return result;
        };
        const item = { id: 8, name: 'Package', completed_at: null, activities: [{ id: 12, name: 'Done', completed_at: '2026-10-24 12:00:00' }], access };
        const buttons = node => {
            if (Array.isArray(node)) return node.flatMap(buttons);
            if (!node?.props) return [];
            return [...(node.props.onClick ? [node] : []), ...buttons(node.props.children)];
        };
        for (const [completed_at, label] of [[null, 'Finish'], ['2026-10-24 14:00:00', 'Reopen'], [null, 'Delete']]) {
            const tree = WorkPackageRow({ project, config, actions: [], item: { ...item, completed_at }, run });
            const button = buttons(tree).find(node => node.props.children === label);
            assert.ok(button);
            const count = requests.length;
            button.props.onClick();
            assert.equal(requests.length, count);
            confirm();
            await operations.at(-1);
            assert.equal(requests.length, count + 1);
        }
        assert.deepEqual(requests.map(request => [request.method, request.url]), [
            ['patch', '/api/v1/crud/ProjectWorkPackage/8'],
            ['patch', '/api/v1/crud/ProjectWorkPackage/8'],
            ['delete', '/api/v1/crud/ProjectWorkPackage/8'],
        ]);
        assert.match(JSON.parse(requests[0].data).completed_at, /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
        assert.equal(JSON.parse(requests[1].data).completed_at, null);
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('work packages use project-scoped CRUD lists with cancellation', async () => {
    const requests = [];
    respond([], requests);
    const controller = new AbortController();
    await collection('ProjectWorkPackage', { 'filter[project_id]': 7, sort: 'name' }, controller.signal);
    assert.equal(requests[0].url, '/api/v1/crud/ProjectWorkPackage');
    assert.deepEqual(requests[0].params, { 'filter[project_id]': 7, sort: 'name' });
    assert.equal(requests[0].signal, controller.signal);
});

test('activities in completed work packages cannot be reopened or deleted in the interface', async () => {
    const { ProjectActions } = await loadComponents('resources\\js\\components\\projects\\ActionPlanPicker.tsx');
    const activity = { id: 12, name: 'Done', completed_at: '2026-10-24 12:00:00', risks: [], access };
    const packageItem = { id: 8, name: 'Closed', completed_at: '2026-10-24 14:00:00', activities: [activity] };
    const props = { project, config, actions: [activity], risks: [], workPackages: [packageItem], run: async () => true };
    const markup = renderToStaticMarkup(React.createElement(ProjectActions, props));
    assert.match(markup, /disabled=""[^>]*>Reopen<\/button>/);
    assert.match(markup, /disabled=""[^>]*>Delete<\/button>/);
    assert.match(markup, /Reopen all completed work packages/);
    const reopened = renderToStaticMarkup(React.createElement(ProjectActions, {
        ...props, workPackages: [{ ...packageItem, completed_at: null }],
    }));
    assert.doesNotMatch(reopened, /disabled=""[^>]*>(Reopen|Delete)<\/button>/);
});

test('project activity completion prefills and saves comments while cancellation leaves it untouched', async () => {
    const activity = {
        id: 12, name: 'Activity', description: 'Details', project_id: 7, responsible_user_id: 3,
        startdate: null, due: null, estimated_cost: null, estimated_hours: null, completed_at: null,
        assessmentcomment: 'Existing activity comment', risks: [], access,
    };
    const actions = [activity];
    let dialog;
    let operation;
    const originalWindow = globalThis.window;
    globalThis.window = {
        translateString: key => key,
        confirmCommentDialog: (title, message, comment, onConfirm) => { dialog = { title, message, comment, onConfirm }; },
    };
    const requests = [];
    respond({}, requests);
    try {
        const { ProjectActions } = await loadComponents('resources/js/components/projects/ActionPlanPicker.tsx', value => value);
        const tree = ProjectActions({ project, config, actions, risks: [], run: run => (operation = run()) });
        const markup = renderToStaticMarkup(tree);
        assert.match(markup, /Existing activity comment/);
        const activityTable = React.Children.toArray(tree.props.children)
            .flatMap(node => React.isValidElement(node) ? React.Children.toArray(node.props.children) : [])
            .find(node => React.isValidElement(node) && node.props.actions === actions);
        assert.ok(activityTable);
        activityTable.props.onToggle(activity);
        assert.equal(dialog.comment, 'Existing activity comment');
        assert.equal(requests.length, 0);
        dialog.onConfirm('');
        await operation;
        assert.equal(requests[0].url, '/api/v1/crud/ProjectActivity/12');
        assert.deepEqual(JSON.parse(requests[0].data), {
            completed_at: JSON.parse(requests[0].data).completed_at,
            assessmentcomment: '',
        });
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('overview metrics have distinct accents and decorative icons without exposing unauthorized metrics', async () => {
    const { Overview } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const item = { ...project, users: [{ id: 4, name: 'Participant' }], riskdistribution: {} };
    const props = { project: item, config: { ...config, riskMappings: [] }, risks: [risk], actions: [] };
    const overview = renderToStaticMarkup(React.createElement(Overview, props));
    for (const tone of ['warn', 'info', 'neutral', 'ok']) {
        assert.match(overview, new RegExp(`class="ls-card pm-stat pm-stat-${tone}"`));
    }
    assert.match(overview, /aria-hidden="true">warning<\/span>/);
    assert.match(overview, /class="pm-stat-value">1<\/div>/);
    const restricted = renderToStaticMarkup(React.createElement(Overview, {
        ...props, config: { ...props.config, canReadRisks: false, canReadActions: false },
    }));
    assert.doesNotMatch(restricted, /pm-stat-warn|pm-stat-info|pm-stat-ok/);
    assert.match(restricted, /pm-stat-neutral/);
});

test('risk form preserves assessment comments when saving the current assessment', async () => {
    const { RiskForm } = await loadComponents('resources/js/components/projects/Views.tsx', value => value);
    const requests = [];
    respond({}, requests);
    let saved = false;
    const form = RiskForm({ risk, project, config, run: async operation => { await operation(); return true; }, onSaved: () => { saved = true; }, onCancel: () => {} });
    await form.props.onSubmit({ preventDefault() {} });
    assert.equal(saved, true);
    assert.equal(requests[0].url, '/api/v1/crud/ProjectRisk/9');
    const payload = JSON.parse(requests[0].data);
    assert.equal(payload.note, 'Migrated note');
    assert.equal(payload.assessmentcomment, 'Existing assessment comment');
    assert.equal(Object.hasOwn(payload, 'post_probability_id'), false);
    assert.equal(Object.hasOwn(payload, 'post_consequence_id'), false);
    assert.deepEqual(payload.risk_controls, [5]);
    assert.equal(payload.project_id, 7);
    for (const key of ['context', 'replacing_id', 'replacedby_id', 'created_by']) {
        assert.equal(Object.hasOwn(payload, key), false);
    }
});

test('risk create and edit forms omit post-activity assessment', async () => {
    const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    for (const existing of [undefined, risk]) {
        const markup = renderToStaticMarkup(React.createElement(RiskForm, {
            risk: existing, project, config, run: async () => true, onSaved() {}, onCancel() {},
        }));
        assert.deepEqual([...markup.matchAll(/<legend>(.*?)<\/legend>/g)].map(match => match[1]), [
            'Risk details', 'Current risk assessment', 'Planned activities', 'Notes',
        ]);
        assert.equal((markup.match(/<form\b/g) ?? []).length, 1);
        assert.match(markup, /Add new activity/);
    }
});

test('risk form saves edited and new activities with their risk links and preserves unrelated links', async () => {
    const drafts = [
        { key: 12, dirty: true, editable: true, values: {
            name: 'Edited activity', description: 'Details', responsible_user_id: '', startdate: '',
            due: '', estimated_cost: '123.50', estimated_hours: '4', risks: [9, 10],
        } },
        { key: -1, dirty: true, editable: true, values: {
            name: 'New activity', description: '', responsible_user_id: '3', startdate: '',
            due: '', estimated_cost: '', estimated_hours: '', risks: [],
        } },
    ];
    const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx',
        initial => Array.isArray(initial) ? drafts : initial);
    const requests = [];
    respond({ id: 20 }, requests);
    let saved = false;
    const form = RiskForm({
        risk, project, config, run: async operation => { await operation(); return true; },
        onSaved() { saved = true; }, onCancel() {},
    });
    await form.props.onSubmit({ preventDefault() {} });
    assert.equal(saved, true);
    assert.deepEqual(requests.map(({ method, url }) => [method, url]), [
        ['patch', '/api/v1/crud/ProjectRisk/9'],
        ['patch', '/api/v1/crud/ProjectActivity/12'],
        ['post', '/api/v1/crud/ProjectActivity'],
    ]);
    const edited = JSON.parse(requests[1].data);
    assert.deepEqual(edited.risks, [9, 10]);
    assert.equal(edited.estimated_cost, 123.5);
    assert.equal(edited.estimated_hours, 4);
    assert.equal(edited.responsible_user_id, null);
    assert.deepEqual(JSON.parse(requests[2].data).risks, [9]);
});

test('risk form never shows post-activity assessment fields', async () => {
    let drafts;
    const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx',
        initial => Array.isArray(initial) ? drafts ??= initial : initial,
        update => { drafts = update(drafts); });
    const props = {
        risk, project, config, run: async () => true, onSaved() {}, onCancel() {},
        actions: [{ id: 12, name: 'Mitigation', risks: [{ id: 9 }], access, completed_at: null }],
    };
    const elements = node => React.isValidElement(node)
        ? [node, ...React.Children.toArray(node.props.children).flatMap(elements)] : [];
    let form = RiskForm(props);
    let markup = renderToStaticMarkup(form);
    assert.doesNotMatch(markup, /Assessment after activities|Probability after activities|Consequence after activities/);
    elements(form).find(node => node.props.children === 'Unlink activity').props.onClick();
    markup = renderToStaticMarkup(RiskForm(props));
    assert.doesNotMatch(markup, /Assessment after activities|Probability after activities|Consequence after activities/);
    form = RiskForm(props);
    elements(form).find(node => node.props['aria-label'] === 'Link existing activity').props.onChange({ target: { value: '12' } });
    assert.doesNotMatch(renderToStaticMarkup(RiskForm(props)), /Assessment after activities|Probability after activities|Consequence after activities/);
});

test('risk display never shows post-activity assessment', async () => {
    const { Risks } = await loadComponents('resources/js/components/projects/Views.tsx');
    const props = { project, risks: [risk], config, run: async () => true };
    for (const actions of [[], [{ id: 12, name: 'Unrelated', risks: [{ id: 10 }], access }]]) {
        const markup = renderToStaticMarkup(React.createElement(Risks, { ...props, actions }));
        assert.doesNotMatch(markup, /Assessment after activities|Probability after activities|Consequence after activities/);
        assert.match(markup, /Existing assessment comment/);
    }
    const actions = [{ id: 12, name: 'Mitigation', risks: [{ id: 9 }], access }];
    assert.doesNotMatch(renderToStaticMarkup(React.createElement(Risks, { ...props, actions })), /Assessment after activities|Probability after activities|Consequence after activities/);
});

test('project risk approval prefills and saves the assessment comment in the approval request', async () => {
    const riskItem = { ...risk, assessmentcomment: 'Existing assessment comment' };
    let dialog;
    let operation;
    const originalWindow = globalThis.window;
    globalThis.window = {
        translateString: key => key,
        confirmCommentDialog: (title, message, comment, onConfirm) => { dialog = { title, message, comment, onConfirm }; },
    };
    const requests = [];
    respond({}, requests);
    try {
        const { Risks } = await loadComponents('resources/js/components/projects/Views.tsx', value => value);
        const output = Risks({
            project, risks: [riskItem], actions: [], config,
            run: run => (operation = run()),
        });
        const nodes = node => React.isValidElement(node)
            ? [node, ...React.Children.toArray(node.props.children).flatMap(nodes)] : [];
        const approveButton = nodes(output).find(node => node.props.children === 'Approve');
        assert.ok(approveButton);
        approveButton.props.onClick();
        assert.equal(dialog.comment, 'Existing assessment comment');
        assert.equal(requests.length, 0);
        dialog.onConfirm('');
        await operation;
        assert.equal(requests[0].url, '/api/v1/crud/ProjectRisk/9/approve');
        assert.deepEqual(JSON.parse(requests[0].data), { assessmentcomment: '' });
    } finally {
        if (originalWindow === undefined) delete globalThis.window;
        else globalThis.window = originalWindow;
    }
});

test('risk form activity controls add editable drafts and retain unchanged linked activities', async () => {
    let drafts;
    const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx',
        initial => Array.isArray(initial) ? drafts ??= initial : initial,
        update => { drafts = update(drafts); });
    const requests = [];
    respond({}, requests);
    const props = {
        risk, project, config, run: async operation => { await operation(); return true; },
        actions: [
            { id: 12, name: 'Existing activity', risks: [{ id: 9 }], access, completed_at: null },
            { id: 13, name: 'Unrelated activity', risks: [{ id: 10 }], access, completed_at: null },
            { id: 14, name: 'Finished activity', risks: [{ id: 9 }], access, completed_at: '2026-10-24' },
        ],
        onSaved() {}, onCancel() {},
    };
    const elements = node => React.isValidElement(node)
        ? [node, ...React.Children.toArray(node.props.children).flatMap(elements)] : [];
    let form = RiskForm(props);
    assert.equal(drafts.length, 2);
    assert.equal(drafts[0].values.name, 'Existing activity');
    await form.props.onSubmit({ preventDefault() {} });
    assert.deepEqual(requests.map(request => request.url), ['/api/v1/crud/ProjectRisk/9']);
    elements(form).find(node => node.props.children?.[1] === 'Add new activity').props.onClick();
    assert.equal(drafts.length, 3);
    assert.equal(drafts[2].values.name, '');
    assert.equal(drafts[2].dirty, true);
    form = RiskForm(props);
    assert.doesNotMatch(renderToStaticMarkup(form), /Assessment after activities|Probability after activities|Consequence after activities/);
    const fields = elements(form).find(node => node.type.name === 'ActivityFields' && node.props.form.name === '');
    fields.props.onChange({ ...fields.props.form, name: 'Draft mitigation', estimated_hours: '8' });
    assert.equal(drafts[2].values.name, 'Draft mitigation');
    assert.equal(drafts[2].values.estimated_hours, '8');
    form = RiskForm(props);
    elements(form).filter(node => node.props.children === 'Unlink activity').at(-1).props.onClick();
    assert.equal(drafts.filter(item => !item.removed).length, 2);
    await RiskForm(props).props.onSubmit({ preventDefault() {} });
    assert.equal(requests.length, 2);
    assert.ok(requests.every(request => request.url === '/api/v1/crud/ProjectRisk/9'));
});

test('risk form unlinks activities only on save, preserves other risks and supports restoring links', async () => {
    let drafts;
    const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx',
        initial => Array.isArray(initial) ? drafts ??= initial : initial,
        update => { drafts = update(drafts); });
    const requests = [];
    respond({}, requests);
    const props = {
        risk: { ...risk, assessed_at: null }, project, config,
        actions: [
            { id: 12, name: 'Shared activity', risks: [{ id: 9 }, { id: 10 }], access, completed_at: null },
            { id: 14, name: 'Finished activity', risks: [{ id: 9 }], access, completed_at: '2026-10-24' },
        ],
        run: async operation => { await operation(); return true; }, onSaved() {}, onCancel() {},
    };
    const elements = node => React.isValidElement(node)
        ? [node, ...React.Children.toArray(node.props.children).flatMap(elements)] : [];
    let form = RiskForm(props);
    for (const button of elements(form).filter(node => node.props.children === 'Unlink activity')) button.props.onClick();
    assert.equal(drafts.filter(item => !item.removed).length, 0);
    assert.equal(requests.length, 0);
    form = RiskForm(props);
    assert.equal(elements(form).filter(node => node.props.children === 'Unlink activity').length, 0);
    const dropdown = elements(form).find(node => node.props['aria-label'] === 'Link existing activity');
    assert.deepEqual(dropdown.props.children[1].props.items.map(item => item.id), [12, 14]);
    dropdown.props.onChange({ target: { value: '12' } });
    assert.equal(drafts.filter(item => item.key === 12).length, 1);
    assert.equal(drafts.find(item => item.key === 12).removed, false);
    form = RiskForm(props);
    elements(form).find(node => node.props.children === 'Unlink activity').props.onClick();
    await RiskForm(props).props.onSubmit({ preventDefault() {} });
    assert.deepEqual(requests.map(request => [request.method, request.url]), [
        ['patch', '/api/v1/crud/ProjectRisk/9'],
        ['patch', '/api/v1/crud/ProjectActivity/12'],
        ['patch', '/api/v1/crud/ProjectActivity/14'],
    ]);
    assert.deepEqual(JSON.parse(requests[1].data), { risks: [10] });
    assert.deepEqual(JSON.parse(requests[2].data), { risks: [] });
});

test('risk form does not allow unlinking for assessed risks or without write and update access', async () => {
    const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const props = {
        risk: { ...risk, assessed_at: null }, project, config,
        actions: [{ id: 12, name: 'Activity', risks: [{ id: 9 }], access, completed_at: null }],
        run: async () => true, onSaved() {}, onCancel() {},
    };
    for (const restricted of [
        { ...props, risk: { ...risk, assessed_at: '2026-10-24' } },
        { ...props, project: { ...project, access: { ...project.access, write: false } } },
        { ...props, actions: [{ ...props.actions[0], access: { ...access, update: false } }] },
    ]) {
        assert.doesNotMatch(renderToStaticMarkup(React.createElement(RiskForm, restricted)), />Unlink activity</);
    }
});

test('risk form respects activity read, create and update permissions', async () => {
    const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const props = {
        risk, project, config: { ...config, canCreateAction: false },
        actions: [{ id: 12, name: 'Read-only mitigation', risks: [{ id: 9 }], access: { ...access, update: false }, completed_at: null }],
        run: async () => true, onSaved() {}, onCancel() {},
    };
    const readOnly = renderToStaticMarkup(React.createElement(RiskForm, props));
    assert.match(readOnly, /<fieldset[^>]*disabled="">[\s\S]*Read-only mitigation/);
    assert.doesNotMatch(readOnly, />Add new activity</);
    const restricted = renderToStaticMarkup(React.createElement(RiskForm, {
        ...props, config: { ...props.config, canReadActions: false },
    }));
    assert.doesNotMatch(restricted, /Planned activities|Read-only mitigation|Add new activity/);
});

test('risk form links existing activities without duplicating them or removing other risk links', async () => {
    for (const existingRisk of [risk, undefined]) {
        let drafts;
        const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx',
            initial => Array.isArray(initial) ? drafts ??= initial : initial,
            update => { drafts = update(drafts); });
        const requests = [];
        respond({ id: 21 }, requests);
        const props = {
            risk: existingRisk, project, config: { ...config, canCreateAction: false },
            actions: [
                { id: 12, name: 'Shared mitigation', risks: [{ id: 10 }], access, completed_at: null },
                { id: 13, name: 'No update access', risks: [], access: { ...access, update: false }, completed_at: null },
                { id: 14, name: 'Already linked', risks: [{ id: 9 }], access, completed_at: null },
            ],
            run: async operation => { await operation(); return true; }, onSaved() {}, onCancel() {},
        };
        const elements = node => React.isValidElement(node)
            ? [node, ...React.Children.toArray(node.props.children).flatMap(elements)] : [];
        let form = RiskForm(props);
        let dropdown = elements(form).find(node => node.props['aria-label'] === 'Link existing activity');
        assert.deepEqual(dropdown.props.children[1].props.items.map(item => item.id), existingRisk ? [12] : [12, 14]);
        dropdown.props.onChange({ target: { value: '12' } });
        dropdown.props.onChange({ target: { value: '12' } });
        assert.equal(drafts.filter(item => item.key === 12).length, 1);
        assert.equal(requests.length, 0);
        form = RiskForm(props);
        dropdown = elements(form).find(node => node.props['aria-label'] === 'Link existing activity');
        assert.ok(!dropdown.props.children[1].props.items.some(item => item.id === 12));
        await form.props.onSubmit({ preventDefault() {} });
        assert.equal(requests[1].url, '/api/v1/crud/ProjectActivity/12');
        assert.equal(requests[1].method, 'patch');
        assert.deepEqual(JSON.parse(requests[1].data).risks, [10, existingRisk ? 9 : 21]);
    }
});

test('risk accordions start collapsed with current probability and consequence levels in their headers', async () => {
    const { Risks } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const markup = renderToStaticMarkup(React.createElement(Risks, {
        project, config, risks: [risk, { ...risk, id: 10, name: 'Unrated risk', probability_id: null, consequence_id: null }],
        actions: [], run: async () => true,
    }));
    const accordions = [...markup.matchAll(/<details class="pm-risk-accordion"([^>]*)>([\s\S]*?)<\/details>/g)];
    assert.equal(accordions.length, 2);
    for (const [, attributes, contents] of accordions) {
        assert.doesNotMatch(attributes, /\bopen\b/);
        assert.match(contents, /^<summary class="pm-risk-header">[\s\S]*<\/summary><div class="pm-risk-body">/);
    }
    const header = accordions[0][2].split('</summary>')[0];
    assert.match(header, /Migration risk/);
    assert.match(header, /Probability: <strong>Initial probability<\/strong>/);
    assert.match(header, /Consequence: <strong>Initial consequence<\/strong>/);
    assert.doesNotMatch(header, /Post probability|Post consequence/);
    assert.match(header, /expand_more/);
    const unratedHeader = accordions[1][2].split('</summary>')[0];
    assert.match(unratedHeader, /Probability: <strong>Not assessed<\/strong>/);
    assert.match(unratedHeader, /Consequence: <strong>Not assessed<\/strong>/);
});

test('risk display follows the same four sections as editing and escapes multiline content', async () => {
    const { Risks } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const markup = renderToStaticMarkup(React.createElement(Risks, {
        project, config, risks: [{ ...risk, note: 'Line one\n<script>unsafe</script>' }],
        actions: [{ id: 12, name: 'Linked activity', description: 'Activity details', risks: [{ id: 9 }], responsible_user_id: 3, due: '2026-12-01', completed_at: null }],
        run: async () => true,
    }));
    assert.deepEqual([...markup.matchAll(/<section class="pm-risk-section"><h3>(.*?)<\/h3>/g)].map(match => match[1]), [
        'Risk details', 'Current risk assessment', 'Planned activities', 'Notes',
    ]);
    assert.match(markup, /Linked activity/);
    assert.match(markup, /Activity details/);
    assert.match(markup, /Due: <!-- -->2026-12-01|Due: 2026-12-01/);
    assert.ok(markup.includes('Line one\n&lt;script&gt;unsafe&lt;/script&gt;'));
    assert.doesNotMatch(markup, /<script>/);
    const restricted = renderToStaticMarkup(React.createElement(Risks, {
        project, config: { ...config, canReadActions: false }, risks: [risk], actions: [], run: async () => true,
    }));
    assert.doesNotMatch(restricted, /Planned activities/);
});

test('retrying activity saves does not create the risk or previously saved activities twice', async () => {
    const values = { name: 'Activity', description: '', responsible_user_id: '3', startdate: '', due: '', estimated_cost: '', estimated_hours: '', risks: [] };
    const { RiskForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx',
        initial => Array.isArray(initial) ? [
            { key: -1, dirty: true, editable: true, values },
            { key: -2, dirty: true, editable: true, values },
        ] : initial);
    const requests = [];
    axios.defaults.adapter = async config => {
        requests.push(config);
        if (requests.length === 3) throw new Error('Activity save failed');
        return { data: { id: requests.length === 1 ? 21 : 22 }, status: 200, statusText: 'OK', headers: {}, config };
    };
    let saved = false;
    const form = RiskForm({
        project, config, run: async operation => { await operation(); return true; },
        onSaved() { saved = true; }, onCancel() {},
    });
    await assert.rejects(form.props.onSubmit({ preventDefault() {} }), /Activity save failed/);
    assert.equal(saved, false);
    await form.props.onSubmit({ preventDefault() {} });
    assert.equal(saved, true);
    assert.deepEqual(requests.map(({ method, url }) => [method, url]), [
        ['post', '/api/v1/crud/ProjectRisk'],
        ['post', '/api/v1/crud/ProjectActivity'],
        ['post', '/api/v1/crud/ProjectActivity'],
        ['patch', '/api/v1/crud/ProjectRisk/21'],
        ['patch', '/api/v1/crud/ProjectActivity/22'],
        ['post', '/api/v1/crud/ProjectActivity'],
    ]);
    for (const request of requests.filter(request => request.url.includes('ProjectActivity'))) {
        assert.deepEqual(JSON.parse(request.data).risks, [21]);
    }
});

test('new activities default both dates to today without changing existing activity dates', async () => {
    const { activityValues } = await loadComponents('resources\\js\\components\\projects\\ActionPlanPicker.tsx');
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    const created = activityValues(undefined, 3);
    assert.equal(created.startdate, today);
    assert.equal(created.due, today);
    const existing = activityValues({ startdate: '2026-01-02', due: '2026-02-03', risks: [] }, 3);
    assert.equal(existing.startdate, '2026-01-02');
    assert.equal(existing.due, '2026-02-03');
    const undated = activityValues({ startdate: null, due: null, risks: [] }, 3);
    assert.equal(undated.startdate, '');
    assert.equal(undated.due, '');
});

test('standalone activity creation omits risk links while editing can update them', async () => {
    const item = {
        id: 12, name: 'Mitigation', description: 'Follow up', responsible_user_id: 3,
        startdate: '2026-10-24', due: '2026-12-01', estimated_cost: '1250.50', estimated_hours: 8,
        risks: [{ id: 9, name: risk.name }], completed_at: null, access,
    };
    const requests = [];
    respond({}, requests);
    for (const existing of [undefined, item]) {
        const { ActivityForm } = await loadComponents('resources\\js\\components\\projects\\ActionPlanPicker.tsx', value => ({
            ...value, name: item.name, startdate: item.startdate, due: item.due,
            estimated_cost: item.estimated_cost, estimated_hours: '8', risks: [9],
        }));
        const form = ActivityForm({ item: existing, project, risks: [risk], config, run: async operation => { await operation(); return true; }, onSaved() {}, onCancel() {} });
        const markup = renderToStaticMarkup(form);
        if (existing) assert.match(markup, /Linked risks/);
        else assert.doesNotMatch(markup, /Linked risks/);
        await form.props.onSubmit({ preventDefault() {} });
    }
    assert.deepEqual(requests.map(({ method, url }) => [method, url]), [
        ['post', '/api/v1/crud/ProjectActivity'], ['patch', '/api/v1/crud/ProjectActivity/12'],
    ]);
    const [createdPayload, updatedPayload] = requests.map(request => JSON.parse(request.data));
    for (const payload of [createdPayload, updatedPayload]) {
        assert.equal(payload.startdate, '2026-10-24');
        assert.equal(payload.estimated_cost, 1250.5);
        assert.equal(payload.estimated_hours, 8);
        assert.equal(payload.project_id, 7);
    }
    assert.equal(Object.hasOwn(createdPayload, 'risks'), false);
    assert.deepEqual(updatedPayload.risks, [9]);
});

test('optional activity fields and risk links can be cleared without assigning the current user', async () => {
    const { ActivityForm } = await loadComponents('resources\\js\\components\\projects\\ActionPlanPicker.tsx', value => value);
    const requests = [];
    respond({}, requests);
    const form = ActivityForm({
        item: { id: 12, name: 'Unassigned', risks: [], responsible_user_id: null },
        project, risks: [risk], config, run: async operation => { await operation(); return true; }, onSaved() {}, onCancel() {},
    });
    await form.props.onSubmit({ preventDefault() {} });
    const payload = JSON.parse(requests[0].data);
    for (const key of ['responsible_user_id', 'startdate', 'due', 'estimated_cost', 'estimated_hours']) {
        assert.equal(payload[key], null, key);
    }
    assert.deepEqual(payload.risks, []);
});

test('new-schema risks and activity links render without replacement chains or pretty/context fields', async () => {
    const { Risks } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const { ProjectActions } = await loadComponents('resources\\js\\components\\projects\\ActionPlanPicker.tsx');
    const activities = [{
        id: 12, name: 'Linked mitigation', description: null, project_id: 7, responsible_user_id: 3,
        startdate: '2026-10-24', due: null, estimated_cost: '1250.50', estimated_hours: 8,
        completed_at: null, risks: [{ id: 9, name: risk.name }], access,
    }];
    const props = { project, config, risks: [risk], actions: activities, run: async () => true, revision: 0 };
    const markup = renderToStaticMarkup(React.createElement(Risks, props));
    for (const value of [risk.scenariodescription, risk.consequencedescription, risk.note, 'Linked mitigation']) {
        assert.ok(markup.includes(value), value);
    }
    assert.doesNotMatch(markup, /Post probability|Post consequence/);
    assert.doesNotMatch(markup, /Show replaced risks|Replaced/);
    assert.doesNotMatch(markup, /Show comments|Show history/);
    const table = renderToStaticMarkup(React.createElement(ProjectActions, props));
    assert.match(table, /RISK-9: Migration risk/);
    assert.match(table, /1250.50/);
    assert.match(table, /2026-10-24/);
    assert.match(table, /Estimated hours/);
    const archived = renderToStaticMarkup(React.createElement(ProjectActions, { ...props, project: { ...project, archived_at: '2026-10-24' } }));
    assert.doesNotMatch(archived, />Edit<|>Finish<|>Delete<|Add new activity/);
});

test('participants are managed only in Team and permissions are visible to readers', async () => {
    const { Team, TeamForm, ProjectForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const teamProject = {
        ...project, name: 'Project', start_date: '2026-10-24',
        users: [{ id: 4, name: 'Reader', readonly: true }, { id: 5, name: 'Writer', readonly: false }],
    };
    const props = { project: teamProject, config, run: async () => true };
    const owner = renderToStaticMarkup(React.createElement(Team, props));
    assert.match(owner, /Manage participants/);
    assert.match(owner, /Reader/);
    assert.match(owner, /Read only/);
    assert.match(owner, /Read\/write/);
    const reader = renderToStaticMarkup(React.createElement(Team, {
        ...props, project: { ...teamProject, access: { ...teamProject.access, manageparticipants: false } },
    }));
    assert.doesNotMatch(reader, /<button|<select/);
    const editor = renderToStaticMarkup(React.createElement(TeamForm, { ...props, onSaved() {}, onCancel() {} }));
    assert.match(editor, /aria-label="Add: Participants"/);
    assert.doesNotMatch(editor, /multiple=""/);
    assert.match(editor, /aria-label="Delete: Reader"/);
    assert.match(editor, /aria-label="Access: Reader"/);
    for (const existing of [undefined, teamProject]) {
        const markup = renderToStaticMarkup(React.createElement(ProjectForm, {
            ...props, project: existing, config: { ...config, projectTypes: [] }, onSaved() {}, onCancel() {},
        }));
        assert.doesNotMatch(markup, /Participants/);
    }
});

test('Team adds participants through a dropdown and removes them with row buttons before saving', async () => {
    let participants;
    const { TeamForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx',
        initial => participants ??= initial,
        update => { participants = update(participants); });
    const requests = [];
    respond({}, requests);
    let saved = false;
    const props = {
        project: { ...project, users: [{ id: 4, name: 'Reader', readonly: true }] },
        config: { ...config, users: [
            { id: 3, name: 'Owner', enabled: true },
            { id: 4, name: 'Reader', enabled: false },
            { id: 5, name: 'New writer', enabled: true },
            { id: 6, name: 'Disabled', enabled: false },
        ] },
        run: async operation => { await operation(); return true; },
        onSaved() { saved = true; }, onCancel() {},
    };
    const elements = node => {
        if (!React.isValidElement(node)) return [];
        return [node, ...React.Children.toArray(node.props.children).flatMap(elements)];
    };
    let form = TeamForm(props);
    let dropdown = elements(form).find(node => node.props['aria-label'] === 'Add: Participants');
    assert.deepEqual(dropdown.props.children[1].props.items.map(user => user.id), [5]);
    assert.equal(dropdown.props.value, '');
    dropdown.props.onChange({ target: { value: '5' } });
    dropdown.props.onChange({ target: { value: '5' } });
    assert.deepEqual(JSON.parse(JSON.stringify(participants)), [
        { id: 4, name: 'Reader', readonly: true }, { id: 5, name: 'New writer', readonly: false },
    ]);
    form = TeamForm(props);
    dropdown = elements(form).find(node => node.props['aria-label'] === 'Add: Participants');
    assert.equal(dropdown.props.disabled, true);
    elements(form).find(node => node.props['aria-label'] === 'Delete: New writer').props.onClick();
    form = TeamForm(props);
    dropdown = elements(form).find(node => node.props['aria-label'] === 'Add: Participants');
    assert.deepEqual(dropdown.props.children[1].props.items.map(user => user.id), [5]);
    dropdown.props.onChange({ target: { value: '5' } });
    form = TeamForm(props);
    elements(form).find(node => node.props['aria-label'] === 'Delete: Reader').props.onClick();
    form = TeamForm(props);
    elements(form).find(node => node.props['aria-label'] === 'Access: New writer').props.onChange({ target: { value: 'read' } });
    assert.equal(requests.length, 0);
    await TeamForm(props).props.onSubmit({ preventDefault() {} });
    assert.equal(saved, true);
    assert.deepEqual(JSON.parse(requests[0].data), { users: [{ id: 5, readonly: true }] });
});

test('Team saves participant additions, removals and read-only flags to its server action', async () => {
    const requests = [];
    respond({}, requests);
    const { TeamForm } = await loadComponents('resources\\js\\components\\projects\\Views.tsx', () => [
        { id: 4, name: 'Reader', readonly: true }, { id: 6, name: 'New writer', readonly: false },
    ]);
    let saved = false;
    const form = TeamForm({
        project: { ...project, users: [{ id: 5, name: 'Removed', readonly: false }] }, config,
        run: async operation => { await operation(); return true; }, onSaved() { saved = true; }, onCancel() {},
    });
    await form.props.onSubmit({ preventDefault() {} });
    assert.equal(saved, true);
    assert.equal(requests[0].url, '/api/v1/crud/Project/7/updateparticipants');
    assert.deepEqual(JSON.parse(requests[0].data), {
        users: [{ id: 4, readonly: true }, { id: 6, readonly: false }],
    });
});

test('project settings retain project actions without Excel export', async () => {
    const { Settings } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const markup = renderToStaticMarkup(React.createElement(Settings, { project, config, run: async () => true }));
    assert.doesNotMatch(markup, /Export as Excel|ReportCentral/);
    assert.match(markup, /class="pm-project-details row mb-0"/);
    assert.match(markup, /class="col-md-6"><dt>Responsible user<\/dt>/);
    assert.match(markup, /class="ls-button ls-button-warning [^"]*">Archive project<\/button>/);
    assert.match(markup, /class="ls-button ls-button-danger [^"]*">Delete project<\/button>/);
    const archived = renderToStaticMarkup(React.createElement(Settings, {
        project: { ...project, archived_at: '2026-10-24' }, config, run: async () => true,
    }));
    assert.match(archived, /class="ls-button ls-button-outline [^"]*">Unarchive project<\/button>/);
});

test('empty risk message renders inside the risk card', async () => {
    const { Risks } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const markup = renderToStaticMarkup(React.createElement(Risks, {
        project, config, risks: [], actions: [], run: async () => true,
    }));
    assert.match(markup, /^<div class="ls-card [^"]*">[\s\S]*<p class="text-muted">No risks<\/p><\/div>$/);
});

test('read-only participants cannot create risks or activities in the interface', async () => {
    const { Risks } = await loadComponents('resources\\js\\components\\projects\\Views.tsx');
    const { ProjectActions } = await loadComponents('resources\\js\\components\\projects\\ActionPlanPicker.tsx');
    const props = {
        project: { ...project, access: { ...project.access, update: false, write: false } },
        config, run: async () => true, risks: [], actions: [], revision: 0,
    };
    assert.doesNotMatch(renderToStaticMarkup(React.createElement(Risks, props)), /Add new risk/);
    assert.doesNotMatch(renderToStaticMarkup(React.createElement(ProjectActions, props)), /Add new activity/);
});
