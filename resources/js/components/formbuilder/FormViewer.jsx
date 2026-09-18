import React, { useState } from 'react';
import './formviewer.css';
import { t } from './t.js';

/* ---- Data helpers ---- */

function extractAnswer(item) {
    for (const key of ['answer', 'answers', 'value', 'values', 'selected', 'selected_value', 'selected_values', 'response']) {
        if (Object.prototype.hasOwnProperty.call(item, key))
            return item[key];
    }
    return null;
}

function flattenValues(value) {
    if (value == null) return [];
    if (typeof value === 'boolean') return [value ? '1' : '0'];
    if (typeof value !== 'object') return [String(value).trim()];
    if (!Array.isArray(value)) {
        for (const key of ['value', 'answer', 'name', 'label', 'original_name', 'file_name', 'url']) {
            if (Object.prototype.hasOwnProperty.call(value, key))
                return flattenValues(value[key]);
        }
    }
    return Object.values(value).flatMap(flattenValues);
}

function answerValues(item) {
    return flattenValues(extractAnswer(item)).filter(v => v !== '');
}

function textValue(item) {
    return answerValues(item).join(', ');
}

function fileEntries(item) {
    const results = [];
    for (const key of ['uploaded_files', 'attached_files', 'file_uploads', 'attachments']) {
        if (Object.prototype.hasOwnProperty.call(item, key)) {
            const entry = item[key];
            if (Array.isArray(entry)) results.push(...entry);
            else if (entry != null) results.push(entry);
        }
    }
    return results;
}

function getFileName(file, fileIndex) {
    if (file && typeof file === 'object') {
        for (const key of ['original_name', 'file_name', 'filename', 'name', 'label']) {
            if (file[key]) return String(file[key]).trim();
        }
    }
    const values = flattenValues(file).filter(v => v !== '');
    return values[0] ?? `${t('File')} ${fileIndex + 1}`;
}

function choiceIsSelected(item, choice, choiceIndex = null) {
    const values = answerValues(item);
    const normalizedChoice = String(choice).trim();

    for (const val of values) {
        if (val.toLowerCase() === normalizedChoice.toLowerCase())
            return true;
    }

    if ((item.type ?? null) !== 'boolean' || values.length === 0)
        return false;

    const boolVal = values[0].toLowerCase();
    if (['1', 'true', 'yes', 'ja', 'on'].includes(boolVal)) return choiceIndex === 0;
    if (['0', 'false', 'no', 'nej', 'off'].includes(boolVal)) return choiceIndex === 1;

    return false;
}

/* ---- Data preparation ---- */

function prepareFormdata(formdata) {
    const rawChapters = (formdata.form_chapters ?? []).filter(c => c && typeof c === 'object');
    const rawItems = (formdata.form_items ?? []).filter(i => i && typeof i === 'object');

    const chapters = [...rawChapters].sort((a, b) =>
        ((a.ordinal ?? 0) - (b.ordinal ?? 0)) || ((a.id ?? 0) - (b.id ?? 0))
    );

    const items = rawItems
        .map((item, idx) => ({ ...item, __item_lookup_id: item.id ?? idx }))
        .sort((a, b) =>
            ((a.form_chapter_id ?? 0) - (b.form_chapter_id ?? 0))
            || ((a.ordinal ?? 0) - (b.ordinal ?? 0))
            || String(a.name ?? '').localeCompare(String(b.name ?? ''))
        );

    const itemsByChapter = {};
    for (const item of items) {
        const key = item.form_chapter_id ?? '__ungrouped__';
        (itemsByChapter[key] ??= []).push(item);
    }

    const chapterDefinitions = chapters.map(c => ({
        key: c.id ?? '__ungrouped__',
        name: c.name ?? t('Untitled chapter'),
    }));

    if (itemsByChapter['__ungrouped__']) {
        chapterDefinitions.push({ key: '__ungrouped__', name: t('General') });
    }

    return { chapterDefinitions, itemsByChapter };
}

/* ---- Field rendering ---- */

