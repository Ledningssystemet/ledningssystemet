import React, { useState, useRef, useEffect } from 'react';
import { ChapterCard } from './ChapterCard.jsx';
import { ChapterModal } from './ChapterModal.jsx';
import { ItemModal } from './ItemModal.jsx';
import { ConfirmModal } from './ConfirmModal.jsx';
import './formbuilder.css';
import { t } from './t.js';

export function FormBuilder({ templateId, templateName, initialFormdata = {} }) {
   const [chapters, setChapters] = useState(() =>
      [...(initialFormdata.form_chapters || [])].sort((a, b) => a.ordinal - b.ordinal)
   );
   const [items, setItems] = useState(() => {
      let id = 1;
      return (initialFormdata.form_items || []).map(item => ({ ...item, _id: id++ }));
   });

   // Next editor ID counter (IDs are stripped before save)
   const nextIdRef = useRef((initialFormdata.form_items || []).length + 1);

   // Top-level metadata preserved across saves (everything except chapters/items)
   const metadataRef = useRef(
      Object.fromEntries(
         Object.entries(initialFormdata).filter(([k]) => k !== 'form_chapters' && k !== 'form_items')
      )
   );

   const [isDirty, setIsDirty] = useState(false);

   const [chapterModal, setChapterModal] = useState({ open: false, chapterId: null });
   const [itemModal, setItemModal] = useState({ open: false, itemId: null, defaultChapterId: null });
   const [confirmModal, setConfirmModal] = useState({ open: false, title: '', message: '', warning: false, onConfirm: null });

   // HTML5 drag ref — no re-render needed for drag tracking
   const dragRef = useRef(null);

   useEffect(() => {
      const handler = e => { if (isDirty) { e.preventDefault(); e.returnValue = true; } };
      window.addEventListener('beforeunload', handler);
      return () => window.removeEventListener('beforeunload', handler);
   }, [isDirty]);

   /* ---- Chapter operations ---- */

   function openChapterModal(chapterId = null) {
      setChapterModal({ open: true, chapterId: chapterId ?? null });
   }

   function saveChapter(name) {
      const { chapterId } = chapterModal;
      if (chapterId !== null) {
         setChapters(prev => prev.map(c => c.id === chapterId ? { ...c, name } : c));
      } else {
         setChapters(prev => {
            const maxId = Math.max(0, ...prev.map(c => c.id));
            return [...prev, { id: maxId + 1, name, ordinal: prev.length + 1 }];
         });
      }
      setIsDirty(true);
      setChapterModal({ open: false, chapterId: null });
   }

   function confirmDeleteChapter(chapterId) {
      setConfirmModal({
         open: true,
         title: t('Delete chapter'),
         message: t('Are you sure? All fields in this chapter will also be deleted.'),
         warning: true,
         onConfirm: () => {
            setChapters(prev => prev.filter(c => c.id !== chapterId));
            setItems(prev => prev.filter(i => i.form_chapter_id !== chapterId));
            setIsDirty(true);
            setConfirmModal(m => ({ ...m, open: false }));
         },
      });
   }

   function reorderChapters(fromId, toId) {
      setChapters(prev => {
         const sorted = [...prev].sort((a, b) => a.ordinal - b.ordinal);
         const fi = sorted.findIndex(c => c.id === fromId);
         const ti = sorted.findIndex(c => c.id === toId);
         if (fi < 0 || ti < 0) return prev;
         const [moved] = sorted.splice(fi, 1);
         sorted.splice(ti, 0, moved);
         return sorted.map((c, i) => ({ ...c, ordinal: i + 1 }));
      });
      setIsDirty(true);
   }

   /* ---- Item operations ---- */

   function openItemModal(itemId = null, defaultChapterId = null) {
      setItemModal({ open: true, itemId: itemId ?? null, defaultChapterId: defaultChapterId ?? null });
   }

   function saveItem(data) {
      const { itemId, defaultChapterId } = itemModal;
      if (itemId !== null) {
         setItems(prev => prev.map(i => i._id === itemId ? { ...i, ...data } : i));
      } else {
         setItems(prev => {
            const maxOrd = prev
               .filter(i => i.form_chapter_id === defaultChapterId)
               .reduce((m, i) => Math.max(m, i.ordinal), 0);
            return [...prev, { ...data, form_chapter_id: defaultChapterId, ordinal: maxOrd + 1, _id: nextIdRef.current++ }];
         });
      }
      setIsDirty(true);
      setItemModal({ open: false, itemId: null, defaultChapterId: null });
   }

   function confirmDeleteItem(itemId) {
      setConfirmModal({
         open: true,
         title: t('Delete field'),
         message: t('Are you sure you want to delete this field?'),
         warning: true,
         onConfirm: () => {
            setItems(prev => prev.filter(i => i._id !== itemId));
            setIsDirty(true);
            setConfirmModal(m => ({ ...m, open: false }));
         },
      });
   }

   function moveItemToChapter(itemId, chapterId) {
      setItems(prev => {
         const maxOrd = prev
            .filter(i => i.form_chapter_id === chapterId)
            .reduce((m, i) => Math.max(m, i.ordinal), 0);
         return prev.map(i => i._id === itemId ? { ...i, form_chapter_id: chapterId, ordinal: maxOrd + 1 } : i);
      });
      setIsDirty(true);
   }

   function reorderItems(fromId, toId) {
      setItems(prev => {
         const updated = prev.map(i => ({ ...i }));
         const from = updated.find(i => i._id === fromId);
         const to = updated.find(i => i._id === toId);
         if (!from || !to) return prev;

         if (from.form_chapter_id === to.form_chapter_id) {
            const list = updated
               .filter(i => i.form_chapter_id === from.form_chapter_id)
               .sort((a, b) => a.ordinal - b.ordinal);
            const fi = list.findIndex(i => i._id === fromId);
            const ti = list.findIndex(i => i._id === toId);
            const [moved] = list.splice(fi, 1);
            list.splice(ti, 0, moved);
            list.forEach((i, n) => { i.ordinal = n + 1; });
         } else {
            from.form_chapter_id = to.form_chapter_id;
            from.ordinal = to.ordinal - 0.5;
            updated
               .filter(i => i.form_chapter_id === to.form_chapter_id)
               .sort((a, b) => a.ordinal - b.ordinal)
               .forEach((i, n) => { i.ordinal = n + 1; });
         }
         return updated;
      });
      setIsDirty(true);
   }

   /* ---- Drag handlers — chapter ---- */

   function handleChapterDragStart(e, chapterId) {
      if (e.target.closest && e.target.closest('.fe-item')) return;
      dragRef.current = { type: 'chapter', id: chapterId };
      const el = e.currentTarget;
      setTimeout(() => el.classList.add('fe-dragging'), 0);
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text', 'chapter');
   }

   function handleChapterDragOver(e, chapterId) {
      if (!dragRef.current) return;
      if (dragRef.current.type === 'chapter' && dragRef.current.id !== chapterId) {
         e.preventDefault(); e.stopPropagation();
         e.currentTarget.classList.add('fe-drop-target');
      } else if (dragRef.current.type === 'item') {
         e.preventDefault();
      }
   }

   function handleChapterDragLeave(e) {
      if (!e.currentTarget.contains(e.relatedTarget)) {
         e.currentTarget.classList.remove('fe-drop-target');
      }
   }

   function handleChapterDrop(e, chapterId) {
      e.preventDefault();
      e.currentTarget.classList.remove('fe-drop-target');
      if (!dragRef.current) return;

      if (dragRef.current.type === 'chapter' && dragRef.current.id !== chapterId) {
         const fromId = dragRef.current.id;
         dragRef.current = null;
         reorderChapters(fromId, chapterId);
      } else if (dragRef.current.type === 'item') {
         const dragItem = items.find(i => i._id === dragRef.current.id);
         if (dragItem && dragItem.form_chapter_id !== chapterId) {
            const dragItemId = dragRef.current.id;
            dragRef.current = null;
            moveItemToChapter(dragItemId, chapterId);
         }
      }
   }

   function handleChapterDragEnd(e) {
      e.currentTarget.classList.remove('fe-dragging');
      document.querySelectorAll('.fe-drop-target').forEach(el => el.classList.remove('fe-drop-target'));
      dragRef.current = null;
   }

   /* ---- Drag handlers — item ---- */

   function handleItemDragStart(e, itemId) {
      e.stopPropagation();
      dragRef.current = { type: 'item', id: itemId };
      const el = e.currentTarget;
      setTimeout(() => el.classList.add('fe-dragging'), 0);
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text', 'item');
   }

   function handleItemDragOver(e, itemId) {
      if (dragRef.current?.type === 'item' && dragRef.current.id !== itemId) {
         e.preventDefault(); e.stopPropagation();
         e.currentTarget.classList.add('fe-drop-target');
      }
   }

   function handleItemDragLeave(e) {
      e.currentTarget.classList.remove('fe-drop-target');
   }

   function handleItemDrop(e, itemId) {
      e.preventDefault(); e.stopPropagation();
      e.currentTarget.classList.remove('fe-drop-target');
      if (!dragRef.current || dragRef.current.type !== 'item') return;
      const fromId = dragRef.current.id;
      if (fromId === itemId) { dragRef.current = null; return; }
      dragRef.current = null;
      reorderItems(fromId, itemId);
   }

   function handleItemDragEnd(e) {
      e.currentTarget.classList.remove('fe-dragging');
      document.querySelectorAll('.fe-drop-target').forEach(el => el.classList.remove('fe-drop-target'));
      dragRef.current = null;
   }

   /* ---- Save ---- */

   function save() {
      const saveData = { ...metadataRef.current };
      const sortedChapters = [...chapters].sort((a, b) => a.ordinal - b.ordinal);

      saveData.form_chapters = sortedChapters.map((c, i) => ({
         id: c.id, name: c.name, ordinal: i + 1,
      }));

      saveData.form_items = [];
      sortedChapters.forEach(chapter => {
         items
            .filter(i => i.form_chapter_id === chapter.id)
            .sort((a, b) => a.ordinal - b.ordinal)
            .forEach((item, iIdx) => {
               const clean = Object.fromEntries(Object.entries(item).filter(([k]) => k !== '_id'));
               clean.ordinal = iIdx + 1;
               saveData.form_items.push(clean);
            });
      });

      window.ajaxPatch(
         `/api/v1/items/FormTemplate/${templateId}`,
         { formdata: JSON.stringify(saveData) },
         () => {
            setIsDirty(false);
            window.showDialog(t('Saved'), t('The template has been saved successfully.'));
         }
      );
   }

   /* ---- Render ---- */

   const sortedChapters = [...chapters].sort((a, b) => a.ordinal - b.ordinal);

   return (
      <div>
         {/* Header */}
         <div className="d-flex align-items-center mb-3 gap-2 flex-wrap">
            <a href="/management/formtemplates" className="btn btn-sm btn-outline-secondary">
               <span className="material-symbols-rounded">arrow_back</span>
               {t('Back')}
            </a>
            <h4 className="mb-0 flex-grow-1">
               {t('Edit form template')}: {templateName}
            </h4>
            <button className="btn btn-primary" onClick={save}>
               <span className="material-symbols-rounded">save</span>
               {t('Save')}
            </button>
         </div>

         {/* Chapter list */}
         {sortedChapters.length === 0 ? (
            <div className="alert alert-light text-center p-4">
               {t('No chapters yet. Add a chapter to get started.')}
            </div>
         ) : (
            sortedChapters.map(chapter => {
               const chapterItems = items
                  .filter(i => i.form_chapter_id === chapter.id)
                  .sort((a, b) => a.ordinal - b.ordinal);
               return (
                  <ChapterCard
                     key={chapter.id}
                     chapter={chapter}
                     items={chapterItems}
                     onEditChapter={openChapterModal}
                     onDeleteChapter={confirmDeleteChapter}
                     onAddItem={chapterId => openItemModal(null, chapterId)}
                     onEditItem={id => openItemModal(id, null)}
                     onDeleteItem={confirmDeleteItem}
                     onDragStart={handleChapterDragStart}
                     onDragOver={handleChapterDragOver}
                     onDragLeave={handleChapterDragLeave}
                     onDrop={handleChapterDrop}
                     onDragEnd={handleChapterDragEnd}
                     onItemDragStart={handleItemDragStart}
                     onItemDragOver={handleItemDragOver}
                     onItemDragLeave={handleItemDragLeave}
                     onItemDrop={handleItemDrop}
                     onItemDragEnd={handleItemDragEnd}
                  />
               );
            })
         )}

         <button className="btn btn-outline-primary mt-2" onClick={() => openChapterModal(null)}>
            <span className="material-symbols-rounded">add</span> {t('Add chapter')}
         </button>

         {/* Modals */}
         <ChapterModal
            open={chapterModal.open}
            chapter={chapterModal.chapterId !== null ? chapters.find(c => c.id === chapterModal.chapterId) : null}
            onSave={saveChapter}
            onClose={() => setChapterModal({ open: false, chapterId: null })}
         />
         <ItemModal
            open={itemModal.open}
            item={itemModal.itemId !== null ? items.find(i => i._id === itemModal.itemId) : null}
            onSave={saveItem}
            onClose={() => setItemModal({ open: false, itemId: null, defaultChapterId: null })}
         />
         <ConfirmModal
            open={confirmModal.open}
            title={confirmModal.title}
            message={confirmModal.message}
            warning={confirmModal.warning}
            onConfirm={confirmModal.onConfirm}
            onClose={() => setConfirmModal(m => ({ ...m, open: false }))}
         />
      </div>
   );
}
