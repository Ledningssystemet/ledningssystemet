import React, { useState, useEffect, useRef } from 'react';
import { t } from './t.js';

export function ChapterModal({ open, chapter, onSave, onClose }) {
    const [name, setName] = useState('');
    const inputRef = useRef(null);

    useEffect(() => {
        if (open) {
            setName(chapter ? chapter.name : '');
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open, chapter]);

    if (!open) return null;

    function handleSave() {
        const trimmed = name.trim();
        if (!trimmed) return;
        onSave(trimmed);
    }

    return (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
            <div className="modal-dialog">
                <div className="modal-content">
                    <div className="modal-header">
                        <h5 className="modal-title">
                            {chapter ? t('Edit chapter') : t('Add chapter')}
                        </h5>
                        <button type="button" className="btn-close" onClick={onClose}></button>
                    </div>
                    <div className="modal-body">
                        <div className="mb-3">
                            <label className="form-label">{t('Name')}</label>
                            <input
                                ref={inputRef}
                                className="form-control"
                                value={name}
                                maxLength={255}
                                onChange={e => setName(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && handleSave()}
                            />
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
