import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import { t } from './t.js';

/**
 * Fetches all GHG data from the API and provides CRUD for GhgFactorReport.
 * Endpoints: /api/v1/items/{GhgCategory|GhgConversionFactor|GhgFactor|GhgFactorReport}
 */
export function useGhgData({ from, to } = {}) {
    const [categories, setCategories] = useState([]);
    const [conversionFactors, setConversionFactors] = useState([]);
    const [factors, setFactors] = useState([]);
    const [reports, setReports] = useState([]);
    const [processMetrics, setProcessMetrics] = useState([]);
    const [metricReports, setMetricReports] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const fetchCollection = useCallback(async (url, params = {}) => {
        const firstResponse = await axios.get(url, { params });
        const firstPayload = firstResponse.data;

        if (Array.isArray(firstPayload)) {
            return firstPayload;
        }

        const firstPageItems = Array.isArray(firstPayload?.data) ? firstPayload.data : [];
        const lastPage = Number(firstPayload?.last_page ?? 1);

        if (lastPage <= 1) {
            return firstPageItems;
        }

        const pageResponses = await Promise.all(
            Array.from({ length: lastPage - 1 }, (_, index) =>
                axios.get(url, { params: { ...params, page: index + 2 } })
            )
        );

        return firstPageItems.concat(
            pageResponses.flatMap(response => Array.isArray(response.data?.data) ? response.data.data : [])
        );
    }, []);

    const refreshFactors = useCallback(async () => {
        const ghgFactorParams = {};
        if (from) {
            ghgFactorParams.from = from;
        }
        if (to) {
            ghgFactorParams.to = to;
        }

        const factorData = await fetchCollection('/api/v1/items/GhgFactor', ghgFactorParams);
        setFactors(factorData);
        return factorData;
    }, [fetchCollection, from, to]);

    const fetchAll = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const [catData, cfData, factorData, reportData, processMetricData, metricReportData] = await Promise.all([
                fetchCollection('/api/v1/items/GhgCategory'),
                fetchCollection('/api/v1/items/GhgConversionFactor'),
                refreshFactors(),
                fetchCollection('/api/v1/items/GhgFactorReport'),
                fetchCollection('/api/v1/items/ProcessPerformanceMetric'),
                fetchCollection('/api/v1/items/ProcessPerformanceMetricReport'),
            ]);
            setCategories(catData);
            setConversionFactors(cfData);
            setFactors(factorData);
            setReports(reportData);
            setProcessMetrics(processMetricData);
            setMetricReports(metricReportData);
        } catch (e) {
            setError(e?.response?.data?.message ?? e.message ?? t('Could not fetch data'));
        } finally {
            setLoading(false);
        }
    }, [fetchCollection, refreshFactors]);

    useEffect(() => {
        fetchAll();
    }, [fetchAll]);

    const addFactor = useCallback(async ({
        ghg_conversion_factor_id,
        is_activity_based,
        factor_name,
        process_performance_metrics_id,
        description,
        postprocessing_function,
    }) => {
        const res = await axios.post('/api/v1/items/GhgFactor', {
            name: factor_name,
            ghg_conversion_factor_id,
            description: description ?? null,
            is_activity_based: Boolean(is_activity_based),
            process_performance_metrics_id: process_performance_metrics_id || null,
            postprocessing_function: postprocessing_function ?? null,
        });
        setFactors(prev => [...prev, res.data]);
        return res.data;
    }, []);

    const addManualReport = useCallback(async ({ ghg_factor_id, value, valuedate, comment }) => {
        const res = await axios.post('/api/v1/items/GhgFactorReport', {
            ghg_factor_id,
            value,
            valuedate,
            comment: comment || null,
        });
        setReports(prev => [res.data, ...prev]);
        await refreshFactors();
        return res.data;
    }, [refreshFactors]);

    const updateManualReport = useCallback(async ({ id, value, valuedate, comment }) => {
        const res = await axios.patch(`/api/v1/items/GhgFactorReport/${id}`, {
            value,
            valuedate,
            comment: comment || null,
        });
        setReports(prev => prev.map(report => report.id === id ? res.data : report));
        await refreshFactors();
        return res.data;
    }, [refreshFactors]);

    const updateFactor = useCallback(async ({
        id,
        name,
        description,
        ghg_conversion_factor_id,
        is_activity_based,
        process_performance_metrics_id,
        postprocessing_function,
    }) => {
        const res = await axios.patch(`/api/v1/items/GhgFactor/${id}`, {
            name,
            description: description ?? null,
            ghg_conversion_factor_id,
            is_activity_based: Boolean(is_activity_based),
            process_performance_metrics_id: process_performance_metrics_id || null,
            postprocessing_function: postprocessing_function ?? null,
        });
        setFactors(prev => prev.map(factor => factor.id === id ? res.data : factor));
        return res.data;
    }, []);

    const removeReport = useCallback(async (id) => {
        await axios.delete(`/api/v1/items/GhgFactorReport/${id}`);
        setReports(prev => prev.filter(r => r.id !== id));
        await refreshFactors();
    }, [refreshFactors]);

    const removeFactor = useCallback(async (id) => {
        await axios.delete(`/api/v1/items/GhgFactor/${id}`);
        setFactors(prev => prev.filter(factor => factor.id !== id));
        setReports(prev => prev.filter(report => report.ghg_factor_id !== id));
    }, []);

    return {
        categories,
        conversionFactors,
        factors,
        reports,
        processMetrics,
        metricReports,
        loading,
        error,
        addFactor,
        updateFactor,
        addManualReport,
        updateManualReport,
        removeFactor,
        removeReport,
    };
}
