import React, { useState } from 'react';
import { SummaryCards } from './SummaryCards.jsx';
import { ScopeTabs } from './ScopeTabs.jsx';
import { ScopeSection } from './ScopeSection.jsx';
import { EmissionFactorList } from './EmissionFactorList.jsx';
import { useGhgData } from './useGhgData.js';
import { t } from './t.js';
import './ghgcalculator.css';

export function GhgCalculator() {
    const [activeScope, setActiveScope] = useState(1);
    const [from, setFrom] = useState(new Date(new Date().setFullYear(new Date().getFullYear() - 1)).toISOString().split('T')[0]);
    const [to, setTo] = useState(new Date().toISOString().split('T')[0]);
    const {
        categories,
        conversionFactors,
        factors,
        reports,
        processMetrics,
        loading,
        error,
        addFactor,
        updateFactor,
        addManualReport,
        updateManualReport,
        removeFactor,
        removeReport,
    } = useGhgData({ from, to });

    return (
        <div className="ghg-module">
            <header className="ghg-header">
                <div className="ghg-eyebrow">
                    {t("Calculation according to the GHG protocol")}
                </div>
                <h2 className="ghg-title">
                    {t("Calculate your climate footprint")}
                </h2>
                <p className="ghg-header-description">
                    {t("Add activities under scope 1, 2 and 3 utilizing the available emission factors. The emission factors are configurable in the system-wide assessment settings.")}
                </p>
                <div className="row g-2 mt-3">
                    <div className="col-12 col-md-3">
                        <label htmlFor="ghg-from" className="form-label mb-1">{t('From')}</label>
                        <input
                            id="ghg-from"
                            type="date"
                            className="form-control form-control-sm"
                            value={from}
                            onChange={e => setFrom(e.target.value)}
                            max={to || undefined}
                        />
                    </div>
                    <div className="col-12 col-md-3">
                        <label htmlFor="ghg-to" className="form-label mb-1">{t('To')}</label>
                        <input
                            id="ghg-to"
                            type="date"
                            className="form-control form-control-sm"
                            value={to}
                            onChange={e => setTo(e.target.value)}
                            min={from || undefined}
                        />
                    </div>
                </div>
            </header>

            <div className="ghg-body">
                {error && (
                    <div className="alert alert-danger mb-4" role="alert">
                        <strong>{t("Error")}:</strong> {error}
                    </div>
                )}

                {loading ? (
                    <div className="d-flex align-items-center gap-2 text-muted mb-4">
                        <div className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                        {t("Loading data…")}
                    </div>
                ) : (
                    <>
                        <SummaryCards
                            factors={factors}
                            conversionFactors={conversionFactors}
                            categories={categories}
                        />

                        <ScopeTabs activeScope={activeScope} onScopeChange={setActiveScope} />

                        <ScopeSection
                            scope={activeScope}
                            reports={reports}
                            factors={factors}
                            conversionFactors={conversionFactors}
                            categories={categories}
                            processMetrics={processMetrics}
                            onAddFactor={addFactor}
                            onUpdateFactor={updateFactor}
                            onAddManualReport={addManualReport}
                            onUpdateManualReport={updateManualReport}
                            onRemoveFactor={removeFactor}
                            onRemoveReport={removeReport}
                        />

                        <EmissionFactorList
                            conversionFactors={conversionFactors}
                            categories={categories.filter(c => c.scope === activeScope)}
                        />
                    </>
                )}
            </div>
        </div>
    );
}
