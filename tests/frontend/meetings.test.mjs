import assert from 'node:assert/strict';
import { test, afterEach } from 'node:test';
import { createRequire } from 'node:module';
import { runInNewContext } from 'node:vm';
import { build } from 'esbuild';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import axios from 'axios';
import { getSchema } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Mention from '@tiptap/extension-mention';

const originalAdapter = axios.defaults.adapter;
afterEach(() => { axios.defaults.adapter = originalAdapter; });

async function components(file, { states = [], changes = [], effects = [], editor = null, captureEditor = () => {}, confirm = () => {}, browser = {}, document = {} } = {}) {
    const result = await build({
        entryPoints: [`resources\\js\\components\\projects\\${file}.tsx`],
        bundle: true, platform: 'node', format: 'cjs', packages: 'external', write: false, loader: { '.css': 'empty' },
    });
    let slot = 0;
    const require = createRequire(import.meta.url);
    const module = { exports: {} };
    runInNewContext(result.outputFiles[0].text, {
        module, exports: module.exports, Error, AbortController, URLSearchParams, Event, crypto: globalThis.crypto, document,
        window: { translateString: key => key, confirmDialog: confirm, ...browser },
        require: name => name === 'react' ? {
            ...React,
            useState: initial => {
                const index = slot++;
                return [index < states.length ? states[index] : typeof initial === 'function' ? initial() : initial,
                    value => changes.push({ index, value })];
            },
            useRef: initial => ({ current: initial }), useEffect: effect => { effects.push(effect); },
        } : name === '@tiptap/react'
            ? { useEditor: options => { captureEditor(options); return editor; }, EditorContent: () => null }
            : name === 'axios' ? axios : require(name),
    });
    return module.exports;
}

function nodes(element) {
    if (!element || typeof element !== 'object') return [];
    if (Array.isArray(element)) return element.flatMap(nodes);
    return [element, ...nodes(element.props?.children)];
}
const button = (tree, text) => nodes(tree).find(node => node.type?.name === 'Btn' && node.props.children === text);
const section = (source = 'template') => ({
    id: 'template-1', title: 'Safety', description: 'Review incidents', source,
    document: { type: 'doc', content: [{ type: 'paragraph' }] }, activity_ids: [],
});
const config = { currentUserId: 1, canReadActions: true, canCreateAction: true, users: [{ id: 1, name: 'Owner' }] };
const project = { id: 7, responsible_user_id: 1, users: [{ id: 2, name: 'Participant' }], access: { write: true }, archived_at: null };

test('mandatory agenda points retain title and description, cannot be removed, and remain reorderable', async () => {
    const { MeetingAgenda } = await components('MeetingAgenda');
    const tree = MeetingAgenda({
        items: [section(), { ...section('followup'), id: 'followup-2', title: 'Follow-up', previous_meeting_id: 2 }],
        members: [], actions: [], config, locked: false, onChange: () => {}, onCreateActivity: () => {}, onOpenMeeting: () => {},
    });
    const markup = renderToStaticMarkup(tree);
    assert.match(markup, /Safety|Review incidents|Mandatory/);
    assert.doesNotMatch(markup, /aria-label="Remove section"|aria-label="Section title"/);
    assert.match(markup, /aria-label="Move down"/);
    assert.match(markup, /Open previous meeting/);
});

test('custom agenda points can be removed after confirmation without modifying mandatory points', async () => {
    let confirmed;
    let changed;
    const { MeetingAgenda } = await components('MeetingAgenda', { confirm: (_title, _message, callback) => { confirmed = callback; } });
    const tree = MeetingAgenda({
        items: [section(), { ...section('custom'), id: 'custom-1' }], members: [], actions: [], config, locked: false,
        onChange: value => { changed = value; }, onCreateActivity: () => {}, onOpenMeeting: () => {},
    });
    nodes(tree).find(node => node.props?.['aria-label'] === 'Remove section').props.onClick();
    assert.equal(changed, undefined);
    confirmed();
    assert.deepEqual(JSON.parse(JSON.stringify(changed)), [section()]);
});

