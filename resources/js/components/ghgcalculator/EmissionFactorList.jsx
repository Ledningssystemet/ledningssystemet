import React, { useState } from 'react';
import { t } from './t.js';

function FactorValue({ label, factor, sourceunit, datasource, datasourceUrl }) {
    return (
        <div style={{ textAlign: 'right' }}>
            <span style={{ fontWeight: 700, fontSize: '0.85rem' }}>
                {Number(factor).toLocaleString('sv-SE')}{' '}
                <span style={{ fontWeight: 400, fontSize: '0.75rem', color: '#888' }}>
                    kg/{sourceunit ?? ''}
                </span>
            </span>
            {datasource && (
                <div style={{ fontSize: '0.7rem', color: '#888' }}>
                    {datasourceUrl ? (
                        <a href={datasourceUrl} target="_blank" rel="noreferrer noopener" style={{ color: 'inherit' }}>
                            {datasource}
                        </a>
                    ) : (
                        datasource
                    )}
                </div>
            )}
            <div style={{ fontSize: '0.7rem', color: '#5a7a6a', fontStyle: 'italic' }}>{label}</div>
        </div>
    );
}

function FactorCard({ cf }) {
    const hasActivity = cf.activity_factor != null;
    const hasSpend = cf.spend_factor != null;

    return (
        <div className="col-12 col-md-6">
            <div className="d-flex flex-column p-3 rounded-3 mb-2 h-100" style={{ background: '#fff', border: '1px solid #e2ebe6' }}>
                <div style={{ minWidth: 0 }}>
                    <div style={{ fontWeight: 600, fontSize: '0.85rem' }}>{cf.name}</div>
                    {(cf.activity_datasource_name || cf.spend_datasource_name) && !hasActivity && !hasSpend && (
                        <div style={{ fontSize: '0.75rem', color: '#5a7a6a' }}>
                            {cf.activity_datasource_url || cf.spend_datasource_url ? (
                                <a
                                    href={cf.activity_datasource_url ?? cf.spend_datasource_url}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                    style={{ color: 'inherit' }}
                                >
                                    {cf.activity_datasource_name ?? cf.spend_datasource_name}
                                </a>
                            ) : (
                                cf.activity_datasource_name ?? cf.spend_datasource_name
                            )}
                        </div>
                    )}
                    <div style={{ fontSize: '0.75rem', color: '#5a7a6a' }}>
                        {cf.description ?? cf.description}
                    </div>
                </div>
                <div className="d-flex flex-wrap justify-content-start gap-5 mt-3">
                    {hasActivity && (
                        <FactorValue
                            label={t('Activity based')}
                            factor={cf.activity_factor}
                            sourceunit={cf.activity_sourceunit}
                            datasource={cf.activity_datasource_name}
                            datasourceUrl={cf.activity_datasource_url}
                        />
                    )}
                    {hasSpend && (
                        <FactorValue
                            label={t('Spend based')}
                            factor={cf.spend_factor}
                            sourceunit={cf.spend_sourceunit}
                            datasource={cf.spend_datasource_name}
                            datasourceUrl={cf.spend_datasource_url}
                        />
                    )}
                </div>
            </div>
        </div>
    );
}

function CategoryGroup({ category, conversionFactors }) {
    const catCfs = conversionFactors.filter(
        cf => cf.ghg_category_id === category.id && (cf.activity_factor != null || cf.spend_factor != null)
    );
    if (catCfs.length === 0) return null;

    return (
        <div className="mb-4">
            <div style={{ fontWeight: 700, fontSize: '0.9rem', marginBottom: 2 }}>{category.name}</div>
            {category.description && (
                <div style={{ fontSize: '0.8rem', color: '#5a7a6a', marginBottom: 10 }}>{category.description}</div>
            )}
            <div className="row g-2">
                {catCfs.map(cf => <FactorCard key={cf.id} cf={cf} />)}
            </div>
        </div>
    );
}

export function EmissionFactorList({ conversionFactors, categories }) {
    const [open, setOpen] = useState(true);

    return (
        <div className="card border-0 shadow-sm rounded-3 mb-4" style={{ background: '#f0f4f1' }}>
            <button
                type="button"
                className="btn d-flex align-items-center justify-content-between w-100 p-4 border-0"
                onClick={() => setOpen(o => !o)}
                aria-expanded={open}
            >
                <div>
                    <div style={{ fontWeight: 700, fontSize: '1rem', textAlign: 'left' }}>{ t("Available emission factors") }</div>
                </div>
                <span
                    className="material-symbols-rounded"
                    style={{ fontSize: '1.4rem', color: '#1e4d3a', transition: 'transform 0.2s', transform: open ? 'rotate(180deg)' : 'rotate(0deg)' }}
                >
                    expand_more
                </span>
            </button>

            {open && (
                <div className="px-4 pb-4">
                    {(() => {
                        const categoryIds = new Set(categories.map(c => c.id));
                        const configured = conversionFactors.filter(
                            cf => categoryIds.has(cf.ghg_category_id) && (cf.activity_factor != null || cf.spend_factor != null)
                        );
                        if (configured.length === 0) {
                            return (
                                <p className="text-muted" style={{ fontSize: '0.85rem' }}>
                                    {t("No emission factors are available for this scope")}.
                                </p>
                            );
                        }
                        return categories.map(cat => (
                            <CategoryGroup key={cat.id} category={cat} conversionFactors={conversionFactors} />
                        ));
                    })()}
                </div>
            )}
        </div>
    );
}
