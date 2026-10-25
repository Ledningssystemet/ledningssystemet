import React from 'react';
import { t } from './t.js';
import { calcTotalByScope, calcGrandTotal, fmtEmission } from './ghgHelpers.js';

export function SummaryCards({ factors, conversionFactors, categories }) {
    const total = calcGrandTotal(factors);
    const scope1 = calcTotalByScope(factors, conversionFactors, categories, 1);
    const scope2 = calcTotalByScope(factors, conversionFactors, categories, 2);
    const scope3 = calcTotalByScope(factors, conversionFactors, categories, 3);

    const cards = [
        { label: t('TOTAL'), value: fmtEmission(total), sub: 'kg CO₂e', total: true },
        { label: t('SCOPE 1'), value: fmtEmission(scope1), sub: t('Direct emissions') },
        { label: t('SCOPE 2'), value: fmtEmission(scope2), sub: t('Energy') },
        { label: t('SCOPE 3'), value: fmtEmission(scope3), sub: t('Value chain') },
    ];

    return (
        <div className="ghg-stats">
            {cards.map((card) => (
                <div key={card.label} className={`ghg-card ghg-stat${card.total ? ' ghg-stat-total' : ''}`}>
                    <div className="ghg-stat-label">{card.label}</div>
                    <div className="ghg-stat-value">{card.value}</div>
                    <div className="ghg-stat-detail">{card.sub}</div>
                </div>
            ))}
        </div>
    );
}