test('completed meeting agendas hide all editing controls while preserving the previous meeting link', async () => {
    let opened;
    const { MeetingAgenda } = await components('MeetingAgenda');
    const tree = MeetingAgenda({
        items: [{ ...section('followup'), previous_meeting_id: 3 }], members: [], actions: [], config, locked: true,
        onChange: () => assert.fail('Locked document cannot change'), onCreateActivity: () => {}, onOpenMeeting: id => { opened = id; },
    });
    const markup = renderToStaticMarkup(tree);
    assert.doesNotMatch(markup, /Add section|Move up|Remove section|Add new activity|Link existing activity/);
    button(tree, 'Open previous meeting').props.onClick();
    assert.equal(opened, 3);
});

const preview = {
    templates: [{ id: 9, name: 'Construction' }], sections: [section()],
    previous_meeting: { id: 3, name: 'Previous construction meeting', date: '2026-10-24' },
    follow_up_section: { ...section('followup'), id: 'followup-3', title: 'Follow-up', activity_ids: [5], previous_meeting_id: 3 },
};

test('creation explicitly asks about follow-up before preparing the new meeting and includes it only on acceptance', async () => {
    let draft;
    const { NewMeetingSetup } = await components('Meetings', {
        states: [preview.templates, '9', '2026-10-25', preview, false, null, 0],
    });
    const tree = NewMeetingSetup({ project, onPrepared: value => { draft = value; }, onCancel: () => {} });
    assert.equal(draft, undefined);
    const markup = renderToStaticMarkup(tree);
    assert.match(markup, /role="dialog"|Previous construction meeting|Include follow-up|Continue without follow-up/);
    button(tree, 'Include follow-up').props.onClick();
    assert.equal(draft.project_meeting_agenda_template_id, 9);
    assert.equal(draft.follow_up_meeting_id, 3);
    assert.equal(draft.include_follow_up, true);
    assert.deepEqual(JSON.parse(JSON.stringify(draft.values.agenda)), [preview.follow_up_section, ...preview.sections]);
});

test('declining follow-up keeps template points without creating a follow-up point', async () => {
    let draft;
    const { NewMeetingSetup } = await components('Meetings', {
        states: [preview.templates, '9', '2026-10-25', preview, false, null, 0],
    });
    const tree = NewMeetingSetup({ project, onPrepared: value => { draft = value; }, onCancel: () => {} });
    button(tree, 'Continue without follow-up').props.onClick();
    assert.equal(draft.include_follow_up, false);
    assert.equal(draft.follow_up_meeting_id, 3);
    assert.deepEqual(JSON.parse(JSON.stringify(draft.values.agenda)), preview.sections);
});

test('preparing an agenda calls the project preview action, not meeting creation, and waits for the follow-up answer', async () => {
    const requests = [];
    const changes = [];
    axios.defaults.adapter = async request => {
        requests.push(request);
        return { data: preview, status: 200, statusText: 'OK', headers: {}, config: request };
    };
    const { NewMeetingSetup } = await components('Meetings', {
        states: [preview.templates, '9', '2026-10-25', null, false, null, 0], changes,
    });
    const tree = NewMeetingSetup({ project, onPrepared: () => assert.fail('Must wait for the answer'), onCancel: () => {} });
    await nodes(tree).find(node => node.type === 'form').props.onSubmit({ preventDefault() {} });
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/api/v1/crud/Project/7/meetingagenda');
    assert.deepEqual(JSON.parse(requests[0].data), { date: '2026-10-25', project_meeting_agenda_template_id: 9 });
    assert.deepEqual(changes.find(change => change.index === 3).value, preview);
});

test('meeting save retains the draft after a failed request', async () => {
    let saved;
    let closed = 0;
    const values = { name: ' Planning ', date: '2026-10-25', location: '', agenda: [section()] };
    const { MeetingEditor } = await components('Meetings', { states: [values, null] });
    const tree = MeetingEditor({
        meeting: null, initialValues: values, project, config, actions: [], busy: false,
        onSave: async value => { saved = value; return false; },
        onCancel: () => { closed++; }, onCreateActivity: async () => null, onDirty: () => {}, onOpenMeeting: () => {},
    });
    await button(tree, 'Save').props.onClick();
    await Promise.resolve();
    assert.equal(saved.name, 'Planning');
    assert.equal(saved.location, null);
    assert.equal(closed, 0);
});

