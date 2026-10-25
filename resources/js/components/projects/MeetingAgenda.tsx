import React, { useEffect, useRef, useState } from 'react';
import { EditorContent, useEditor } from '@tiptap/react';
import type { Editor } from '@tiptap/core';
import { StarterKit } from '@tiptap/starter-kit';
import { Mention } from '@tiptap/extension-mention';
import { confirmAction, nameOf, t, type Action, type AgendaItem, type Configuration, type Option } from './api';
import { Badge, Btn, Card, Icon, Input } from '../ui';

export const emptyDocument = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
export const newAgendaItem = (): AgendaItem => ({
    id: crypto.randomUUID(), title: t('Notes'), document: emptyDocument(), activity_ids: [], source: 'custom', description: '',
});

type SuggestionMenu = { from: number; to: number; query: string; kind: 'mention' | 'activity' };
type SuggestionChoice = { id: string; label: string; icon: string; member?: Option; activity?: Action; create?: boolean };

function SectionEditor({ item, members, actions, config, locked, onChange, onCreateActivity, onReady }: {
    item: AgendaItem; members: Option[]; actions: Action[]; config: Configuration; locked: boolean;
    onChange: (item: AgendaItem) => void; onCreateActivity: (sectionId: string) => void;
    onReady?: () => void;
}) {
    const changeRef = useRef(onChange);
    const itemRef = useRef(item);
    const readyRef = useRef(onReady);
    changeRef.current = onChange;
    itemRef.current = item;
    readyRef.current = onReady;
    const [menu, setMenu] = useState<SuggestionMenu | null>(null);
    const [selected, setSelected] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);
    const popupRef = useRef<HTMLDivElement>(null);
    const menuRef = useRef(menu);
    menuRef.current = menu;
    const query = menu?.query.toLocaleLowerCase() ?? '';
    const choices: SuggestionChoice[] = menu?.kind === 'activity' ? [
        ...(config.canCreateAction ? [{ id: 'create', label: t('Add new activity'), icon: 'add', create: true }] : []),
        ...(config.canReadActions ? actions.filter(activity => !item.activity_ids.includes(activity.id) && activity.name.toLocaleLowerCase().includes(query))
            .map(activity => ({ id: `activity-${activity.id}`, label: activity.name, icon: 'link', activity })) : []),
    ] : members.filter(member => member.name.toLocaleLowerCase().includes(query))
        .map(member => ({ id: `member-${member.id}`, label: member.name, icon: 'alternate_email', member }));
    const choicesRef = useRef(choices);
    choicesRef.current = choices;
    const selectedRef = useRef(selected);
    selectedRef.current = selected;
    const pickRef = useRef<(index: number) => void>(() => {});
    const closeMenu = () => { menuRef.current = null; setMenu(null); };
    const detect = (editor: Editor) => {
        const { $from, empty, from } = editor.state.selection;
        if (locked || !empty || !$from.parent.isTextblock) { closeMenu(); return; }
        const before = $from.parent.textBetween(0, $from.parentOffset, undefined, '\ufffc');
        const match = before.match(/(?:^|\s)([@/])([^@/\ufffc]{0,40})$/);
        if (!match || (match[1] === '/' && !config.canReadActions && !config.canCreateAction)) { closeMenu(); return; }
        const next: SuggestionMenu = { from: from - match[2].length - 1, to: from, query: match[2], kind: match[1] === '@' ? 'mention' : 'activity' };
        if (next.from !== menuRef.current?.from || next.query !== menuRef.current?.query || next.kind !== menuRef.current?.kind) {
            selectedRef.current = 0;
            setSelected(0);
        }
        menuRef.current = next;
        setMenu(next);
    };
    const editor = useEditor({
        immediatelyRender: false,
        editable: !locked,
        extensions: [
            StarterKit.configure({ link: false, underline: false, heading: { levels: [2, 3] } }),
            Mention.configure({
                HTMLAttributes: { class: 'pm-meeting-mention' },
                suggestion: { allow: () => false, items: () => [] },
            }),
        ],
        content: item.document,
        editorProps: {
            attributes: { class: 'pm-meeting-document', role: 'textbox', 'aria-label': item.title, 'aria-multiline': 'true' },
            handleKeyDown: (_view, event) => {
                if (!menuRef.current) return false;
                if (event.isComposing) return false;
                if (event.key === 'Escape') { closeMenu(); return true; }
                const length = choicesRef.current.length;
                if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
                    if (length) {
                        selectedRef.current = (selectedRef.current + (event.key === 'ArrowDown' ? 1 : length - 1)) % length;
                        setSelected(selectedRef.current);
                    }
                    return true;
                }
                if (length && ['Enter', 'Tab'].includes(event.key)) { pickRef.current(selectedRef.current); return true; }
                if (event.key === 'Tab') closeMenu();
                return false;
            },
        },
        onUpdate: ({ editor }) => { changeRef.current({ ...itemRef.current, document: editor.getJSON() }); detect(editor); },
        onSelectionUpdate: ({ editor }) => detect(editor),
    });
    useEffect(() => { editor?.setEditable(!locked, false); if (locked) closeMenu(); }, [editor, locked]);
    useEffect(() => { if (editor) readyRef.current?.(); }, [editor]);
    useEffect(() => {
        if (!menu) return;
        const dismiss = (event: PointerEvent) => {
            if (event.target instanceof Node && !containerRef.current?.contains(event.target)) closeMenu();
        };
        document.addEventListener('pointerdown', dismiss);
        return () => document.removeEventListener('pointerdown', dismiss);
    }, [!!menu]);
    useEffect(() => {
        popupRef.current?.querySelector('[aria-selected="true"]')?.scrollIntoView({ block: 'nearest' });
    }, [selected, menu?.query, menu?.kind]);
    useEffect(() => {
        if (!editor) return;
        const textbox = editor.view.dom;
        textbox.setAttribute('aria-expanded', String(!!menu && !locked));
        if (menu && !locked) {
            textbox.setAttribute('aria-controls', `meeting-suggestions-${item.id}`);
            if (choices[selected]) textbox.setAttribute('aria-activedescendant', `meeting-suggestion-${item.id}-${choices[selected].id}`);
            else textbox.removeAttribute('aria-activedescendant');
        } else {
            textbox.removeAttribute('aria-controls');
            textbox.removeAttribute('aria-activedescendant');
        }
    }, [editor, menu, selected, choices, locked, item.id]);
    pickRef.current = index => {
        const choice = choicesRef.current[index];
        const activeMenu = menuRef.current;
        if (!editor || !activeMenu || !choice || locked) return;
        const chain = editor.chain().focus().deleteRange({ from: activeMenu.from, to: activeMenu.to });
        if (choice.member) {
            chain.insertContent([{ type: 'mention', attrs: { id: choice.member.id, label: choice.member.name } }, { type: 'text', text: ' ' }]).run();
        } else {
            chain.run();
            if (choice.activity) changeRef.current({ ...itemRef.current, document: editor.getJSON(), activity_ids: [...itemRef.current.activity_ids, choice.activity.id] });
            else if (choice.create) onCreateActivity(itemRef.current.id);
        }
        closeMenu();
    };
    const insertTrigger = (trigger: '@' | '/') => {
        if (!editor) return;
        const { $from } = editor.state.selection;
        const before = $from.parent.textBetween(0, $from.parentOffset, undefined, '\ufffc');
        editor.chain().focus().insertContent(`${before && !/\s$/.test(before) ? ' ' : ''}${trigger}`).run();
    };
    return <div ref={containerRef} onBlur={event => {
        if (!event.currentTarget.contains(event.relatedTarget)) closeMenu();
    }}>
        {!locked && editor && <div className="d-flex flex-wrap gap-1 border-bottom pb-2 mb-2" role="toolbar" aria-label={t('Document tools')}>
            <Btn variant="ghost" aria-label={t('Bold')} aria-pressed={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()}><strong>B</strong></Btn>
            <Btn variant="ghost" aria-label={t('Italic')} aria-pressed={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()}><em>I</em></Btn>
            <Btn variant="ghost" aria-label={t('Bullet list')} onClick={() => editor.chain().focus().toggleBulletList().run()}><Icon name="format_list_bulleted" /></Btn>
            <Btn variant="ghost" aria-label={t('Numbered list')} onClick={() => editor.chain().focus().toggleOrderedList().run()}><Icon name="format_list_numbered" /></Btn>
            <Btn variant="ghost" aria-label={t('Undo')} onClick={() => editor.chain().focus().undo().run()}><Icon name="undo" /></Btn>
            <Btn variant="ghost" aria-label={t('Redo')} onClick={() => editor.chain().focus().redo().run()}><Icon name="redo" /></Btn>
            <Btn variant="ghost" aria-label={t('Mention colleague')} onClick={() => insertTrigger('@')}>@</Btn>
            {(config.canReadActions || config.canCreateAction) && <Btn variant="outline" className="ms-auto" aria-label={t('Select activity')} onClick={() => insertTrigger('/')}><Icon name="add" /></Btn>}
        </div>}
        <div className="pm-meeting-editor">
            <EditorContent editor={editor} />
            {!locked && menu && <div ref={popupRef} className="pm-meeting-suggestions">
                <div id={`meeting-suggestions-${item.id}`} role="listbox" aria-label={t(menu.kind === 'mention' ? 'Mention colleague' : 'Select activity')}>
                    {choices.map((choice, index) => <Btn key={choice.id} id={`meeting-suggestion-${item.id}-${choice.id}`} variant="ghost" role="option" aria-selected={index === selected}
                        onMouseDown={event => event.preventDefault()} onMouseEnter={() => { selectedRef.current = index; setSelected(index); }} onClick={() => pickRef.current(index)}>
                        <Icon name={choice.icon} />{choice.label}
                    </Btn>)}
                    {!choices.length && <span role="status">{t('No matches')}</span>}
                </div>
                <Btn variant="ghost" onMouseDown={event => event.preventDefault()} onClick={closeMenu}>{t('Close')}</Btn>
            </div>}
        </div>
    </div>;
}

