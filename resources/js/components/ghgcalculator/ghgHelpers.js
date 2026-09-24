/**
 * Helpers to navigate GHG model relationships:
 * GhgCategory → GhgConversionFactor → GhgFactor → GhgFactorReport
 */

export function getConversionFactor(conversionFactors, id) {
    return conversionFactors.find(cf => cf.id === id) ?? null;
}

export function getCategoryForConversionFactor(categories, conversionFactors, cfId) {
    const cf = getConversionFactor(conversionFactors, cfId);
    if (!cf) return null;
    return categories.find(c => c.id === cf.ghg_category_id) ?? null;
}

export function getScopeForFactor(factor, conversionFactors, categories) {
    const category = getCategoryForConversionFactor(categories, conversionFactors, factor.ghg_conversion_factor_id);
    return category?.scope ?? null;
}

export function getFactorsByScope(factors, conversionFactors, categories, scope) {
    return factors.filter(f => getScopeForFactor(f, conversionFactors, categories) === scope);
}

export function getFactorsByCategory(factors, conversionFactors, categoryId) {
    return factors.filter(f => {
        const cf = getConversionFactor(conversionFactors, f.ghg_conversion_factor_id);
        return cf?.ghg_category_id === categoryId;
    });
}

export function findFactorForConversionFactor(factors, conversionFactorId, isActivityBased) {
    return factors.find(f =>
        f.ghg_conversion_factor_id === conversionFactorId &&
        Boolean(f.is_activity_based) === Boolean(isActivityBased)
    ) ?? null;
}

export function hasConfiguredConversionFactorValue(conversionFactor, isActivityBased) {
    if (!conversionFactor) {
        return false;
    }

    return isActivityBased
        ? conversionFactor.activity_factor != null
        : conversionFactor.spend_factor != null;
}

export function getSelectableFactorOptionsByCategory(factors, conversionFactors, categoryId) {
    return conversionFactors
        .filter(cf => cf.ghg_category_id === categoryId)
        .flatMap((cf) => {
            const options = [];

            if (hasConfiguredConversionFactorValue(cf, true)) {
                const existingFactor = findFactorForConversionFactor(factors, cf.id, true);
                options.push({
                    key: `${cf.id}:activity`,
                    factorId: existingFactor?.id ?? null,
                    conversionFactorId: cf.id,
                    isActivityBased: true,
                    name: cf.name,
                    unit: cf.activity_sourceunit ?? '',
                });
            }

            if (hasConfiguredConversionFactorValue(cf, false)) {
                const existingFactor = findFactorForConversionFactor(factors, cf.id, false);
                options.push({
                    key: `${cf.id}:spend`,
                    factorId: existingFactor?.id ?? null,
                    conversionFactorId: cf.id,
                    isActivityBased: false,
                    name: cf.name,
                    unit: cf.spend_sourceunit ?? '',
                });
            }

            return options;
        });
}

export function calcEmissionForReport(report, factors, conversionFactors) {
    const factor = factors.find(f => f.id === report.ghg_factor_id);
    if (!factor) return 0;
    const cf = getConversionFactor(conversionFactors, factor.ghg_conversion_factor_id);
    if (!cf) return 0;
    const rate = factor.is_activity_based ? cf.activity_factor : cf.spend_factor;
    return (report.value ?? 0) * (rate ?? 0);
}

export function getLatestManualReport(reports, factorId) {
    return reports
        .filter(report => report.ghg_factor_id === factorId)
        .sort((a, b) => {
            const dateCompare = String(b.valuedate ?? '').localeCompare(String(a.valuedate ?? ''));
            if (dateCompare !== 0) {
                return dateCompare;
            }

            return (b.id ?? 0) - (a.id ?? 0);
        })[0] ?? null;
}

export function getLatestMetricReport(metricReports, metricId) {
    return metricReports
        .filter(report => report.process_performance_metric_id === metricId)
        .sort((a, b) => {
            const dateCompare = String(b.reporting_date_at ?? '').localeCompare(String(a.reporting_date_at ?? ''));
            if (dateCompare !== 0) {
                return dateCompare;
            }

            return (b.id ?? 0) - (a.id ?? 0);
        })[0] ?? null;
}

export function getSourceValueForFactor(factor, reports, metricReports) {
    if (factor.process_performance_metrics_id) {
        const metricReport = getLatestMetricReport(metricReports, factor.process_performance_metrics_id);
        if (!metricReport) {
            return null;
        }

        return {
            type: 'metric',
            date: metricReport.reporting_date_at ?? null,
            value: metricReport.calculatedvalue ?? metricReport.reportvalue ?? null,
            rawReport: metricReport,
        };
    }

    const manualReport = getLatestManualReport(reports, factor.id);
    if (!manualReport) {
        return null;
    }

    return {
        type: 'manual',
        date: manualReport.valuedate ?? null,
        value: manualReport.value ?? null,
        rawReport: manualReport,
    };
}

export function calcEmissionForFactor(factor, reports, metricReports, conversionFactors) {
    const sourceValue = getSourceValueForFactor(factor, reports, metricReports);
    if (!sourceValue) {
        return 0;
    }

    const cf = getConversionFactor(conversionFactors, factor.ghg_conversion_factor_id);
    if (!cf) {
        return 0;
    }

    const rate = factor.is_activity_based ? cf.activity_factor : cf.spend_factor;
    return Number(sourceValue.value ?? 0) * Number(rate ?? 0);
}

export function getEmissionCo2eKgForFactor(factor) {
    const value = Number(factor?.emission_co2e_kg);
    return Number.isFinite(value) ? value : 0;
}

export function calcTotalByScope(factors, conversionFactors, categories, scope) {
    return factors
        .filter(factor => getScopeForFactor(factor, conversionFactors, categories) === scope)
        .reduce((sum, factor) => sum + getEmissionCo2eKgForFactor(factor), 0);
}

export function calcGrandTotal(factors) {
    return factors.reduce((sum, factor) => sum + getEmissionCo2eKgForFactor(factor), 0);
}

export function fmtEmission(kg) {
    return `${Math.round(Number(kg) || 0).toLocaleString('sv-SE')} kg`;
}