test('mention insertion uses the selected project member ID and Tiptap JSON rather than plain text', async () => {
    let editorOptions;
    let inserted;
    let deleted;
    const chain = { focus: () => chain, deleteRange: range => { deleted = range; return chain; }, insertContent: content => { inserted = content; return chain; }, run: () => true };
    const { MeetingAgenda } = await components('MeetingAgenda', {
        states: [{ from: 2, to: 6, query: 'part', kind: 'mention' }, 0],
        editor: { chain: () => chain, isActive: () => false }, captureEditor: options => { editorOptions = options; },
    });
    const tree = MeetingAgenda({
        items: [section('custom')], members: [{ id: 2, name: 'Participant' }, { id: 1, name: 'Owner' }], actions: [], config,
        locked: false, onChange: () => {}, onCreateActivity: () => {}, onOpenMeeting: () => {},
    });
    const editorNode = nodes(tree).find(node => node.type?.name === 'SectionEditor');
    editorNode.type(editorNode.props);
    assert.equal(editorOptions.editorProps.handleKeyDown({}, { key: 'Enter' }), true);
    assert.deepEqual(JSON.parse(JSON.stringify(deleted)), { from: 2, to: 6 });
    assert.deepEqual(JSON.parse(JSON.stringify(inserted)), [
        { type: 'mention', attrs: { id: 2, label: 'Participant' } }, { type: 'text', text: ' ' },
    ]);
});

async function suggestionEditor({ menu = null, selected = 0, members = [], actions = [], permissions = config, locked = false, linked = [] } = {}) {
    let options;
    let deleted;
    let inserted;
    let changed;
    let created;
    const changes = [];
    const document = { type: 'doc', content: [{ type: 'paragraph', content: [{ type: 'text', text: 'Keep these notes' }] }] };
    const chain = {
        focus: () => chain, deleteRange: range => { deleted = range; return chain; },
        insertContent: content => { inserted = content; return chain; }, run: () => true,
    };
    const { MeetingAgenda } = await components('MeetingAgenda', {
        states: [menu, selected], changes,
        editor: { chain: () => chain, isActive: () => false, getJSON: () => document },
        captureEditor: value => { options = value; },
    });
    const agenda = MeetingAgenda({
        items: [{ ...section('custom'), activity_ids: linked }], members, actions, config: permissions, locked,
        onChange: value => { changed = value[0]; }, onCreateActivity: id => { created = id; }, onOpenMeeting: () => {},
    });
    const node = nodes(agenda).find(node => node.type?.name === 'SectionEditor');
    const tree = node.type(node.props);
    return {
        tree, options, changes,
        key: (key, extra = {}) => options.editorProps.handleKeyDown({}, { key, ...extra }),
        detect: (text, extra = {}) => options.onSelectionUpdate({ editor: { state: { selection: {
            empty: true, from: text.length + 1,
            $from: { parent: { isTextblock: true, textBetween: () => text }, parentOffset: text.length },
            ...extra,
        } } } }),
        result: () => ({ deleted, inserted, changed, created, document }),
    };
}

test('mention popup filters participants and supports wrapping keyboard selection without leaving the editor', async () => {
    const editor = await suggestionEditor({
        menu: { kind: 'mention', from: 1, to: 2, query: '' },
        members: [{ id: 2, name: 'Participant' }, { id: 1, name: 'Owner' }],
    });
    assert.match(renderToStaticMarkup(editor.tree), /role="listbox"|aria-selected="true"|pm-meeting-suggestions/);
    assert.equal(editor.key('ArrowUp'), true);
    assert.equal(editor.key('Tab'), true);
    assert.equal(editor.result().inserted[0].attrs.id, 1);
    assert.equal(editor.key('Enter'), false);
});

test('slash popup filters project activities, excludes existing links, and preserves notes when linking', async () => {
    const editor = await suggestionEditor({
        menu: { kind: 'activity', from: 18, to: 23, query: 'risk' }, selected: 1, linked: [8],
        actions: [{ id: 8, name: 'Risk already linked', access: {} }, { id: 9, name: 'Risk assessment', access: {} }, { id: 10, name: 'Training', access: {} }],
    });
    const markup = renderToStaticMarkup(editor.tree);
    assert.match(markup, /Add new activity|Risk assessment/);
    assert.doesNotMatch(markup, /Risk already linked|Training/);
    assert.equal(editor.key('Enter'), true);
    const result = editor.result();
    assert.deepEqual(JSON.parse(JSON.stringify(result.deleted)), { from: 18, to: 23 });
    assert.deepEqual([...result.changed.activity_ids], [8, 9]);
    assert.deepEqual(result.changed.document, result.document);
    assert.equal(result.inserted, undefined);
});

