import React, { useState } from 'react';
import { SummaryCards } from './SummaryCards.jsx';
import { ScopeTabs } from './ScopeTabs.jsx';
import { ScopeSection } from './ScopeSection.jsx';
import { EmissionFactorList } from './EmissionFactorList.jsx';
import { useGhgData } from './useGhgData.js';
import { t } from './t.js';

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
        <div className="py-3">
            {/* Header */}
            <div className="card border-0 shadow-sm rounded-3 p-4 mb-4">
                <div className="mb-3">
                    <span className="badge rounded-pill d-inline-flex align-items-center gap-1"
                        style={{ background: '#e8f2ec', color: '#1e4d3a', fontWeight: 600, fontSize: '0.78rem', padding: '0.4rem 0.8rem' }}>
                        <span className="material-symbols-rounded" style={{ fontSize: '0.9rem' }}>trending_up</span>
                        {t("Calculation according to the GHG protocol")}
                    </span>
                </div>
                <h2 style={{ fontWeight: 800, fontSize: '1.6rem', marginBottom: '0.5rem' }}>
                    {t("Calculate your climate footprint")}
                </h2>
                <p className="mb-0 text-muted" style={{ fontSize: '0.88rem', maxWidth: 620 }}>
                    {t("Add activities under scope 1, 2 and 3 utilizing the available emission factors. The emission factors are configurable in the system-wide assessment settings.")}
                </p>
                <div className="row g-2 mt-3">
                    <div className="col-12 col-md-3">
                        <label className="form-label mb-1" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('From')}</label>
                        <input
                            type="date"
                            className="form-control form-control-sm"
                            value={from}
                            onChange={e => setFrom(e.target.value)}
                            max={to || undefined}
                        />
                    </div>
                    <div className="col-12 col-md-3">
                        <label className="form-label mb-1" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('To')}</label>
                        <input
                            type="date"
                            className="form-control form-control-sm"
                            value={to}
                            onChange={e => setTo(e.target.value)}
                            min={from || undefined}
                        />
                    </div>
                </div>
            </div>

            {error && (
                <div className="alert alert-danger mb-4" role="alert">
                    <strong>{t("Error")}:</strong> {error}
                </div>
            )}

            {loading ? (
                <div className="d-flex align-items-center gap-2 text-muted mb-4" style={{ fontSize: '0.9rem' }}>
                    <div className="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                    {t("Loading data…")}
                </div>
            ) : (
                <>
                    {/* Summary cards */}
                    <SummaryCards
                        factors={factors}
                        conversionFactors={conversionFactors}
                        categories={categories}
                    />

                    {/* Scope tabs */}
                    <ScopeTabs activeScope={activeScope} onScopeChange={setActiveScope} />

                    {/* Active scope section */}
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

                    {/* Emission factors reference */}
                    <EmissionFactorList
                        conversionFactors={conversionFactors}
                        categories={categories.filter(c => c.scope === activeScope)}
                    />
                </>
            )}
        </div>
    );
}