export function MeetingAgenda({ items, members, actions, config, locked, onChange, onCreateActivity, onOpenMeeting, onToggleActivity, targetSectionId, onSectionReady }: {
    items: AgendaItem[]; members: Option[]; actions: Action[]; config: Configuration; locked: boolean;
    onChange: (items: AgendaItem[]) => void; onCreateActivity: (sectionId: string) => void;
    onOpenMeeting: (id: number) => void;
    onToggleActivity: (activity: Action) => void;
    targetSectionId?: string; onSectionReady?: (id: string) => void;
}) {
    const change = (item: AgendaItem) => onChange(items.map(previous => previous.id === item.id ? item : previous));
    const move = (index: number, direction: number) => {
        const next = [...items];
        const target = index + direction;
        if (!next[index] || !next[target]) return;
        [next[index], next[target]] = [next[target]!, next[index]!];
        onChange(next);
    };
    return <section aria-label={t('Agenda')} className="my-3">
        <h3 className="h5">{t('Agenda')}</h3>
        {items.map((item, index) => <div key={item.id} id={`meeting-section-${item.id}`} tabIndex={targetSectionId === item.id ? -1 : undefined}
            className={targetSectionId === item.id ? 'pm-meeting-section-highlight' : undefined}><Card className="mb-3">
            <div className="d-flex align-items-center gap-2 mb-3">
                {locked ? <h4 className="h5">{item.title}</h4> : <>
                    {item.source === 'template' || item.source === 'followup'
                        ? <><h4 className="h5 mb-0">{item.title}</h4><Badge tone="info">{t('Mandatory')}</Badge></>
                        : <Input aria-label={t('Section title')} required maxLength={255} value={item.title} onChange={event => change({ ...item, title: event.target.value })} />}
                    <Btn variant="ghost" aria-label={t('Move up')} disabled={index === 0} onClick={() => move(index, -1)}><Icon name="arrow_upward" /></Btn>
                    <Btn variant="ghost" aria-label={t('Move down')} disabled={index === items.length - 1} onClick={() => move(index, 1)}><Icon name="arrow_downward" /></Btn>
                    {(!item.source || item.source === 'custom') && <Btn variant="ghost" aria-label={t('Remove section')} onClick={() => confirmAction('Remove this section and its notes?', () => onChange(items.filter(section => section.id !== item.id)), 'Remove section')}><Icon name="delete" /></Btn>}
                </>}
            </div>
            {item.description && <p className="text-muted text-break" style={{ whiteSpace: 'pre-wrap' }}>{item.description}</p>}
            {item.previous_meeting_id && <Btn variant="outline" className="mb-3" onClick={() => onOpenMeeting(item.previous_meeting_id!)}>{t('Open previous meeting')}</Btn>}
            <SectionEditor item={item} members={members} actions={actions} config={config} locked={locked} onChange={change} onCreateActivity={onCreateActivity}
                onReady={targetSectionId === item.id ? () => onSectionReady?.(item.id) : undefined} />
            <ul className="list-unstyled mt-3">{item.activity_ids.map(id => {
                const activity = actions.find(action => action.id === id);
                return <li key={id} className="d-flex flex-wrap align-items-center gap-2 border-top py-2">
                    <Icon name="checklist" /><span>{activity?.name ?? t('Activity unavailable')}</span>
                    {activity && <><span className="text-muted small">{nameOf(config.users, activity.responsible_user_id)} · {activity.due}</span><Badge tone={activity.completed_at ? 'ok' : 'info'}>{t(activity.completed_at ? 'Finished' : 'Planned')}</Badge></>}
                    {!locked && activity?.access.update && <Btn variant="outline" onClick={() => onToggleActivity(activity)}>{t(activity.completed_at ? 'Reopen' : 'Finish')}</Btn>}
                    {!locked && <Btn variant="ghost" aria-label={t('Unlink activity')} onClick={() => change({ ...item, activity_ids: item.activity_ids.filter(value => value !== id) })}><Icon name="link_off" /></Btn>}
                </li>;
            })}</ul>
        </Card></div>)}
        {!locked && <Btn variant="outline" onClick={() => onChange([...items, newAgendaItem()])}><Icon name="add" />{t('Add section')}</Btn>}
    </section>;
}
