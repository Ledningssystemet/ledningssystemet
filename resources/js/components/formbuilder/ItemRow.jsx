import React from 'react';
import { t } from './t.js';

const TYPE_LABELS = {
    text: 'Text',
    textarea: 'Text area',
    boolean: 'Yes/No',
    multiple_choice: 'Multiple choice',
};

const TYPE_BADGE_CLASSES = {
    text: 'bg-primary',
    textarea: 'bg-info text-dark',
    boolean: 'bg-success',
    multiple_choice: 'bg-warning text-dark',
};

export function ItemRow({ item, onEdit, onDelete, onDragStart, onDragOver, onDragLeave, onDrop, onDragEnd }) {
    return (
        <div
            className="fe-item d-flex align-items-center gap-2 p-2 mb-1 border rounded"
            draggable
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDragLeave={onDragLeave}
            onDrop={onDrop}
            onDragEnd={onDragEnd}
        >
            <span className="fe-drag-handle">
                <span className="material-symbols-rounded">drag_indicator</span>
            </span>
            <span className={`badge fe-type-badge ${TYPE_BADGE_CLASSES[item.type] || 'bg-secondary'}`}>
                {t(TYPE_LABELS[item.type] || item.type)}
            </span>
            <div className="flex-grow-1 overflow-hidden">
                <div className="fw-semibold text-truncate">{item.name}</div>
                {item.description && (
                    <div className="small text-muted text-truncate">{item.description}</div>
                )}
                {item.files && (
                    <span className="badge bg-light text-dark border small">
                        <span className="material-symbols-rounded" style={{ verticalAlign: 'middle' }}>
                            attach_file
                        </span>
                        {' '}
                        {item.files.required ? t('Required file') : t('Optional file')}
                        {item.files.multiple ? ` (${t('multiple')})` : ''}
                    </span>
                )}
            </div>
            <div className="d-flex gap-1 flex-shrink-0">
                <button
                    className="btn btn-sm btn-outline-secondary"
                    title={t('Edit field')}
                    onClick={e => { e.stopPropagation(); onEdit(); }}
                >
                    <span className="material-symbols-rounded">edit</span> {t('Edit')}
                </button>
                <button
                    className="btn btn-sm btn-outline-danger"
                    title={t('Delete field')}
                    onClick={e => { e.stopPropagation(); onDelete(); }}
                >
                    <span className="material-symbols-rounded">delete</span> {t('Delete')}
                </button>
            </div>
        </div>
    );
}
