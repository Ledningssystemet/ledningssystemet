import React, { useState } from 'react';
import { t } from './t.js';

function FactorValue({ label, factor, sourceunit, datasource, datasourceUrl }) {
    return (
        <div className="ghg-factor-value">
            <span className="ghg-factor-amount">
                {Number(factor).toLocaleString('sv-SE')}{' '}
                <span className="ghg-detail">
                    kg/{sourceunit ?? ''}
                </span>
            </span>
            {datasource && (
                <div className="ghg-detail">
                    {datasourceUrl ? (
                        <a href={datasourceUrl} target="_blank" rel="noreferrer noopener">
                            {datasource}
                        </a>
                    ) : (
                        datasource
                    )}
                </div>
            )}
            <div className="ghg-detail">{label}</div>
        </div>
    );
}

function FactorCard({ cf }) {
    const hasActivity = cf.activity_factor != null;
    const hasSpend = cf.spend_factor != null;

    return (
        <div className="col-12 col-md-6">
            <div className="ghg-reference-card h-100">
                <div>
                    <div className="ghg-item-title">{cf.name}</div>
                    {(cf.activity_datasource_name || cf.spend_datasource_name) && !hasActivity && !hasSpend && (
                        <div className="ghg-detail">
                            {cf.activity_datasource_url || cf.spend_datasource_url ? (
                                <a
                                    href={cf.activity_datasource_url ?? cf.spend_datasource_url}
                                    target="_blank"
                                    rel="noreferrer noopener"
                                >
                                    {cf.activity_datasource_name ?? cf.spend_datasource_name}
                                </a>
                            ) : (
                                cf.activity_datasource_name ?? cf.spend_datasource_name
                            )}
                        </div>
                    )}
                    <div className="ghg-detail">
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
        <div className="ghg-reference-category">
            <div className="ghg-item-title">{category.name}</div>
            {category.description && (
                <div className="ghg-detail mb-2">{category.description}</div>
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
        <div className="ghg-card ghg-reference">
            <button
                type="button"
                className="ghg-reference-toggle"
                onClick={() => setOpen(o => !o)}
                aria-expanded={open}
            >
                <span className="ghg-section-title">{ t("Available emission factors") }</span>
                <span
                    className="material-symbols-rounded ghg-reference-chevron"
                    aria-hidden="true"
                >
                    expand_more
                </span>
            </button>

            {open && (
                <div className="ghg-reference-body">
                    {(() => {
                        const categoryIds = new Set(categories.map(c => c.id));
                        const configured = conversionFactors.filter(
                            cf => categoryIds.has(cf.ghg_category_id) && (cf.activity_factor != null || cf.spend_factor != null)
                        );
                        if (configured.length === 0) {
                            return (
                                <p className="text-muted mb-0">
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
