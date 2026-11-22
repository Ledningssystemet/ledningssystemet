import React, { lazy, Suspense } from 'react';
import ReactDOM from 'react-dom/client';

const GhgCalculator = lazy(() =>
    import('../../pages/ghgcalculator/index.jsx').then(module => ({ default: module.GhgCalculator }))
);
const FormBuilder = lazy(() =>
    import('../formbuilder/index.jsx').then(module => ({ default: module.FormBuilder }))
);
const FormViewer = lazy(() =>
    import('../formbuilder/index.jsx').then(module => ({ default: module.FormViewer }))
);
const AuthApp = lazy(() =>
    import('../../pages/auth/AuthApp.jsx').then(module => ({ default: module.AuthApp }))
);
const Projects = lazy(() =>
    import('../../pages/projects/index.tsx').then(module => ({ default: module.Projects }))
);
const ProjectMentions = lazy(() =>
    import('../../pages/projects/ProjectMentions.tsx').then(module => ({ default: module.ProjectMentions }))
);
const TableModule = lazy(() =>
    import('../table/TableModule.tsx').then(module => ({ default: module.TableModule }))
);
const SystemSettingsForm = lazy(() =>
    import('../../pages/settings/SystemSettingsForm.tsx').then(module => ({ default: module.SystemSettingsForm }))
);

const registry = {
    'ghg-calculator': GhgCalculator,
    'form-builder': FormBuilder,
    'form-viewer': FormViewer,
    'auth-app': AuthApp,
    'projects-module': Projects,
    'project-mentions': ProjectMentions,
    'table-module': TableModule,
    'system-settings-form': SystemSettingsForm,
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
            <Suspense fallback={null}>
                <FormViewer {...props} />
            </Suspense>
        </React.StrictMode>
    );
    return () => root.unmount();
};

window.mountTableModule = function (container, props) {
    const root = ReactDOM.createRoot(container);
    root.render(
        <React.StrictMode>
            <Suspense fallback={null}>
                <TableModule {...props} />
            </Suspense>
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
                    <Suspense fallback={null}>
                        <Component {...props} />
                    </Suspense>
                </React.StrictMode>
            );
        });
    });
});
