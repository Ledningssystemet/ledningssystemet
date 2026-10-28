import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { collection, confirmAction, create, errorMessage, remove, t, type Access, type Id, type Run } from './api';
import { Btn, Textarea } from '../ui';

type Entry = { id: Id; created_at_pretty: string; created_by: string; text?: string; comment?: string; access: Access };

export function ObjectActivity({ model, id, run, revision, canComment }: { model: 'Project' | 'ProjectRisk'; id: Id; run: Run; revision: number; canComment: boolean }) {
    const [panel, setPanel] = useState<'history' | 'comments' | null>(null);
    const [entries, setEntries] = useState<Entry[]>([]);
    const [comment, setComment] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [retry, setRetry] = useState(0);
    useEffect(() => {
        const controller = new AbortController();
        if (!panel) return () => controller.abort();
        setLoading(true);
        setError(null);
        collection<Entry>(panel === 'history' ? 'ActivityLog' : 'ObjectMessage', {
            'filter[object_type]': `Ledningssystemet\\Ledningssystemet\\Models\\${model}`,
            'filter[object_id]': id,
            sort: '-created_at',
        }, controller.signal).then(setEntries)
            .catch(error => { if (!axios.isCancel(error)) setError(errorMessage(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [panel, model, id, revision, retry]);
    return <div className="mt-3">
        <div className="d-flex flex-wrap gap-2">
            <Btn variant="outline" aria-expanded={panel === 'history'} onClick={() => setPanel(panel === 'history' ? null : 'history')}>{t('Show history')}</Btn>
            <Btn variant="outline" aria-expanded={panel === 'comments'} onClick={() => setPanel(panel === 'comments' ? null : 'comments')}>{t('Show comments')}</Btn>
        </div>
        {panel && <div className="border-top mt-3 pt-3">
            <h3 className="h6">{t(panel === 'history' ? 'History' : 'Comments')}</h3>
            {error && <div className="alert alert-danger" role="alert">{error} <Btn variant="outline" onClick={() => setRetry(value => value + 1)}>{t('Retry')}</Btn></div>}
            {loading ? <p role="status">{t('Loading data…')}</p> : !error && <ul className="list-unstyled">{entries.map(entry => <li key={entry.id} className="border-bottom py-2">
                <div className="text-muted small">{entry.created_at_pretty} · {entry.created_by}</div>
                <div className="text-break" style={{ whiteSpace: 'pre-wrap' }}>{entry.text ?? entry.comment}</div>
                {panel === 'comments' && entry.access.delete && <Btn variant="ghost" onClick={() => {
                    confirmAction('Are you sure that you want to delete this comment?', () => {
                        void run(() => remove('ObjectMessage', entry.id)).then(saved => { if (saved) setRetry(value => value + 1); });
                    }, 'Delete comment');
                }}>{t('Delete')}</Btn>}
            </li>)}</ul>}
            {panel === 'comments' && canComment && <form onSubmit={async event => {
                event.preventDefault();
                if (await run(() => create('ObjectMessage', { object_type: model, object_id: id, comment: comment.trim() }))) { setComment(''); setRetry(value => value + 1); }
            }}>
                <Textarea aria-label={t('Comment')} placeholder={t('Write your comment here')} rows={3} required value={comment} onChange={event => setComment(event.target.value)} />
                <Btn type="submit" className="mt-2" disabled={!comment.trim()}>{t('Add comment')}</Btn>
            </form>}
        </div>}
    </div>;
}
