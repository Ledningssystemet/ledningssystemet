import React, { useEffect, useRef, useState } from 'react';
import { t } from '../../lib/i18n';
// @ts-ignore
import './dropzone.css';

export type DropzoneProps = {
    onUpload: (file: File, signal: AbortSignal) => Promise<unknown>;
    onUploaded?: (file: File) => void;
    errorMessage?: (error: unknown) => string;
    disabled?: boolean;
};

type Upload = {
    id: number;
    file: File;
    status: 'uploading' | 'uploaded' | 'error';
    error?: string;
};

export function Dropzone({ onUpload, onUploaded, errorMessage, disabled = false }: DropzoneProps) {
    const [uploads, setUploads] = useState<Upload[]>([]);
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const nextId = useRef(0);
    const active = useRef(false);
    const controllers = useRef(new Map<number, AbortController>());
    const callbacks = useRef({ onUpload, onUploaded, errorMessage });
    callbacks.current = { onUpload, onUploaded, errorMessage };

    useEffect(() => {
        active.current = true;
        const pending = controllers.current;
        return () => {
            active.current = false;
            pending.forEach(controller => controller.abort());
            pending.clear();
        };
    }, []);

    const upload = async (id: number, file: File) => {
        if (controllers.current.has(id)) return;
        const controller = new AbortController();
        controllers.current.set(id, controller);
        setUploads(previous => previous.map(item => item.id === id ? { ...item, status: 'uploading', error: undefined } : item));
        try {
            await callbacks.current.onUpload(file, controller.signal);
        } catch (error) {
            if (!active.current || controller.signal.aborted) return;
            const message = callbacks.current.errorMessage?.(error)
                || (error instanceof Error && error.message)
                || t('The upload failed');
            setUploads(previous => previous.map(item => item.id === id ? { ...item, status: 'error', error: message } : item));
            return;
        } finally {
            controllers.current.delete(id);
        }
        if (!active.current || controller.signal.aborted) return;
        setUploads(previous => previous.map(item => item.id === id ? { ...item, status: 'uploaded' } : item));
        callbacks.current.onUploaded?.(file);
    };

    const addFiles = (files: FileList | File[]) => {
        if (disabled) return;
        const added: Upload[] = Array.from(files, file => ({ id: ++nextId.current, file, status: 'uploading' }));
        setUploads(previous => [...previous, ...added]);
        added.forEach(item => { void upload(item.id, item.file); });
    };

    return <div className="ls-dropzone-wrapper">
        <div className={`ls-dropzone${dragging ? ' ls-dropzone-dragging' : ''}${disabled ? ' ls-dropzone-disabled' : ''}`}
            onDragEnter={event => { event.preventDefault(); if (!disabled) setDragging(true); }}
            onDragOver={event => { event.preventDefault(); event.dataTransfer.dropEffect = disabled ? 'none' : 'copy'; }}
            onDragLeave={event => { event.preventDefault(); if (!event.currentTarget.contains(event.relatedTarget as Node | null)) setDragging(false); }}
            onDrop={event => { event.preventDefault(); setDragging(false); addFiles(event.dataTransfer.files); }}>
            <p>{t('Drop files here to upload')}</p>
            <input ref={inputRef} type="file" multiple hidden disabled={disabled} onChange={event => {
                if (event.target.files) addFiles(event.target.files);
                event.target.value = '';
            }} />
            <button type="button" className="ls-dropzone-select" disabled={disabled} onClick={() => inputRef.current?.click()}>{t('Select files')}</button>
        </div>
        {uploads.length > 0 && <ul className="ls-dropzone-uploads" aria-live="polite">
            {uploads.map(item => <li key={item.id}>
                <span className="ls-dropzone-filename">{item.file.name}</span>
                {item.status === 'uploading' && <span role="status">{t('Uploading…')}</span>}
                {item.status === 'uploaded' && <span>{t('Uploaded')}</span>}
                {item.status === 'error' && <span role="alert">{item.error}</span>}
                {item.status === 'error' && <button type="button" className="ls-dropzone-action" disabled={disabled} onClick={() => { void upload(item.id, item.file); }}>{t('Retry')}</button>}
                {item.status !== 'uploading' && <button type="button" className="ls-dropzone-action" onClick={() => setUploads(previous => previous.filter(upload => upload.id !== item.id))}>{t('Dismiss')}</button>}
            </li>)}
        </ul>}
    </div>;
}
