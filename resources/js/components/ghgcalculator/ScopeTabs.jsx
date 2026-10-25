import React from 'react';
import { t } from './t.js';

export function ScopeTabs({ activeScope, onScopeChange }) {
    const scopes = [
        { id: 1, label: t('Scope 1'), sub: t('Direct') },
        { id: 2, label: t('Scope 2'), sub: t('Energy') },
        { id: 3, label: t('Scope 3'), sub: t('Value chain') },
    ];
    return (
        <div className="ghg-tabs">
            {scopes.map((scope) => {
                const isActive = scope.id === activeScope;
                return (
                    <button
                        key={scope.id}
                        type="button"
                        onClick={() => onScopeChange(scope.id)}
                        className="ghg-tab"
                        aria-pressed={isActive}
                    >
                        <span>{scope.label}</span>
                        <span className="ghg-tab-detail">{scope.sub}</span>
                    </button>
                );
            })}
        </div>
    );
}
