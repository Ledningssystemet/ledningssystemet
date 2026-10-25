import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createRequire } from 'node:module';
import { runInNewContext } from 'node:vm';
import { build } from 'esbuild';
import postcss from 'postcss';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

async function loadComponent(file, initialStates = []) {
    const result = await build({
        entryPoints: [`resources\\js\\components\\ghgcalculator\\${file}.jsx`],
        bundle: true, platform: 'node', format: 'cjs', packages: 'external',
        write: false, loader: { '.css': 'empty' },
    });
    const module = { exports: {} };
    const require = createRequire(import.meta.url);
    let stateIndex = 0;
    runInNewContext(result.outputFiles[0].text, {
        module, exports: module.exports,
        require: name => name === 'react' ? {
            ...React,
            useState: initial => React.useState(
                stateIndex < initialStates.length ? initialStates[stateIndex++] : initial
            ),
        } : require(name),
    });
    return module.exports;
}

const categories = [
    { id: 1, scope: 1, name: 'Vehicles' },
    { id: 2, scope: 2, name: 'Electricity' },
];
const conversionFactors = [
    { id: 10, ghg_category_id: 1, name: 'Diesel', activity_factor: 2.5, activity_sourceunit: 'l', activity_datasource_name: 'Source', activity_datasource_url: 'https://example.com/source' },
    { id: 20, ghg_category_id: 2, name: 'Purchased energy', activity_factor: 0.1 },
];
const factors = [
    { id: 100, ghg_conversion_factor_id: 10, name: 'Company vehicles', is_activity_based: true, emission_co2e_kg: 25 },
    { id: 200, ghg_conversion_factor_id: 20, name: 'Office electricity', process_performance_metrics_id: 9, emission_co2e_kg: 10 },
];
const scopeProps = {
    scope: 1, categories, conversionFactors, factors, processMetrics: [{ id: 9, name: 'Electricity metric' }],
    reports: [{ id: 1, ghg_factor_id: 100, value: 10, valuedate: '2026-10-24', comment: 'Fuel report' }],
};

