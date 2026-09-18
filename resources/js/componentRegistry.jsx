import React from 'react';
import ReactDOM from 'react-dom/client';
import { GhgCalculator } from '@components/ghgcalculator/index.jsx';
import { FormBuilder, FormViewer } from '@components/formbuilder/index.jsx';
import { AuthApp } from '@components/auth/AuthApp.jsx';

const registry = {
    'ghg-calculator': GhgCalculator,
    'form-builder': FormBuilder,
    'form-viewer': FormViewer,
    'auth-app': AuthApp,
};

/**
 * Allows consumers (e.g. the proprietary customer application) to register
 * additional custom-element tags to React components. Must be called before
 * the 'DOMContentLoaded' event fires, i.e. during module import.
 */
export function registerComponent(tag, Component) {
    registry[tag] = Component;
}

window.mountFormViewer = function (container, props) {
    const root = ReactDOM.createRoot(container);
    root.render(
        <React.StrictMode>
            <FormViewer {...props} />
        </React.StrictMode>
    );
    return () => root.unmount();
};

document.addEventListener('DOMContentLoaded', () => {
    Object.entries(registry).forEach(([tag, Component]) => {
        document.querySelectorAll(tag).forEach(el => {
            const props = el.dataset.props ? JSON.parse(el.dataset.props) : {};
            if (tag === 'crud-module' && el.dataset.configDataset && !props.configDataset) {
                props.configDataset = el.dataset.configDataset;
            }
            ReactDOM.createRoot(el).render(
                <React.StrictMode>
                    <Component {...props} />
                </React.StrictMode>
            );
        });
    });
});