test('slash popup creates an activity for the current section and removes only the command text', async () => {
    const editor = await suggestionEditor({ menu: { kind: 'activity', from: 4, to: 5, query: '' } });
    assert.equal(editor.key('Enter'), true);
    assert.equal(editor.result().created, 'template-1');
    assert.deepEqual(JSON.parse(JSON.stringify(editor.result().deleted)), { from: 4, to: 5 });
    assert.equal(editor.result().inserted, undefined);
});

test('popup closes with Escape or Close without inserting, linking or removing notes', async () => {
    for (const close of ['Escape', 'Close']) {
        const editor = await suggestionEditor({
            menu: { kind: 'mention', from: 1, to: 2, query: '' }, members: [{ id: 2, name: 'Participant' }],
        });
        if (close === 'Escape') assert.equal(editor.key(close), true);
        else button(editor.tree, 'Close').props.onClick();
        assert.equal(editor.key('Enter'), false);
        assert.equal(editor.result().deleted, undefined);
        assert.equal(editor.result().changed, undefined);
        assert.equal(editor.result().inserted, undefined);
    }
});

test('typing detects mention and activity commands but not email addresses, paths or nonempty selections', async () => {
    for (const [text, kind, query] of [['@', 'mention', ''], ['Contact @Part', 'mention', 'Part'], ['/', 'activity', ''], ['Notes /Risk', 'activity', 'Risk']]) {
        const editor = await suggestionEditor();
        editor.detect(text);
        const menu = editor.changes.findLast(change => change.index === 0).value;
        assert.equal(menu.kind, kind);
        assert.equal(menu.query, query);
        assert.equal(menu.to, text.length + 1);
        assert.equal(menu.from, text.length - query.length);
    }
    for (const text of ['test@example.com', '2026/10/25', 'folder/file', 'Notes']) {
        const editor = await suggestionEditor();
        editor.detect(text);
        assert.equal(editor.changes.findLast(change => change.index === 0).value, null);
    }
    const editor = await suggestionEditor();
    editor.detect('@Part', { empty: false });
    assert.equal(editor.changes.findLast(change => change.index === 0).value, null);
});

test('empty suggestions do not swallow Enter and IME composition does not select a suggestion', async () => {
    const empty = await suggestionEditor({ menu: { kind: 'mention', from: 1, to: 5, query: 'none' } });
    assert.match(renderToStaticMarkup(empty.tree), /No matches/);
    assert.equal(empty.key('ArrowDown'), true);
    assert.equal(empty.key('Enter'), false);
    const composing = await suggestionEditor({
        menu: { kind: 'mention', from: 1, to: 2, query: '' }, members: [{ id: 2, name: 'Participant' }],
    });
    assert.equal(composing.key('Enter', { isComposing: true }), false);
    assert.equal(composing.result().inserted, undefined);
});

test('activity popup respects read/create permissions and locked agendas cannot pick suggestions', async () => {
    for (const [read, create] of [[true, false], [false, true], [false, false]]) {
        const editor = await suggestionEditor({
            menu: { kind: 'activity', from: 1, to: 2, query: '' },
            actions: [{ id: 9, name: 'Existing activity' }],
            permissions: { ...config, canReadActions: read, canCreateAction: create },
        });
        const markup = renderToStaticMarkup(editor.tree);
        assert.equal(markup.includes('Add new activity'), create);
        assert.equal(markup.includes('Existing activity'), read);
        if (!read && !create) {
            editor.detect('/');
            assert.equal(editor.changes.findLast(change => change.index === 0).value, null);
        }
    }
    const editor = await suggestionEditor({
        locked: true, menu: { kind: 'mention', from: 1, to: 2, query: '' }, members: [{ id: 2, name: 'Participant' }],
    });
    assert.doesNotMatch(renderToStaticMarkup(editor.tree), /role="listbox"|role="toolbar"/);
    editor.key('Enter');
    assert.equal(editor.result().inserted, undefined);
});