function FormField({ item, fieldId, formId }) {
    const choices = Array.isArray(item.options?.choices) ? item.options.choices : [];
    const value = textValue(item);
    const files = fileEntries(item);
    const updatedBy = item.updated_by ?? null;

    return (
        <div className="formview-field">
            <label htmlFor={fieldId}>
                {item.name ?? t('Untitled field')}
                {item.required && <span className="text-danger"> *</span>}
            </label>

            {updatedBy && (
                <div className="updatedby">{t('Last updated by')} {updatedBy}</div>
            )}

            {item.description && (
                <span className="form-text">{item.description}</span>
            )}

            {renderInput(item, fieldId, value, choices)}

            {item.files && (
                <div className="mt-3 small text-muted">
                    {item.files.required ? t('Required file') : t('Optional file')}
                    {item.files.multiple && ` (${t('multiple')})`}
                </div>
            )}

            {files.length > 0 && (
                <div className="formview-file-list">
                    {files.map((file, fileIndex) => {
                        const downloadUrl = formId
                            ? `/api/v1/items/Form/${formId}/downloadFile?item_id=${encodeURIComponent(String(item.__item_lookup_id))}&file_idx=${fileIndex}`
                            : null;
                        const name = getFileName(file, fileIndex);
                        const uploadedBy = file?.updated_by ?? null;
                        return (
                            <div key={fileIndex} className="formview-file-item">
                                <span className="material-symbols-rounded" style={{ fontSize: 18 }}>attach_file</span>
                                {downloadUrl
                                    ? <a href={downloadUrl}>{name}</a>
                                    : <span>{name}</span>
                                }
                                {uploadedBy && (
                                    <span className="updatedby">{t('Uploaded by')} {uploadedBy}</span>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function renderInput(item, fieldId, value, choices) {
    switch (item.type ?? 'text') {
        case 'textarea':
            return (
                <textarea
                    id={fieldId}
                    className="form-control"
                    rows={4}
                    readOnly
                    placeholder={item.placeholder ?? ''}
                    value={value}
                    onChange={() => {}}
                />
            );

        case 'boolean': {
            const boolChoices = choices.length ? choices : [t('Yes'), t('No')];
            return (
                <div id={fieldId}>
                    {boolChoices.map((choice, choiceIndex) => (
                        <div key={choiceIndex} className="form-check">
                            <input
                                className="form-check-input"
                                type="radio"
                                disabled
                                readOnly
                                checked={choiceIsSelected(item, choice, choiceIndex)}
                                onChange={() => {}}
                            />
                            <label className="form-check-label">{choice}</label>
                        </div>
                    ))}
                </div>
            );
        }

        case 'multiple_choice':
            return (
                <div id={fieldId}>
                    {choices.map((choice, choiceIndex) => (
                        <div key={choiceIndex} className="form-check">
                            <input
                                className="form-check-input"
                                type={item.options?.multiple ? 'checkbox' : 'radio'}
                                disabled
                                readOnly
                                checked={choiceIsSelected(item, choice)}
                                onChange={() => {}}
                            />
                            <label className="form-check-label">{choice}</label>
                        </div>
                    ))}
                </div>
            );

        default:
            return (
                <input
                    id={fieldId}
                    className="form-control"
                    type="text"
                    value={value}
                    placeholder={item.placeholder ?? ''}
                    readOnly
                    onChange={() => {}}
                />
            );
    }
}

/* ---- Main component ---- */

export function FormViewer({ formName, formdata = {}, formId = null }) {
    const { chapterDefinitions, itemsByChapter } = prepareFormdata(formdata);
    const [activeIndex, setActiveIndex] = useState(0);

    return (
        <div className="formview-shell">
            <div className="formview-card">
                <div className="formview-header">
                    <h1 className="formview-title">{formName}</h1>
                </div>

                {chapterDefinitions.length > 0 && (
                    <ul className="nav nav-tabs formview-chapter-nav" role="tablist" aria-label={t('Form chapters')}>
                        {chapterDefinitions.map((chapter, index) => (
                            <li key={chapter.key} className="nav-item" role="presentation">
                                <button
                                    type="button"
                                    className={`nav-link formview-chip${activeIndex === index ? ' active' : ''}`}
                                    role="tab"
                                    aria-selected={activeIndex === index}
                                    onClick={() => setActiveIndex(index)}
                                >
                                    {chapter.name}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <div className="formview-body">
                    {chapterDefinitions.length === 0 ? (
                        <div className="alert alert-light mb-0">
                            {t('No chapters yet. Add a chapter to get started.')}
                        </div>
                    ) : (
                        chapterDefinitions.map((chapter, index) => {
                            const chapterItems = itemsByChapter[chapter.key] ?? [];
                            return (
                                <section
                                    key={chapter.key}
                                    className={`formview-section${activeIndex === index ? '' : ' d-none'}`}
                                    role="tabpanel"
                                >
                                    <h2>{chapter.name}</h2>
                                    {chapterItems.length === 0 ? (
                                        <div className="text-muted small">{t('No fields in this chapter.')}</div>
                                    ) : (
                                        chapterItems.map((item, itemIndex) => (
                                            <FormField
                                                key={item.__item_lookup_id ?? itemIndex}
                                                item={item}
                                                fieldId={`form-item-${chapter.key}-${itemIndex}`}
                                                formId={formId}
                                            />
                                        ))
                                    )}
                                </section>
                            );
                        })
                    )}
                </div>
            </div>
        </div>
    );
}
