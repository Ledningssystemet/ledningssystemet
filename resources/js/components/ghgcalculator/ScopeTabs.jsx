import React from 'react';
import { t } from './t.js';

export function ScopeTabs({ activeScope, onScopeChange }) {
    const scopes = [
        { id: 1, label: t('Scope 1'), sub: t('Direct') },
        { id: 2, label: t('Scope 2'), sub: t('Energy') },
        { id: 3, label: t('Scope 3'), sub: t('Value chain') },
    ];
    return (
        <div className="card border-0 shadow-sm rounded-3 mb-4 p-1" style={{ background: '#f0f4f1' }}>
            <div className="row g-0">
                {scopes.map((scope) => {
                    const isActive = scope.id === activeScope;
                    return (
                        <div key={scope.id} className="col-4">
                            <button
                                type="button"
                                onClick={() => onScopeChange(scope.id)}
                                className="btn w-100 py-3 border-0 rounded-3"
                                style={{
                                    background: isActive ? '#fff' : 'transparent',
                                    boxShadow: isActive ? '0 1px 4px rgba(0,0,0,0.1)' : 'none',
                                    transition: 'background 0.15s',
                                }}
                            >
                                <div style={{ fontWeight: 700, fontSize: '0.95rem', color: '#1e4d3a' }}>{scope.label}</div>
                                <div style={{ fontSize: '0.78rem', color: '#5a7a6a' }}>{scope.sub}</div>
                            </button>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
