import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { confirmAction, errorMessage, t, type Access, type Project } from './api';
import { Btn, Card, Dropzone, Icon, Label, SectionTitle, Textarea } from '../ui';

type ProjectFile = {
    id: number;
    name: string;
    description: string | null;
    filename: string;
    created_by: string;
    created_at_pretty: string;
    access: Access;
};

const base = '/api/v1/items/File';

export function ProjectFiles({ project }: { project: Project }) {
    const [files, setFiles] = useState<ProjectFile[]>([]);
    const [editing, setEditing] = useState<ProjectFile | null>(null);
    const [description, setDescription] = useState('');
    const [revision, setRevision] = useState(0);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const busyRef = useRef(false);
    const editable = !project.archived_at && project.access.write;

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        setError(null);
        axios.get(base, { params: { object_type: 'Project', object_id: project.id }, signal: controller.signal })
            .then(({ data }) => {
                if (controller.signal.aborted) return;
                if (Array.isArray(data)) setFiles(data);
                else if (data && typeof data === 'object' && Object.keys(data).every(key => /^\d+$/.test(key))) setFiles(Object.values(data));
                else throw new Error(t('Unexpected API response'));
            })
            .catch(error => { if (!axios.isCancel(error)) setError(errorMessage(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [project.id, revision]);

    const mutate = async (operation: () => Promise<unknown>) => {
        if (busyRef.current) return false;
        busyRef.current = true;
        setBusy(true);
        setError(null);
        try {
            await operation();
            setRevision(value => value + 1);
            return true;
        } catch (error) {
            setError(errorMessage(error));
            return false;
        } finally {
            busyRef.current = false;
            setBusy(false);
        }
    };

    return <Card><SectionTitle title={t('Files')} />
        {error && <div className="alert alert-danger" role="alert">{error} <Btn variant="outline" onClick={() => setRevision(value => value + 1)}>{t('Retry')}</Btn></div>}
        {editable && <div className="mb-3"><Dropzone disabled={busy} errorMessage={errorMessage}
            onUpload={(file, signal) => {
                const data = new FormData();
                data.append('object_type', 'Project');
                data.append('object_id', String(project.id));
                data.append('file', file);
                return axios.post(base, data, { signal });
            }} onUploaded={() => setRevision(value => value + 1)} /></div>}
        {editing && editable && <form className="border rounded p-3 mb-3" onSubmit={async event => {
            event.preventDefault();
            if (await mutate(() => axios.patch(`${base}/${editing.id}`, { description }))) setEditing(null);
        }}>
            <Label text={t('Description')}><Textarea value={description} onChange={event => setDescription(event.target.value)} /></Label>
            <div className="d-flex gap-2"><Btn type="submit" disabled={busy}>{t('Save')}</Btn><Btn variant="ghost" disabled={busy} onClick={() => setEditing(null)}>{t('Cancel')}</Btn></div>
        </form>}
        {loading ? <p role="status">{t('Loading data…')}</p> : !error && <>
            <div className="table-responsive"><table className="table align-middle">
                <thead><tr>{['Name', 'Description', 'Created', 'Created by', 'Actions'].map(label => <th key={label} scope="col">{t(label)}</th>)}</tr></thead>
                <tbody>{files.map(file => <tr key={file.id}>
                    <td>{file.filename}{file.name !== file.filename && <div className="text-muted small">{file.name}</div>}</td>
                    <td>{file.description}</td><td>{file.created_at_pretty}</td><td>{file.created_by}</td>
                    <td><div className="d-flex flex-wrap gap-2">
                        <a className="ls-button ls-button-outline" href={`${base}/${file.id}/download`}><Icon name="download" />{t('Download')}</a>
                        {editable && file.access.update && <Btn variant="outline" disabled={busy} onClick={() => { setEditing(file); setDescription(file.description ?? ''); }}><Icon name="edit" />{t('Edit')}</Btn>}
                        {editable && file.access.delete && <Btn variant="outline" disabled={busy} onClick={() => confirmAction('Delete this file?', () => {
                            void mutate(() => axios.delete(`${base}/${file.id}`)).then(saved => { if (saved && editing?.id === file.id) setEditing(null); });
                        }, 'Delete file')}><Icon name="delete" />{t('Delete')}</Btn>}
                    </div></td>
                </tr>)}</tbody>
            </table></div>
            {!files.length && <p className="text-muted">{t('No files')}</p>}
        </>}
    </Card>;
}
