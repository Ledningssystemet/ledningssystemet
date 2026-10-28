import React, { useEffect, useRef } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import type { ReactNode, InputHTMLAttributes, SelectHTMLAttributes, TextareaHTMLAttributes, ButtonHTMLAttributes } from 'react';
import { t } from '../../lib/i18n';
// @ts-ignore
import './ui.css';

export { Gantt, type GanttProps, type GanttGroup, type GanttRow } from './Gantt';

export type Option = { id: number; name: string };

export function Icon({ name }: { name: string }) {
    return <span className="material-symbols-rounded ls-icon" aria-hidden="true">{name}</span>;
}

export function Options({ items, empty = true }: { items: Option[]; empty?: boolean }) {
    return <>{empty && <option value="">{t('None')}</option>}{items.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</>;
}

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <div className={`ls-card ${className}`}>{children}</div>;
}
export function SectionTitle({ title, action }: { title: string; action?: ReactNode }) {
    return <div className="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><h2 className="ls-section-title">{title}</h2>{action}</div>;
}
export function Btn({ variant = 'primary', className = '', type = 'button', ...props }: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: 'primary' | 'ghost' | 'outline' | 'warning' | 'danger' }) {
    return <button {...props} type={type} className={`ls-button ls-button-${variant} ${className}`} />;
}
export const Input = ({ className = '', ...props }: InputHTMLAttributes<HTMLInputElement>) => <input {...props} className={`form-control ls-field ${className}`} />;
export const Select = ({ className = '', ...props }: SelectHTMLAttributes<HTMLSelectElement>) => <select {...props} className={`form-select ls-field ${className}`} />;
export const Textarea = ({ className = '', ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) => <textarea {...props} className={`form-control ls-field ${className}`} />;
type Select2Select = {
    select2: (options?: { width: string } | 'destroy') => Select2Select;
    on: (eventName: string, callback: () => void) => Select2Select;
    off: (eventName: string) => Select2Select;
    val: (values?: string[]) => string[] | Select2Select | null;
    trigger: (eventName: string) => Select2Select;
};
type JQueryWindow = Window & { $?: (element: HTMLSelectElement) => Select2Select };

export function MultiSelect2({ items, values, onChange, label }: {
    items: Option[]; values: number[]; onChange: (values: number[]) => void; label: string;
}) {
    const selectRef = useRef<HTMLSelectElement>(null);
    const onChangeRef = useRef(onChange);
    const valueKey = values.join(',');

    useEffect(() => { onChangeRef.current = onChange; }, [onChange]);
    useEffect(() => {
        const selectElement = selectRef.current;
        const jquery = (window as JQueryWindow).$;
        if (!selectElement || !jquery) {
            throw new Error('Select2 is not available');
        }

        const select = jquery(selectElement);
        select.select2({ width: '100%' });
        select.on('change.sharedMultiSelect', () => {
            const selected = select.val();
            onChangeRef.current(Array.isArray(selected) ? selected.map(Number) : []);
        });
        return () => {
            select.off('.sharedMultiSelect');
            select.select2('destroy');
        };
    }, []);
    useEffect(() => {
        const selectElement = selectRef.current;
        const jquery = (window as JQueryWindow).$;
        if (!selectElement || !jquery) {
            return;
        }

        jquery(selectElement).val(valueKey ? valueKey.split(',') : []).trigger('change.select2');
    }, [valueKey]);

    return <select ref={selectRef} className="form-select ls-field" aria-label={label} multiple defaultValue={values.map(String)}>
        {items.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}
    </select>;
}
export function Label({ text, children }: { text: string; children: ReactNode }) {
    return <label className="d-block mb-3"><span className="ls-field-label d-block mb-1">{text}</span>{children}</label>;
}
export function Badge({ children, tone = 'muted' }: { children: ReactNode; tone?: 'muted' | 'ok' | 'warn' | 'bad' | 'info' | 'outline' }) {
    return <span className={`ls-badge ls-badge-${tone}`}>{children}</span>;
}

export function Popup({ title, trigger, open, onOpenChange, children }: {
    title: string; trigger: ReactNode; open: boolean; onOpenChange: (open: boolean) => void; children: ReactNode;
}) {
    return <Dialog.Root open={open} onOpenChange={onOpenChange}>
        <Dialog.Trigger asChild>{trigger}</Dialog.Trigger>
        <Dialog.Portal>
            <Dialog.Overlay className="ls-popup-overlay" />
            <Dialog.Content className="ls-popup" aria-describedby={undefined}>
                <div className="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <Dialog.Title className="ls-section-title">{title}</Dialog.Title>
                    <Dialog.Close asChild><Btn variant="ghost" aria-label={t('Close')}><Icon name="close" /></Btn></Dialog.Close>
                </div>
                {children}
            </Dialog.Content>
        </Dialog.Portal>
    </Dialog.Root>;
}