test('summary cards keep total and scope values in the harmonized cards', async () => {
    const { SummaryCards } = await loadComponent('SummaryCards');
    const html = renderToStaticMarkup(React.createElement(SummaryCards, scopeProps));
    assert.equal((html.match(/class="ghg-card ghg-stat/g) ?? []).length, 4);
    assert.match(html, /ghg-stat-total/);
    for (const value of [35, 25, 10, 0]) {
        assert.match(html, new RegExp(`class="ghg-stat-value">${value} kg</div>`));
    }
    assert.doesNotMatch(html, /style=/);
});

test('scope buttons expose the active state and keep their scope change handlers', async () => {
    const { ScopeTabs } = await loadComponent('ScopeTabs');
    for (const activeScope of [1, 2, 3]) {
        let selectedScope;
        const element = ScopeTabs({ activeScope, onScopeChange: scope => { selectedScope = scope; } });
        const buttons = element.props.children;
        assert.equal(buttons.length, 3);
        buttons.forEach((button, index) => {
            assert.equal(button.props['aria-pressed'], index + 1 === activeScope);
            button.props.onClick();
            assert.equal(selectedScope, index + 1);
        });
        const html = renderToStaticMarkup(element);
        assert.equal((html.match(/aria-pressed="true"/g) ?? []).length, 1);
        assert.doesNotMatch(html, /style=/);
    }
});

test('scope content retains factor filtering, contributions and manual report actions', async () => {
    const { ScopeSection } = await loadComponent('ScopeSection');
    const manual = renderToStaticMarkup(React.createElement(ScopeSection, scopeProps));
    assert.match(manual, /class="ghg-card"/);
    assert.match(manual, /Company vehicles/);
    assert.match(manual, /class="ghg-contribution-value">25 kg/);
    assert.match(manual, /Manage reports.*\(1\)/);
    assert.match(manual, /Edit factor/);
    assert.match(manual, /Remove factor/);
    assert.doesNotMatch(manual, /Office electricity/);

    const metric = renderToStaticMarkup(React.createElement(ScopeSection, { ...scopeProps, scope: 2 }));
    assert.match(metric, /Office electricity/);
    assert.match(metric, /Electricity metric/);
    assert.doesNotMatch(metric, /Manage reports|Company vehicles/);

    const empty = renderToStaticMarkup(React.createElement(ScopeSection, { ...scopeProps, scope: 3 }));
    assert.match(empty, /class="ghg-empty-state mb-3"/);
    assert.match(empty, /No factors configured yet/);
    assert.match(empty, /btn-primary[^>]*>.*<span[^>]*>add/s);
});

test('factor and reporting forms use the same panels and primary buttons', async () => {
    const { ScopeSection } = await loadComponent('ScopeSection', [true, 100, 100]);
    const html = renderToStaticMarkup(React.createElement(ScopeSection, scopeProps));
    assert.equal((html.match(/class="ghg-form-panel /g) ?? []).length, 3);
    assert.match(html, /Manual reports/);
    assert.match(html, /Fuel report/);
    assert.match(html, /Save factor/);
    assert.match(html, /Add report/);
    assert.match(html, /class="btn btn-sm btn-primary"/);
    assert.match(html, /type="number"[^>]*min="0"[^>]*step="any"[^>]*required/);
    assert.match(html, /class="form-label">Category/);
    assert.doesNotMatch(html, /style="[^"]*(?:background|color|font-size)/);
});

test('reference list retains sources, units, expansion and empty states', async () => {
    const { EmissionFactorList } = await loadComponent('EmissionFactorList');
    const html = renderToStaticMarkup(React.createElement(EmissionFactorList, scopeProps));
    assert.match(html, /class="ghg-card ghg-reference"/);
    assert.match(html, /aria-expanded="true"/);
    assert.match(html, /href="https:\/\/example.com\/source" target="_blank" rel="noreferrer noopener"/);
    assert.match(html, /kg\/l/);
    assert.match(html, /Activity based/);
    assert.doesNotMatch(html, /style=/);
    const empty = renderToStaticMarkup(React.createElement(EmissionFactorList, {
        categories: [], conversionFactors: [],
    }));
    assert.match(empty, /No emission factors are available for this scope/);

    const { EmissionFactorList: ClosedList } = await loadComponent('EmissionFactorList', [false]);
    const closed = renderToStaticMarkup(React.createElement(ClosedList, scopeProps));
    assert.match(closed, /aria-expanded="false"/);
    assert.doesNotMatch(closed, /ghg-reference-body|Diesel/);
});

test('calculator has a scoped layout and labelled date filters while loading', async () => {
    const { GhgCalculator } = await loadComponent('index');
    const html = renderToStaticMarkup(React.createElement(GhgCalculator));
    assert.match(html, /class="ghg-module"/);
    assert.match(html, /class="ghg-header"/);
    assert.match(html, /class="ghg-body"/);
    assert.match(html, /for="ghg-from"/);
    assert.match(html, /id="ghg-from" type="date"[^>]*max=/);
    assert.match(html, /for="ghg-to"/);
    assert.match(html, /id="ghg-to" type="date"[^>]*min=/);
    assert.match(html, /Loading data/);
});

test('calculator bundles its responsive styles without leaking selectors into other modules', async () => {
    const result = await build({
        entryPoints: ['resources\\js\\components\\ghgcalculator\\index.jsx'],
        bundle: true, packages: 'external', outdir: 'ghg-build-check', write: false,
    });
    const stylesheet = result.outputFiles.find(file => file.path.endsWith('.css'));
    assert.ok(stylesheet, 'The calculator entry must include its stylesheet');
    const css = postcss.parse(stylesheet.text);
    let ruleCount = 0;
    css.walkRules(rule => {
        ruleCount++;
        for (const selector of rule.selector.split(',')) {
            assert.ok(selector.trim().startsWith('.ghg-module'), `Unscoped selector: ${selector}`);
        }
    });
    assert.ok(ruleCount > 0);
    const breakpoints = [];
    css.walkAtRules('media', rule => breakpoints.push(rule.params));
    assert.deepEqual(breakpoints, ['(max-width: 767px)', '(max-width: 399px)']);
});
