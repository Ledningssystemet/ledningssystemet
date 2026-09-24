import React from 'react';
import { ItemRow } from './ItemRow.jsx';
import { t } from './t.js';

export function ChapterCard({
    chapter,
    items,
    onEditChapter,
    onDeleteChapter,
    onAddItem,
    onEditItem,
    onDeleteItem,
    onDragStart,
    onDragOver,
    onDragLeave,
    onDrop,
    onDragEnd,
    onItemDragStart,
    onItemDragOver,
    onItemDragLeave,
    onItemDrop,
    onItemDragEnd,
}) {
    return (
        <div
            className="fe-chapter card mb-3"
            draggable
            onDragStart={e => onDragStart(e, chapter.id)}
            onDragOver={e => onDragOver(e, chapter.id)}
            onDragLeave={e => onDragLeave(e)}
            onDrop={e => onDrop(e, chapter.id)}
            onDragEnd={e => onDragEnd(e)}
        >
            <div className="card-header d-flex align-items-center gap-2">
                <span className="fe-drag-handle">
                    <span className="material-symbols-rounded">drag_indicator</span>
                </span>
                <strong>{chapter.name}</strong>
                <span className="badge bg-secondary ms-1">{items.length} {t('fields')}</span>
                <div className="ms-auto d-flex gap-1">
                    <button
                        className="btn btn-sm btn-outline-secondary"
                        title={t('Edit chapter')}
                        onClick={e => { e.stopPropagation(); onEditChapter(chapter.id); }}
                    >
                        <span className="material-symbols-rounded">edit</span> {t('Edit')}
                    </button>
                    <button
                        className="btn btn-sm btn-outline-danger"
                        title={t('Delete chapter')}
                        onClick={e => { e.stopPropagation(); onDeleteChapter(chapter.id); }}
                    >
                        <span className="material-symbols-rounded">delete</span> {t('Delete')}
                    </button>
                </div>
            </div>
            <div className="card-body">
                <div className="fe-items-container">
                    {items.length === 0 ? (
                        <div className="text-muted text-center small p-2">
                            {t('No fields in this chapter.')}
                        </div>
                    ) : (
                        items.map(item => (
                            <ItemRow
                                key={item._id}
                                item={item}
                                onEdit={() => onEditItem(item._id)}
                                onDelete={() => onDeleteItem(item._id)}
                                onDragStart={e => onItemDragStart(e, item._id)}
                                onDragOver={e => onItemDragOver(e, item._id)}
                                onDragLeave={e => onItemDragLeave(e)}
                                onDrop={e => onItemDrop(e, item._id, chapter.id)}
                                onDragEnd={e => onItemDragEnd(e)}
                            />
                        ))
                    )}
                </div>
                <button
                    className="btn btn-sm btn-outline-primary mt-2"
                    onClick={() => onAddItem(chapter.id)}
                >
                    <span className="material-symbols-rounded">add</span> {t('Add field')}
                </button>
            </div>
        </div>
    );
}