test('meeting loading fetches meetings and activities without requesting mentions', async () => {
    const requests = [];
    const effects = [];
    const changes = [];
    axios.defaults.adapter = async request => {
        requests.push(request);
        return { data: [], status: 200, statusText: 'OK', headers: {}, config: request };
    };
    const { Meetings } = await components('Meetings', { effects, changes });
    Meetings({ project, config, onDirty: () => {}, onActivityChanged: () => {} });
    const cleanup = effects[0]();
    await new Promise(resolve => setImmediate(resolve));
    assert.deepEqual(requests.map(request => request.url).sort(), [
        '/api/v1/crud/ProjectActivity', '/api/v1/crud/ProjectMeeting',
    ]);
    assert.equal(changes.some(change => change.index === 4 && change.value === false), true);
    cleanup();
});

test('persisted JSON uses the configured Tiptap schema including lists, formatting and structured mentions', () => {
    const schema = getSchema([
        StarterKit.configure({ link: false, underline: false, heading: { levels: [2, 3] } }),
        Mention,
    ]);
    const document = { type: 'doc', content: [
        { type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: 'Follow-up' }] },
        { type: 'orderedList', attrs: { start: 1 }, content: [{ type: 'listItem', content: [
            { type: 'paragraph', content: [
                { type: 'text', text: 'Contact ', marks: [{ type: 'bold' }] },
                { type: 'mention', attrs: { id: 2, label: 'Participant' } },
            ] },
        ] }] },
    ] };
    const parsed = schema.nodeFromJSON(document);
    assert.doesNotThrow(() => parsed.check());
    const mention = parsed.toJSON().content[1].content[0].content[0].content[1];
    assert.equal(mention.type, 'mention');
    assert.equal(mention.attrs.id, 2);
    assert.equal(mention.attrs.mentionSuggestionChar, '@');
});

test('new meeting saves include the selected agenda and explicit follow-up decision', async () => {
    const requests = [];
    axios.defaults.adapter = async request => {
        requests.push(request);
        return { data: request.method === 'post' ? { id: 10 } : [], status: 200, statusText: 'OK', headers: {}, config: request };
    };
    const values = { name: 'Construction meeting', date: '2026-10-25', location: null, agenda: [preview.follow_up_section, ...preview.sections] };
    const draft = { values, project_meeting_agenda_template_id: 9, include_follow_up: true, follow_up_meeting_id: 3 };
    const { Meetings } = await components('Meetings', {
        states: [[], [], 'new', draft, false, false, null, 0],
    });
    const tree = Meetings({ project, config, onDirty: () => {}, onActivityChanged: () => {} });
    const editor = nodes(tree).find(node => node.type?.name === 'MeetingEditor');
    assert.equal(await editor.props.onSave(values, false), true);
    const saved = requests.find(request => request.method === 'post');
    assert.equal(saved.url, '/api/v1/crud/ProjectMeeting');
    assert.deepEqual(JSON.parse(saved.data), { ...values, project_id: 7, project_meeting_agenda_template_id: 9, include_follow_up: true, follow_up_meeting_id: 3 });
});

test('activities created from an unsaved meeting update the shared project state without remounting the draft', async () => {
    const created = { id: 12, project_id: 7, name: 'New activity' };
    const requests = [];
    axios.defaults.adapter = async request => {
        requests.push(request);
        return { data: created, status: 200, statusText: 'OK', headers: {}, config: request };
    };
    const changes = [];
    const meeting = { id: 3, name: 'Planning', agenda: [section()], revision: 1, access: { update: true } };
    const { Meetings } = await components('Meetings', {
        states: [[meeting], [], meeting, null, false, false, null, 0],
    });
    const tree = Meetings({ project, config, onDirty: () => {}, onActivityChanged: value => changes.push(value) });
    const editor = nodes(tree).find(node => node.type?.name === 'MeetingEditor');
    const result = await editor.props.onCreateActivity({ name: 'New activity', project_id: 7 });
    assert.deepEqual(result, created);
    assert.deepEqual(changes, [created]);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].url, '/api/v1/crud/ProjectActivity');
});

const mention = {
    id: 21, project_id: 7, project_name: 'Bridge', meeting_name: 'Planning', project_meeting_id: 3,
    user_id: 2, agenda_item_id: 'template-1', excerpt: 'Contact @Participant', acknowledged_at: null,
};
const mentionedMeeting = { id: 3, project_id: 7, name: 'Planning', agenda: [section()], revision: 1, access: { update: false } };
const settle = () => new Promise(resolve => setImmediate(resolve));

