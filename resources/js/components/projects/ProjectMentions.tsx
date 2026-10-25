import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { collection, errorMessage, mentionUrl, t, type MeetingMention } from './api';
import { Badge, Btn, Popup } from '../ui';
import './mentions.css';

export function ProjectMentions() {
    const [mentions, setMentions] = useState<MeetingMention[]>([]);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        const refresh = () => setAttempt(value => value + 1);
        window.addEventListener('focus', refresh);
        window.addEventListener('project-mentions-changed', refresh);
        return () => {
            window.removeEventListener('focus', refresh);
            window.removeEventListener('project-mentions-changed', refresh);
        };
    }, []);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true); setError(null);
        collection<MeetingMention>('ProjectMeetingMention', { sort: '-created_at' }, controller.signal)
            .then(rows => { if (!controller.signal.aborted) setMentions(rows.filter(row => !row.acknowledged_at)); })
            .catch(error => { if (!axios.isCancel(error)) setError(errorMessage(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [attempt, open]);

    const projects = Array.from(new Set(mentions.map(mention => mention.project_id)));
    return <Popup title={t('Project mentions')} open={open} onOpenChange={setOpen}
        trigger={<Btn variant="outline" className="pm-mentions-trigger" aria-label={`${t('Project mentions')}: ${error ? t('Could not fetch data') : loading ? t('Loading data…') : mentions.length}`}>
            <span aria-hidden="true">@</span><Badge tone={error ? 'bad' : 'info'}>{error ? '!' : loading ? '…' : mentions.length}</Badge>
        </Btn>}>
        {error && <div role="alert" className="alert alert-danger">{error} <Btn variant="outline" onClick={() => setAttempt(value => value + 1)}>{t('Retry')}</Btn></div>}
        {loading && <p role="status">{t('Loading data…')}</p>}
        {!loading && !error && !mentions.length && <p role="status">{t('No unacknowledged project mentions')}</p>}
        {!loading && !error && projects.map(projectId => {
            const rows = mentions.filter(mention => mention.project_id === projectId);
            return <section key={projectId} className="pm-mentions-project">
                <h3 className="h6">{rows[0].project_name}</h3>
                <ul className="list-unstyled mb-0">{rows.map(mention => <li key={mention.id}>
                    <a className="pm-mention-link" href={mentionUrl(mention)}>
                        <strong>{mention.meeting_name}</strong>
                        <span className="pm-mention-excerpt">{mention.excerpt}</span>
                        <span>{t('Open meeting notes')}</span>
                    </a>
                </li>)}</ul>
            </section>;
        })}
    </Popup>;
}
