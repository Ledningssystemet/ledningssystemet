import React, { useState, useEffect, useRef } from 'react';
import { t } from './t.js';

const TYPES = [
    ['text', 'Text'],
    ['textarea', 'Text area'],
    ['boolean', 'Yes/No'],
    ['multiple_choice', 'Multiple choice'],
];

function ChoiceRow({ id, value, onChange, onRemove }) {
    return (
        <div className="d-flex gap-2 mb-1">
            <input
                className="form-control form-control-sm"
                type="text"
                placeholder={t('Choice text')}
                value={value}
                onChange={e => onChange(id, e.target.value)}
            />
            <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => onRemove(id)}>
                <span className="material-symbols-rounded">delete</span>
            </button>
        </div>
    );
}

export function ItemModal({ open, item, onSave, onClose }) {
    const [type, setType] = useState('text');
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [placeholder, setPlaceholder] = useState('');
    const [required, setRequired] = useState(false);
    const [maxCharacters, setMaxCharacters] = useState('');
    const [boolYes, setBoolYes] = useState('Ja');
    const [boolNo, setBoolNo] = useState('Nej');
    const [mcChoices, setMcChoices] = useState([{ id: 1, value: '' }]);
    const [mcMultiple, setMcMultiple] = useState(false);
    const [filesEnabled, setFilesEnabled] = useState(false);
    const [filesRequired, setFilesRequired] = useState(false);
    const [filesMultiple, setFilesMultiple] = useState(false);
    const nameRef = useRef(null);

    useEffect(() => {
        if (!open) return;
        if (item) {
            setType(item.type || 'text');
            setName(item.name || '');
            setDescription(item.description || '');
            setPlaceholder(item.placeholder || '');
            setRequired(!!item.required);
            setMaxCharacters(item.options?.max_characters ? String(item.options.max_characters) : '');
            setBoolYes(item.options?.choices?.[0] ?? 'Ja');
            setBoolNo(item.options?.choices?.[1] ?? 'Nej');
            const existingChoices = item.options?.choices;
            setMcChoices(
                existingChoices?.length
                    ? existingChoices.map((c, i) => ({ id: i + 1, value: c }))
                    : [{ id: 1, value: '' }]
            );
            setMcMultiple(!!(item.options?.multiple));
            setFilesEnabled(!!item.files);
            setFilesRequired(!!(item.files?.required));
            setFilesMultiple(!!(item.files?.multiple));
        } else {
            setType('text');
            setName('');
            setDescription('');
            setPlaceholder('');
            setRequired(false);
            setMaxCharacters('');
            setBoolYes('Ja');
            setBoolNo('Nej');
            setMcChoices([{ id: 1, value: '' }]);
            setMcMultiple(false);
            setFilesEnabled(false);
            setFilesRequired(false);
            setFilesMultiple(false);
        }
        setTimeout(() => nameRef.current?.focus(), 50);
    }, [open, item]);

    if (!open) return null;

    function handleSave() {
        const trimmedName = name.trim();
        if (!trimmedName) return;

        const ni = { type, name: trimmedName, required };

        const desc = description.trim();
        if (desc) ni.description = desc;

        if (type === 'text' || type === 'textarea') {
            const ph = placeholder.trim();
            if (ph) ni.placeholder = ph;
        }
        if (type === 'textarea') {
            const mc = parseInt(maxCharacters);
            if (mc > 0) ni.options = { max_characters: mc };
        }
        if (type === 'boolean') {
            ni.options = { choices: [boolYes || 'Ja', boolNo || 'Nej'] };
        }
        if (type === 'multiple_choice') {
            const choices = mcChoices.map(c => c.value.trim()).filter(Boolean);
            ni.options = { choices: choices.length ? choices : [''], multiple: mcMultiple };
        }
        if (filesEnabled) {
            ni.files = { required: filesRequired, multiple: filesMultiple };
        }

        onSave(ni);
    }

    function updateChoice(id, val) {
        setMcChoices(prev => prev.map(c => c.id === id ? { ...c, value: val } : c));
    }
    function removeChoice(id) {
        setMcChoices(prev => prev.filter(c => c.id !== id));
    }
    function addChoice() {
        setMcChoices(prev => [...prev, { id: Date.now(), value: '' }]);
    }

    return (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
            <div className="modal-dialog modal-lg">
                <div className="modal-content">
                    <div className="modal-header">
                        <h5 className="modal-title">{item ? t('Edit field') : t('Add field')}</h5>
                        <button type="button" className="btn-close" onClick={onClose}></button>
                    </div>
                    <div className="modal-body">
                        {/* Type */}
                        <div className="mb-3">
                            <label className="form-label">{t('Type')}</label>
                            <select className="form-select" value={type} onChange={e => setType(e.target.value)}>
                                {TYPES.map(([val, lbl]) => (
                                    <option key={val} value={val}>{t(lbl)}</option>
                                ))}
                            </select>
                        </div>
                        {/* Name */}
                        <div className="mb-3">
                            <label className="form-label">{t('Name')}</label>
                            <input
                                ref={nameRef}
                                className="form-control"
                                value={name}
                                maxLength={255}
                                onChange={e => setName(e.target.value)}
                            />
                        </div>
                        {/* Description */}
                        <div className="mb-3">
                            <label className="form-label">{t('Description')}</label>
                            <textarea
                                className="form-control"
                                rows={2}
                                value={description}
                                onChange={e => setDescription(e.target.value)}
                            />
                        </div>
                        {/* Placeholder (text / textarea only) */}
                        {(type === 'text' || type === 'textarea') && (
                            <div className="mb-3">
                                <label className="form-label">{t('Placeholder')}</label>
                                <input
                                    className="form-control"
                                    value={placeholder}
                                    maxLength={255}
                                    onChange={e => setPlaceholder(e.target.value)}
                                />
                            </div>
                        )}
                        {/* Required */}
                        <div className="mb-3 form-check">
                            <input
                                className="form-check-input"
                                type="checkbox"
                                id="fb-req"
                                checked={required}
                                onChange={e => setRequired(e.target.checked)}
                            />
                            <label className="form-check-label" htmlFor="fb-req">{t('Required')}</label>
                        </div>
                        {/* Max characters (textarea only) */}
                        {type === 'textarea' && (
                            <div className="mb-3">
                                <label className="form-label">{t('Max characters')}</label>
                                <input
                                    className="form-control"
                                    type="number"
                                    min={1}
                                    value={maxCharacters}
                                    onChange={e => setMaxCharacters(e.target.value)}
                                />
                            </div>
                        )}
                        {/* Boolean choices */}
                        {type === 'boolean' && (
                            <div className="mb-3">
                                <label className="form-label">{t('Choices')}</label>
                                <div className="d-flex gap-2">
                                    <input
                                        className="form-control"
                                        placeholder={t('Yes option')}
                                        value={boolYes}
                                        onChange={e => setBoolYes(e.target.value)}
                                    />
                                    <input
                                        className="form-control"
                                        placeholder={t('No option')}
                                        value={boolNo}
                                        onChange={e => setBoolNo(e.target.value)}
                                    />
                                </div>
                            </div>
                        )}
                        {/* Multiple choice */}
                        {type === 'multiple_choice' && (
                            <div className="mb-3">
                                <label className="form-label">{t('Choices')}</label>
                                {mcChoices.map(c => (
                                    <ChoiceRow
                                        key={c.id}
                                        id={c.id}
                                        value={c.value}
                                        onChange={updateChoice}
                                        onRemove={removeChoice}
                                    />
                                ))}
                                <button
                                    type="button"
                                    className="btn btn-sm btn-outline-secondary mt-1"
                                    onClick={addChoice}
                                >
                                    <span className="material-symbols-rounded">add</span> {t('Add choice')}
                                </button>
                                <div className="mt-2 form-check">
                                    <input
                                        className="form-check-input"
                                        type="checkbox"
                                        id="fb-mcm"
                                        checked={mcMultiple}
                                        onChange={e => setMcMultiple(e.target.checked)}
                                    />
                                    <label className="form-check-label" htmlFor="fb-mcm">
                                        {t('Allow multiple selections')}
                                    </label>
                                </div>
                            </div>
                        )}
                        {/* File attachment */}
                        <div className="mb-3 border rounded p-3">
                            <div className="form-check">
                                <input
                                    className="form-check-input"
                                    type="checkbox"
                                    id="fb-fen"
                                    checked={filesEnabled}
                                    onChange={e => setFilesEnabled(e.target.checked)}
                                />
                                <label className="form-check-label" htmlFor="fb-fen">
                                    {t('Enable file upload')}
                                </label>
                            </div>
                            {filesEnabled && (
                                <div className="mt-2">
                                    <div className="form-check">
                                        <input
                                            className="form-check-input"
                                            type="checkbox"
                                            id="fb-fr"
                                            checked={filesRequired}
                                            onChange={e => setFilesRequired(e.target.checked)}
                                        />
                                        <label className="form-check-label" htmlFor="fb-fr">
                                            {t('File upload required')}
                                        </label>
                                    </div>
                                    <div className="form-check">
                                        <input
                                            className="form-check-input"
                                            type="checkbox"
                                            id="fb-fm"
                                            checked={filesMultiple}
                                            onChange={e => setFilesMultiple(e.target.checked)}
                                        />
                                        <label className="form-check-label" htmlFor="fb-fm">
                                            {t('Allow multiple files')}
                                        </label>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                    <div className="modal-footer">
                        <button type="button" className="btn btn-secondary" onClick={onClose}>{t('Cancel')}</button>
                        <button type="button" className="btn btn-primary" onClick={handleSave}>{t('Save')}</button>
                    </div>
                </div>
            </div>
        </div>
    );
}