test('header popup groups pending mentions by project and links to the meeting without acknowledging them', async () => {
    const requests = [];
    axios.defaults.adapter = async request => {
        requests.push(request);
        return { data: [], status: 200, statusText: 'OK', headers: {}, config: request };
    };
    const { ProjectMentions } = await components('ProjectMentions', {
        states: [[mention, { ...mention, id: 22, meeting_name: 'Follow-up' }], true, false, null, 0],
    });
    const tree = ProjectMentions();
    assert.equal(tree.type.name, 'Popup');
    assert.match(renderToStaticMarkup(tree.props.trigger), /@.*ls-badge-info.*2/);
    const markup = renderToStaticMarkup(React.createElement('div', null, tree.props.children));
    assert.equal((markup.match(/<h3/g) ?? []).length, 1);
    assert.match(markup, /Bridge|Planning|Follow-up|Contact @Participant/);
    assert.match(markup, /href="\/projects\?project=7&amp;meeting=3&amp;mention=21#meeting-section-template-1"/);
    assert.equal(requests.length, 0);
});

test('header loads personal mentions, excludes acknowledged rows and refreshes on acknowledgement', async () => {
    const requests = [];
    const effects = [];
    const changes = [];
    const listeners = new Map();
    axios.defaults.adapter = async request => {
        requests.push(request);
        return { data: [mention, { ...mention, id: 22, acknowledged_at: '2026-10-25 10:00:00' }], status: 200, statusText: 'OK', headers: {}, config: request };
    };
    const { ProjectMentions } = await components('ProjectMentions', {
        effects, changes, browser: {
            addEventListener: (name, callback) => listeners.set(name, callback),
            removeEventListener: name => listeners.delete(name),
        },
    });
    ProjectMentions();
    const cleanup = effects.map(effect => effect());
    await settle();
    assert.deepEqual(changes.find(change => change.index === 0).value, [mention]);
    assert.equal(requests[0].url, '/api/v1/crud/ProjectMeetingMention');
    assert.equal(requests[0].params.sort, '-created_at');
    assert.ok(requests.every(request => request.method === 'get'));
    listeners.get('project-mentions-changed')();
    assert.equal(changes.findLast(change => change.index === 4).value(4), 5);
    cleanup.forEach(callback => callback?.());
    assert.equal(listeners.size, 0);
});

test('header shows loading failures instead of treating them as zero mentions', async () => {
    const effects = [];
    const changes = [];
    axios.defaults.adapter = async () => { throw new Error('Unavailable'); };
    const { ProjectMentions } = await components('ProjectMentions', { effects, changes });
    ProjectMentions();
    const cleanup = effects[1]();
    await settle();
    assert.equal(changes.find(change => change.index === 3 && change.value).value, 'Unavailable');
    cleanup();
    const failed = await components('ProjectMentions', { states: [[], true, false, 'Unavailable', 0] });
    const popup = failed.ProjectMentions();
    assert.match(renderToStaticMarkup(popup.props.trigger), /ls-badge-bad.*!/);
    assert.match(renderToStaticMarkup(React.createElement('div', null, popup.props.children)), /role="alert".*Unavailable.*Retry/);
});

test('mention deep link opens the matching meeting and section without acknowledging before rendering', async () => {
    const requests = [];
    const effects = [];
    const changes = [];
    let opened = 0;
    axios.defaults.adapter = async request => {
        requests.push(request);
        return { data: mention, status: 200, statusText: 'OK', headers: {}, config: request };
    };
    const { Meetings } = await components('Meetings', {
        states: [[mentionedMeeting], [], null, null, false, false, null, 0], effects, changes,
    });
    Meetings({ project, config, onDirty: () => {}, onActivityChanged: () => {},
        initialLink: { projectId: 7, meetingId: 3, mentionId: 21 }, onLinkOpened: () => opened++ });
    const cleanup = effects[1]();
    await settle();
    assert.equal(opened, 1);
    assert.equal(changes.find(change => change.index === 2).value, mentionedMeeting);
    assert.equal(changes.find(change => change.index === 8).value, mention);
    assert.deepEqual(requests.map(request => request.url), ['/api/v1/crud/ProjectMeetingMention/21']);
    assert.equal(requests[0].method, 'get');
    cleanup();
});

