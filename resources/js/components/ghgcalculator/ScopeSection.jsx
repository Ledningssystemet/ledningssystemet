import React, { useMemo, useState } from 'react';
import { t } from './t.js';
import {
    getFactorsByScope,
    getConversionFactor,
    getCategoryForConversionFactor,
    getSelectableFactorOptionsByCategory,
    fmtEmission,
    getEmissionCo2eKgForFactor,
} from './ghgHelpers.js';

const SCOPE_META = {
    1: { key: 'Scope 1 – Direct emissions', desc: 'Emissions from sources the company owns or controls: own vehicles, boilers, processes and refrigerant leakage.' },
    2: { key: 'Scope 2 – Energy', desc: 'Indirect emissions from purchased electricity, district heating and district cooling consumed by the company.' },
    3: { key: 'Scope 3 – Value chain', desc: "Other indirect emissions in the company's value chain, e.g. purchased goods, business travel and commuting." },
};

function normalizePostprocessingFunction(value) {
    const nextValue = String(value ?? '').trim().toLowerCase();
    if (nextValue === '' || nextValue === 'latest') return 'latest';
    if (['sum', 'sum_days', 'sum_months'].includes(nextValue)) return 'sum';
    if (['average', 'avg', 'avg_days', 'avg_months', 'avg_ytd'].includes(nextValue)) return 'average';
    return 'latest';
}

function FactorConfigRow({
    factor,
    reports,
    conversionFactors,
    categories,
    processMetrics,
    onEditFactor,
    onShowManualReport,
    onRemoveFactor,
}) {
    const conversionFactor = getConversionFactor(conversionFactors, factor.ghg_conversion_factor_id);
    const category = conversionFactor ? getCategoryForConversionFactor(categories, conversionFactors, conversionFactor.id) : null;
    const emissionCo2e = getEmissionCo2eKgForFactor(factor);
    const isManual = !factor.process_performance_metrics_id;
    const manualReportCount = isManual
        ? reports.filter(report => report.ghg_factor_id === factor.id).length
        : 0;
    const processMetric = factor.process_performance_metrics_id
        ? processMetrics.find(metric => metric.id === factor.process_performance_metrics_id)
        : null;

    return (
        <div className="border rounded-3 p-3 mb-3" style={{ background: '#fff' }}>
            <div className="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div style={{ fontWeight: 700, fontSize: '0.95rem' }}>{factor.name}</div>
                    <div className="text-muted" style={{ fontSize: '0.78rem' }}>
                        {category?.name ?? '—'} · {conversionFactor?.name ?? '—' } · {factor.is_activity_based ? t('Activity based') : t('Spend based')}
                    </div>
                    <div
                        className="rounded-2 px-3 py-2 mt-2"
                        style={{ background: '#e8f2ec', border: '1px solid #c9dfd2' }}
                    >
                        <div style={{ fontSize: '0.72rem', fontWeight: 700, letterSpacing: '0.05em', color: '#2a7a4f' }}>
                            {t('CO₂e contribution')}
                        </div>
                        <div style={{ fontSize: '1.35rem', fontWeight: 800, lineHeight: 1.1, color: '#1e4d3a' }}>
                            {fmtEmission(emissionCo2e)}
                        </div>
                    </div>
                </div>
                <div className="d-inline-flex gap-2">
                    {isManual && (
                       <button
                         type="button"
                         className="btn btn-sm btn-outline-secondary"
                         onClick={() => onShowManualReport(factor.id)}
                       >
                           {t('Manage reports')} ({manualReportCount})
                       </button>
                    )}
                    <button
                       type="button"
                       className="btn btn-sm btn-outline-secondary"
                       onClick={() => onEditFactor(factor.id)}
                    >
                        {t('Edit factor')}
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-danger"
                        onClick={() => onRemoveFactor(factor.id)}
                    >
                        {t('Remove factor')}
                    </button>
                </div>
            </div>

            <div className="row g-2" style={{ fontSize: '0.82rem' }}>
                <div className="col-12 col-md-4">
                    <div className="text-muted">{t('Description')}</div>
                    <div style={{ whiteSpace: 'pre-wrap' }}>{factor.description ? factor.description : '—'}</div>
                </div>
                <div className="col-12 col-md-4">
                    <div className="text-muted">{t('Data source')}</div>
                    <div>{isManual ? t('Manual reporting') : (processMetric?.name ?? t('Process performance metric'))}</div>
                </div>
                <div className="col-12 col-md-4">
                    <div className="text-muted">{t('Postprocessing')}</div>
                    <div>
                        {t(normalizePostprocessingFunction(factor.postprocessing_function).replace(/^./, c => c.toUpperCase()))}
                    </div>
                </div>
            </div>
        </div>
    );
}

