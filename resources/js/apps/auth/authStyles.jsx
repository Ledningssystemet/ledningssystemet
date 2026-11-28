import React from 'react';
import { Eye, EyeOff } from 'lucide-react';

export const labelClass = 'mb-1 block text-sm';
const buttonClass = 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50';
export const primaryButtonClass = `${buttonClass} bg-primary text-primary-foreground hover:bg-primary/90 w-full justify-center disabled:cursor-not-allowed`;
export const outlineButtonClass = `${buttonClass} border bg-card hover:bg-surface-hover`;
export const checkboxClass = 'flex h-4 w-4 items-center justify-center rounded border border-input bg-card data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary data-[state=indeterminate]:text-primary-foreground flex-none p-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
export const inputClass = 'w-full rounded-md border border-input bg-card px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-ring';
export const warningClass = 'mb-4 rounded-md border bg-badge-warning px-3 py-2 text-sm text-badge-warning-foreground';
export const passwordToggleClass = 'absolute pw-toggle border-input inset-y-0 right-0 flex w-9 items-center justify-center rounded-md border-0 text-muted-foreground hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

export const eyeOpenIcon = <Eye size={16} aria-hidden="true" />;
export const eyeClosedIcon = <EyeOff size={16} aria-hidden="true" />;
