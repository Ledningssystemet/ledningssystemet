import React, { useState, type ReactNode } from 'react';
import * as RadixTabs from '@radix-ui/react-tabs';
import type { LucideIcon } from 'lucide-react';
import '../../../css/utilities.css';

export type TabItem = {
    value: string;
    label: string;
    icon?: LucideIcon;
    disabled?: boolean;
    content?: ReactNode;
};

export type TabsProps = {
    items: TabItem[];
    /** Accessible name for the tab navigation. */
    label: string;
    value?: string;
    defaultValue?: string;
    onValueChange?: (value: string) => void;
    className?: string;
};

const triggerClass = 'inline-flex shrink-0 items-center gap-2 rounded-md border px-3 py-2.5 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 sm:px-4';
const activeClass = 'border-list-active-border bg-list-active text-accent-foreground';
const listClass = 'flex gap-2 overflow-x-auto p-1';

/** Without content, renders navigation for panels managed by the parent page. */
export function Tabs({ items, label, value, defaultValue, onValueChange, className = '' }: TabsProps) {
    const [internalValue, setInternalValue] = useState(defaultValue ?? items.find(item => !item.disabled)?.value ?? '');
    const activeValue = value ?? internalValue;
    const select = (next: string) => {
        if (next === activeValue) return;
        if (value === undefined) setInternalValue(next);
        onValueChange?.(next);
    };

    if (!items.length) return null;

    const withContent = items.some(item => item.content !== undefined);
    const triggers = items.map(item => {
        const Icon = item.icon;
        const children = <>{Icon && <Icon size={18} aria-hidden="true" />}{item.label}</>;
        const classes = `${triggerClass} ${activeValue === item.value ? activeClass : 'border-transparent bg-transparent text-muted-foreground'}`;
        return withContent
            ? <RadixTabs.Trigger key={item.value} value={item.value} disabled={item.disabled} className={classes}>{children}</RadixTabs.Trigger>
            : <button key={item.value} type="button" disabled={item.disabled} className={classes}
                aria-current={activeValue === item.value ? 'page' : undefined} onClick={() => select(item.value)}>{children}</button>;
    });

    return withContent
        ? <RadixTabs.Root value={activeValue} onValueChange={select} activationMode="manual" className={className}>
            <RadixTabs.List aria-label={label} className={listClass}>{triggers}</RadixTabs.List>
            {items.map(item => <RadixTabs.Content key={item.value} value={item.value}
                className="mt-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">{item.content}</RadixTabs.Content>)}
        </RadixTabs.Root>
        : <nav aria-label={label} className={`${listClass} ${className}`}>{triggers}</nav>;
}

export default Tabs;
