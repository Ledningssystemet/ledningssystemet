import React from 'react';
import { t } from './t.js';
import { calcTotalByScope, calcGrandTotal, fmtEmission } from './ghgHelpers.js';

export function SummaryCards({ factors, conversionFactors, categories }) {
    const total = calcGrandTotal(factors);
    const scope1 = calcTotalByScope(factors, conversionFactors, categories, 1);
    const scope2 = calcTotalByScope(factors, conversionFactors, categories, 2);
    const scope3 = calcTotalByScope(factors, conversionFactors, categories, 3);

    const cards = [
        { label: t('TOTAL'), value: fmtEmission(total), sub: 'kg CO₂e', dark: true },
        { label: t('SCOPE 1'), value: fmtEmission(scope1), sub: t('Direct emissions'), dark: false },
        { label: t('SCOPE 2'), value: fmtEmission(scope2), sub: t('Energy'), dark: false },
        { label: t('SCOPE 3'), value: fmtEmission(scope3), sub: t('Value chain'), dark: false },
    ];

    return (
        <div className="row g-3 mb-4">
            {cards.map((card) => (
                <div key={card.label} className="col-6 col-md-3">
                    <div
                        className="card h-100 border-0 shadow-sm rounded-3 p-3"
                        style={card.dark ? { background: '#1e4d3a', color: '#fff' } : {}}
                    >
                        <div style={{ fontSize: '0.7rem', fontWeight: 600, letterSpacing: '0.08em', opacity: card.dark ? 0.8 : 0.5, marginBottom: 4 }}>
                            {card.label}
                        </div>
                        <div style={{ fontSize: '1.8rem', fontWeight: 700, lineHeight: 1.1 }}>{card.value}</div>
                        <div style={{ fontSize: '0.75rem', opacity: card.dark ? 0.75 : 0.55, marginTop: 4 }}>{card.sub}</div>
                    </div>
                </div>
            ))}
        </div>
    );
}