test('missing meetings and mismatched mention sections remain unacknowledged and show an error', async () => {
    for (const meetings of [[], [{ ...mentionedMeeting, agenda: [] }]]) {
        const effects = [];
        const changes = [];
        const requests = [];
        axios.defaults.adapter = async request => {
            requests.push(request);
            return { data: mention, status: 200, statusText: 'OK', headers: {}, config: request };
        };
        const { Meetings } = await components('Meetings', {
            states: [meetings, [], null, null, false, false, null, 0], effects, changes,
        });
        Meetings({ project, config, onDirty: () => {}, onActivityChanged: () => {},
            initialLink: { projectId: 7, meetingId: 3, mentionId: 21 }, onLinkOpened: () => assert.fail('Invalid target') });
        effects[1]();
        await settle();
        assert.equal(changes.find(change => change.index === 6).value, 'The mentioned meeting or notes are no longer available');
        assert.ok(requests.every(request => request.method === 'get'));
    }
});

test('acknowledgement waits for the target editor to be ready, focuses notes and updates the badge', async () => {
    for (const ready of [null, mention.agenda_item_id]) {
        const requests = [];
        const effects = [];
        const events = [];
        const focused = [];
        let acknowledged;
        axios.defaults.adapter = async request => {
            requests.push(request);
            return { data: { ...mention, acknowledged_at: '2026-10-25 10:00:00' }, status: 200, statusText: 'OK', headers: {}, config: request };
        };
        const { MeetingEditor } = await components('Meetings', {
            states: [{ name: 'Planning', agenda: [section()] }, null, undefined, ready], effects,
            browser: { dispatchEvent: event => events.push(event.type) },
            document: { getElementById: id => {
                assert.equal(id, 'meeting-section-template-1');
                return { scrollIntoView: () => focused.push('scroll'), focus: () => focused.push('focus') };
            } },
        });
        MeetingEditor({ meeting: mentionedMeeting, initialValues: { name: 'Planning', agenda: [section()] },
            project, config, actions: [], busy: false, onDirty: () => {}, onSave: async () => true,
            onCancel: () => {}, onCreateActivity: async () => null, onOpenMeeting: () => {},
            targetMention: mention, onMentionAcknowledged: value => { acknowledged = value; } });
        effects[1]();
        await settle();
        assert.equal(requests.length, ready ? 1 : 0);
        if (ready) {
            assert.equal(requests[0].url, '/api/v1/crud/ProjectMeetingMention/21/acknowledge');
            assert.equal(requests[0].method, 'post');
            assert.ok(acknowledged.acknowledged_at);
            assert.deepEqual(focused, ['scroll', 'focus']);
            assert.deepEqual(events, ['project-mentions-changed']);
        }
    }
});

test('failed acknowledgement preserves the pending mention and offers retry', async () => {
    const effects = [];
    const changes = [];
    axios.defaults.adapter = async () => { throw new Error('Not saved'); };
    const { MeetingEditor } = await components('Meetings', {
        states: [{ name: 'Planning', agenda: [section()] }, null, undefined, mention.agenda_item_id], effects, changes,
        document: { getElementById: () => ({ scrollIntoView() {}, focus() {} }) },
    });
    MeetingEditor({ meeting: mentionedMeeting, project, config, actions: [], busy: false, onDirty: () => {},
        onSave: async () => true, onCancel: () => {}, onCreateActivity: async () => null, onOpenMeeting: () => {},
        targetMention: mention, onMentionAcknowledged: () => assert.fail('Must remain pending') });
    effects[1]();
    await settle();
    assert.equal(changes.findLast(change => change.index === 4).value, 'Not saved');
    const failed = await components('Meetings', {
        states: [{ name: 'Planning', agenda: [section()] }, null, undefined, mention.agenda_item_id, 'Not saved', 0],
    });
    const tree = failed.MeetingEditor({ meeting: mentionedMeeting, project, config, actions: [], busy: false,
        onDirty: () => {}, onCancel: () => {}, onSave: async () => true, onCreateActivity: async () => null, onOpenMeeting: () => {} });
    assert.match(renderToStaticMarkup(tree), /Could not acknowledge mention.*Not saved/);
    assert.ok(button(tree, 'Retry'));
});