function AddFactorForm({ scope, factors, conversionFactors, categories, processMetrics, onAdd, onCancel }) {
    const scopeCategories = categories.filter(c => c.scope === scope);
    const [categoryId, setCategoryId] = useState(scopeCategories[0]?.id ?? '');
    const [factorKey, setFactorKey] = useState('');
    const [factorName, setFactorName] = useState('');
    const [factorDescription, setFactorDescription] = useState('');
    const [dataSourceKey, setDataSourceKey] = useState('manual');
    const [postprocessingFunction, setPostprocessingFunction] = useState('latest');
    const [saving, setSaving] = useState(false);
    const [err, setErr] = useState(null);

    const factorOptions = categoryId
        ? getSelectableFactorOptionsByCategory(factors, conversionFactors, Number(categoryId))
        : [];

    const selectedFactorOption = factorOptions.find(option => option.key === factorKey) ?? null;
    const hasAnySelectableOptions = scopeCategories.some(
        category => getSelectableFactorOptionsByCategory(factors, conversionFactors, category.id).length > 0
    );

    const handleCategoryChange = (e) => {
        setCategoryId(Number(e.target.value));
        setFactorKey('');
        setFactorName('');
        setFactorDescription('');
    };

    const handleFactorChange = (e) => {
        const nextFactorKey = e.target.value;
        const nextFactorOption = factorOptions.find(option => option.key === nextFactorKey) ?? null;

        setFactorKey(nextFactorKey);
        setFactorName(nextFactorOption?.name ?? '');
        setFactorDescription('');
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!selectedFactorOption) {
            return;
        }

        setSaving(true);
        setErr(null);
        try {
            await onAdd({
                ghg_conversion_factor_id: selectedFactorOption.conversionFactorId,
                is_activity_based: selectedFactorOption.isActivityBased,
                process_performance_metrics_id: dataSourceKey === 'manual' ? null : Number(dataSourceKey),
                factor_name: factorName.trim() || selectedFactorOption.name,
                description: factorDescription.trim() || null,
                postprocessing_function: postprocessingFunction,
            });
            onCancel();
        } catch (error) {
            setErr(error?.response?.data?.message ?? t('Could not save the factor'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="p-3 border rounded-3 mt-2" style={{ background: '#f8faf9' }}>
            {err && <div className="alert alert-danger py-2 mb-2" style={{ fontSize: '0.83rem' }}>{err}</div>}
            {!hasAnySelectableOptions ? (
                <>
                    <p className="mb-2 text-muted" style={{ fontSize: '0.85rem' }}>
                        {t('No conversion factors are configured for this scope. Configure GHG categories and conversion factors in the settings.')}
                    </p>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onCancel}>{t('Cancel')}</button>
                </>
            ) : (
                <form onSubmit={handleSubmit}>
                    <div className="row g-2 align-items-start">
                        <div className="col-12 col-md-3">
                            <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Category')}</label>
                            <select className="form-select form-select-sm" value={categoryId} onChange={handleCategoryChange} required>
                                <option value="">{t('Select category…')}</option>
                                {scopeCategories.map(category => (
                                    <option key={category.id} value={category.id}>{category.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-12 col-md-3">
                            <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Conversion factor')}</label>
                            {factorOptions.length === 0 && categoryId ? (
                                <p className="mb-0 text-muted" style={{ fontSize: '0.8rem', paddingTop: '0.3rem' }}>
                                    {t('No conversion factors configured for this category.')}
                                </p>
                            ) : (
                                <select
                                    className="form-select form-select-sm"
                                    value={factorKey}
                                    onChange={handleFactorChange}
                                    required
                                    disabled={!categoryId}
                                >
                                    <option value="">{t('Select conversion factor…')}</option>
                                    {factorOptions.map(option => (
                                        <option key={option.key} value={option.key}>
                                            {option.name} - {option.isActivityBased ? t('Activity based') : t('Spend based')}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>
                        <div className="col-12 col-md-3">
                            <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Factor name')}</label>
                            <input
                                type="text"
                                className="form-control form-control-sm"
                                value={factorName}
                                onChange={e => setFactorName(e.target.value)}
                                placeholder={t('Enter factor name')}
                                required
                                disabled={!selectedFactorOption}
                            />
                        </div>
                        <div className="col-12 col-md-3">
                            <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Data source')}</label>
                            <select
                                className="form-select form-select-sm"
                                value={dataSourceKey}
                                onChange={e => setDataSourceKey(e.target.value)}
                                required
                            >
                                <option value="manual">{t('Manual reporting')}</option>
                                {processMetrics.map(metric => (
                                    <option key={metric.id} value={metric.id}>{metric.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-12 col-md-6">
                            <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Description')}</label>
                            <textarea
                                className="form-control form-control-sm"
                                value={factorDescription}
                                onChange={e => setFactorDescription(e.target.value)}
                                placeholder={t('Optional description')}
                                rows={2}
                            />
                        </div>
                        <div className="col-12 col-md-3">
                            <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Postprocessing function')}</label>
                            <select
                                className="form-select form-select-sm"
                                value={postprocessingFunction}
                                onChange={e => setPostprocessingFunction(e.target.value)}
                            >
                                <option value="latest">{t('Latest')}</option>
                                <option value="sum">{t('Sum')}</option>
                                <option value="average">{t('Average')}</option>
                            </select>
                        </div>
                        <div className="col-12 d-flex flex-wrap justify-content-end gap-2 mt-2">
                            <button
                                type="submit"
                                className="btn btn-sm"
                                style={{ background: '#1e4d3a', color: '#fff' }}
                                disabled={saving || !selectedFactorOption}
                            >
                                {saving ? '…' : t('Add')}
                            </button>
                            <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onCancel}>
                                {t('Cancel')}
                            </button>
                        </div>
                    </div>
                </form>
            )}
        </div>
    );
}

function EditFactorForm({ factor, conversionFactors, processMetrics, onSave, onCancel }) {
    const [factorName, setFactorName] = useState(factor.name ?? '');
    const [factorDescription, setFactorDescription] = useState(factor.description ?? '');
    const [dataSourceKey, setDataSourceKey] = useState(
        factor.process_performance_metrics_id ? String(factor.process_performance_metrics_id) : 'manual'
    );
    const [postprocessingFunction, setPostprocessingFunction] = useState(
        normalizePostprocessingFunction(factor.postprocessing_function)
    );
    const [saving, setSaving] = useState(false);
    const [err, setErr] = useState(null);

    const conversionFactor = getConversionFactor(conversionFactors, factor.ghg_conversion_factor_id);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSaving(true);
        setErr(null);
        try {
            await onSave({
                id: factor.id,
                name: factorName.trim(),
                description: factorDescription.trim() || null,
                ghg_conversion_factor_id: factor.ghg_conversion_factor_id,
                is_activity_based: factor.is_activity_based,
                process_performance_metrics_id: dataSourceKey === 'manual' ? null : Number(dataSourceKey),
                postprocessing_function: postprocessingFunction,
            });
            onCancel();
        } catch (error) {
            setErr(error?.response?.data?.message ?? t('Could not update the factor'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="p-3 border rounded-3 mt-3" style={{ background: '#f8faf9' }}>
            {err && <div className="alert alert-danger py-2 mb-2" style={{ fontSize: '0.83rem' }}>{err}</div>}
            <form onSubmit={handleSubmit}>
                <div className="row g-2 align-items-start">
                    <div className="col-12 col-md-3">
                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Conversion factor')}</label>
                        <input
                            type="text"
                            className="form-control form-control-sm"
                            value={conversionFactor?.name ?? ''}
                            disabled
                        />
                    </div>
                    <div className="col-12 col-md-3">
                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Type')}</label>
                        <input
                            type="text"
                            className="form-control form-control-sm"
                            value={factor.is_activity_based ? t('Activity based') : t('Spend based')}
                            disabled
                        />
                    </div>
                    <div className="col-12 col-md-3">
                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Factor name')}</label>
                        <input
                            type="text"
                            className="form-control form-control-sm"
                            value={factorName}
                            onChange={e => setFactorName(e.target.value)}
                            required
                        />
                    </div>
                    <div className="col-12 col-md-3">
                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Data source')}</label>
                        <select
                            className="form-select form-select-sm"
                            value={dataSourceKey}
                            onChange={e => setDataSourceKey(e.target.value)}
                            required
                        >
                            <option value="manual">{t('Manual reporting')}</option>
                            {processMetrics.map(metric => (
                                <option key={metric.id} value={metric.id}>{metric.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="col-12 col-md-6">
                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Description')}</label>
                        <textarea
                            className="form-control form-control-sm"
                            value={factorDescription}
                            onChange={e => setFactorDescription(e.target.value)}
                            rows={2}
                        />
                    </div>
                    <div className="col-12 col-md-3">
                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Postprocessing function')}</label>
                        <select
                            className="form-select form-select-sm"
                            value={postprocessingFunction}
                            onChange={e => setPostprocessingFunction(e.target.value)}
                        >
                            <option value="latest">{t('Latest')}</option>
                            <option value="sum">{t('Sum')}</option>
                            <option value="average">{t('Average')}</option>
                        </select>
                    </div>
                    <div className="col-12 d-flex flex-wrap justify-content-end gap-2 mt-2">
                        <button
                            type="submit"
                            className="btn btn-sm"
                            style={{ background: '#1e4d3a', color: '#fff' }}
                            disabled={saving || factorName.trim() === ''}
                        >
                            {saving ? '…' : t('Save factor')}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onCancel}>
                            {t('Cancel')}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
}

function ManualReportForm({ factor, reports, conversionFactors, onAdd, onUpdate, onRemove, onCancel }) {
    const [value, setValue] = useState('');
    const [valuedate, setValuedate] = useState(new Date().toISOString().slice(0, 10));
    const [comment, setComment] = useState('');
    const [adding, setAdding] = useState(false);
    const [editingReportId, setEditingReportId] = useState(null);
    const [editValue, setEditValue] = useState('');
    const [editValuedate, setEditValuedate] = useState('');
    const [editComment, setEditComment] = useState('');
    const [savingEditId, setSavingEditId] = useState(null);
    const [deletingId, setDeletingId] = useState(null);
    const [err, setErr] = useState(null);

    const conversionFactor = getConversionFactor(conversionFactors, factor.ghg_conversion_factor_id);
    const unit = factor.is_activity_based ? conversionFactor?.activity_sourceunit : conversionFactor?.spend_sourceunit;
    const manualReports = useMemo(
        () => reports
            .filter(report => report.ghg_factor_id === factor.id)
            .sort((a, b) => {
                const dateCompare = String(b.valuedate ?? '').localeCompare(String(a.valuedate ?? ''));
                if (dateCompare !== 0) {
                    return dateCompare;
                }
                return (b.id ?? 0) - (a.id ?? 0);
            }),
        [reports, factor.id]
    );

    const handleAddSubmit = async (e) => {
        e.preventDefault();
        setAdding(true);
        setErr(null);

        try {
            await onAdd({
                ghg_factor_id: factor.id,
                value: parseFloat(value.replace(',', '.')),
                valuedate,
                comment,
            });
            setValue('');
            setComment('');
        } catch (error) {
            setErr(error?.response?.data?.message ?? t('Could not save the report'));
        } finally {
            setAdding(false);
        }
    };

    const startEditReport = (report) => {
        setEditingReportId(report.id);
        setEditValue(String(report.value ?? ''));
        setEditValuedate(String(report.valuedate ?? ''));
        setEditComment(String(report.comment ?? ''));
        setErr(null);
    };

    const handleEditSubmit = async (e) => {
        e.preventDefault();
        if (!editingReportId) {
            return;
        }

        setSavingEditId(editingReportId);
        setErr(null);
        try {
            await onUpdate({
                id: editingReportId,
                value: parseFloat(editValue.replace(',', '.')),
                valuedate: editValuedate,
                comment: editComment,
            });
            setEditingReportId(null);
            setEditValue('');
            setEditValuedate('');
            setEditComment('');
        } catch (error) {
            setErr(error?.response?.data?.message ?? t('Could not update the report'));
        } finally {
            setSavingEditId(null);
        }
    };

    const handleRemoveReport = async (id) => {
        setDeletingId(id);
        setErr(null);
        try {
            await onRemove(id);
            if (editingReportId === id) {
                setEditingReportId(null);
            }
        } catch (error) {
            setErr(error?.response?.data?.message ?? t('Could not remove the report'));
        } finally {
            setDeletingId(null);
        }
    };

    return (
        <div className="p-3 border rounded-3 mt-3" style={{ background: '#f8faf9' }}>
            <div className="d-flex justify-content-between align-items-center mb-2">
                <h6 className="mb-0" style={{ fontWeight: 700 }}>{t('Manual reports')}</h6>
                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={onCancel}>
                    {t('Close')}
                </button>
            </div>
            {err && <div className="alert alert-danger py-2 mb-2" style={{ fontSize: '0.83rem' }}>{err}</div>}

            {manualReports.length === 0 ? (
                <div className="text-muted mb-3" style={{ fontSize: '0.83rem' }}>
                    {t('No manual reports yet. Add the first report below.')}
                </div>
            ) : (
                <div className="mb-3 d-flex flex-column gap-2">
                    {manualReports.map(report => (
                        <div key={report.id} className="border rounded-3 p-2" style={{ background: '#fff' }}>
                            <div className="row g-2 align-items-end" style={{ fontSize: '0.83rem' }}>
                                <div className="col-12 col-md-3">
                                    <div className="text-muted">{t('Amount')}</div>
                                    <div style={{ fontWeight: 600 }}>
                                        {Number(report.value ?? 0).toLocaleString('sv-SE', { maximumFractionDigits: 2 })}
                                        {unit ? ` ${unit}` : ''}
                                    </div>
                                </div>
                                <div className="col-12 col-md-2">
                                    <div className="text-muted">{t('Date')}</div>
                                    <div>{report.valuedate ?? '—'}</div>
                                </div>
                                <div className="col-12 col-md-7">
                                    <div className="text-muted">{t('Comment')}</div>
                                    <div style={{ whiteSpace: 'pre-wrap' }}>{report.comment || '—'}</div>
                                </div>
                                <div className="col-12 d-flex justify-content-end gap-2 mt-2">
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-outline-secondary"
                                        onClick={() => startEditReport(report)}
                                    >
                                        {t('Edit')}
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-outline-danger"
                                        onClick={() => handleRemoveReport(report.id)}
                                        disabled={deletingId === report.id}
                                    >
                                        {deletingId === report.id ? '…' : t('Remove')}
                                    </button>
                                </div>
                            </div>

                            {editingReportId === report.id && (
                                <form className="row g-2 align-items-end mt-2 pt-2 border-top" onSubmit={handleEditSubmit}>
                                    <div className="col-12 col-md-4">
                                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>
                                            {t('Amount')}{unit ? ` (${unit})` : ''}
                                        </label>
                                        <input
                                            type="number"
                                            className="form-control form-control-sm"
                                            value={editValue}
                                            onChange={e => setEditValue(e.target.value)}
                                            min="0"
                                            step="any"
                                            required
                                        />
                                    </div>
                                    <div className="col-12 col-md-4">
                                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Date')}</label>
                                        <input
                                            type="date"
                                            className="form-control form-control-sm"
                                            value={editValuedate}
                                            onChange={e => setEditValuedate(e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="col-12 col-md-4">
                                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Comment')}</label>
                                        <input
                                            type="text"
                                            className="form-control form-control-sm"
                                            value={editComment}
                                            onChange={e => setEditComment(e.target.value)}
                                        />
                                    </div>
                                    <div className="col-12 d-flex justify-content-end gap-2">
                                        <button
                                            type="submit"
                                            className="btn btn-sm"
                                            style={{ background: '#1e4d3a', color: '#fff' }}
                                            disabled={savingEditId === report.id}
                                        >
                                            {savingEditId === report.id ? '…' : t('Save report')}
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-secondary"
                                            onClick={() => setEditingReportId(null)}
                                        >
                                            {t('Cancel')}
                                        </button>
                                    </div>
                                </form>
                            )}
                        </div>
                    ))}
                </div>
            )}

            <form onSubmit={handleAddSubmit}>
                <div className="row g-2 align-items-end">
                    <div className="col-12 col-md-4">
                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>
                            {t('Amount')}{unit ? ` (${unit})` : ''}
                        </label>
                        <input
                            type="number"
                            className="form-control form-control-sm"
                            placeholder="0"
                            value={value}
                            onChange={e => setValue(e.target.value)}
                            min="0"
                            step="any"
                            required
                        />
                    </div>
                    <div className="col-12 col-md-4">
                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Date')}</label>
                        <input
                            type="date"
                            className="form-control form-control-sm"
                            value={valuedate}
                            onChange={e => setValuedate(e.target.value)}
                            required
                        />
                    </div>
                    <div className="col-12 col-md-4">
                        <label className="form-label" style={{ fontSize: '0.78rem', fontWeight: 600 }}>{t('Comment')}</label>
                        <input
                            type="text"
                            className="form-control form-control-sm"
                            value={comment}
                            onChange={e => setComment(e.target.value)}
                        />
                    </div>
                    <div className="col-12 d-flex justify-content-end gap-2 mt-2">
                        <button
                            type="submit"
                            className="btn btn-sm"
                            style={{ background: '#1e4d3a', color: '#fff' }}
                            disabled={adding}
                        >
                            {adding ? '…' : t('Add report')}
                        </button>
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => {
                            setValue('');
                            setValuedate(new Date().toISOString().slice(0, 10));
                            setComment('');
                        }}>
                            {t('Reset')}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    );
}

export function ScopeSection({
    scope,
    reports,
    factors,
    conversionFactors,
    categories,
    processMetrics,
    onAddFactor,
    onUpdateFactor,
    onAddManualReport,
    onUpdateManualReport,
    onRemoveFactor,
    onRemoveReport,
}) {
    const [showAddFactorForm, setShowAddFactorForm] = useState(false);
    const [editingFactorId, setEditingFactorId] = useState(null);
    const [manualReportFactorId, setManualReportFactorId] = useState(null);
    const meta = SCOPE_META[scope];

    const scopeFactors = useMemo(
        () => getFactorsByScope(factors, conversionFactors, categories, scope),
        [factors, conversionFactors, categories, scope]
    );

    const manualReportFactor = scopeFactors.find(factor => factor.id === manualReportFactorId) ?? null;
    const editingFactor = scopeFactors.find(factor => factor.id === editingFactorId) ?? null;

    const handleAddFactor = async (data) => {
        await onAddFactor(data);
        setShowAddFactorForm(false);
    };

    const handleAddManualReport = async (data) => {
        await onAddManualReport(data);
    };

    const handleUpdateManualReport = async (data) => {
        await onUpdateManualReport(data);
    };

    const handleUpdateFactor = async (data) => {
        await onUpdateFactor(data);
        setEditingFactorId(null);
    };

    const handleRemoveFactor = async (factorId) => {
        await onRemoveFactor(factorId);
        if (manualReportFactorId === factorId) {
            setManualReportFactorId(null);
        }
        if (editingFactorId === factorId) {
            setEditingFactorId(null);
        }
    };

    return (
        <div className="card border-0 shadow-sm rounded-3 mb-4 p-4">
            <h5 className="mb-1" style={{ fontWeight: 700 }}>
                <span className="material-symbols-rounded me-2 align-middle" style={{ fontSize: '1.1rem', color: '#1e4d3a', verticalAlign: 'middle' }}>info</span>
                {t(meta.key)}
            </h5>
            <p className="mb-3" style={{ fontSize: '0.85rem', color: '#2a7a4f' }}>{t(meta.desc)}</p>

            {scopeFactors.length === 0 ? (
                <div
                    className="d-flex align-items-center justify-content-center rounded-3 mb-3"
                    style={{ background: '#f8faf9', border: '1px solid #e2ebe6', minHeight: 64, color: '#5a7a6a', fontSize: '0.87rem' }}
                >
                    {t('No factors configured yet. Add the first factor below.')}
                </div>
            ) : (
                <div className="mb-3">
                    {scopeFactors.map(factor => (
                        <FactorConfigRow
                            key={factor.id}
                            factor={factor}
                            reports={reports}
                            conversionFactors={conversionFactors}
                            categories={categories}
                            processMetrics={processMetrics}
                            onEditFactor={setEditingFactorId}
                            onShowManualReport={setManualReportFactorId}
                            onRemoveFactor={handleRemoveFactor}
                        />
                    ))}
                </div>
            )}

            {manualReportFactor && (
                <ManualReportForm
                    factor={manualReportFactor}
                    reports={reports}
                    conversionFactors={conversionFactors}
                    onAdd={handleAddManualReport}
                    onUpdate={handleUpdateManualReport}
                    onRemove={onRemoveReport}
                    onCancel={() => setManualReportFactorId(null)}
                />
            )}

            {editingFactor && (
                <EditFactorForm
                    factor={editingFactor}
                    conversionFactors={conversionFactors}
                    processMetrics={processMetrics}
                    onSave={handleUpdateFactor}
                    onCancel={() => setEditingFactorId(null)}
                />
            )}

            {showAddFactorForm ? (
                <AddFactorForm
                    scope={scope}
                    factors={factors}
                    conversionFactors={conversionFactors}
                    categories={categories}
                    processMetrics={processMetrics}
                    onAdd={handleAddFactor}
                    onCancel={() => setShowAddFactorForm(false)}
                />
            ) : (
                <button
                    type="button"
                    className="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 mt-2"
                    style={{ width: 'fit-content', fontSize: '0.85rem' }}
                    onClick={() => setShowAddFactorForm(true)}
                >
                    <span className="material-symbols-rounded" style={{ fontSize: '1rem' }}>add</span>
                    {t('Add factor')}
                </button>
            )}
        </div>
    );
}
