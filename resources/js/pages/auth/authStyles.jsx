import React from 'react';
import { btnPrimary, cbCls } from '../../components/table/helpers/styles.ts';

export const labelClass = 'mb-1 block text-sm';
export const primaryButtonClass = `${btnPrimary} w-full justify-center disabled:cursor-not-allowed`;
export const checkboxClass = `${cbCls} flex-none p-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring`;
export const warningClass = 'mb-4 rounded-md border bg-badge-warning px-3 py-2 text-sm text-badge-warning-foreground';
export const passwordToggleClass = 'absolute pw-toggle border-input inset-y-0 right-0 flex w-9 items-center justify-center rounded-md border-0 text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export const eyeOpenIcon = (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
        <circle cx="12" cy="12" r="3" />
    </svg>
);

export const eyeClosedIcon = (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M10.7 5.1A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.2M6.6 6.6A18 18 0 0 0 2 12s3.5 7 10 7a10.5 10.5 0 0 0 4.5-1" />
        <path d="m2 2 20 20" />
        <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
    </svg>
);

