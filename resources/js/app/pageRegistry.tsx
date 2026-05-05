import React, { JSX, lazy } from 'react';
import type { AppSectionRoute } from '@/app/routes';

// Lazy load page components – one file per route key
const pageComponents: Record<string, React.LazyExoticComponent<({ route }: { route: AppSectionRoute }) => JSX.Element>> = {
    'my-profile': lazy(() => import('@/pages/app/MyProfilePage')),
    'chemical-register': lazy(() => import('@/pages/app/ChemicalRegisterPage')),
    documents: lazy(() => import('@/pages/app/DocumentsPage')),
    'requirement-sources': lazy(() => import('@/pages/app/RequirementSourcesPage')),
    'gdpr-register': lazy(() => import('@/pages/app/GdprRegisterPage')),
    'information-handling-plan': lazy(() => import('@/pages/app/InformationHandlingPlanPage')),
    controls: lazy(() => import('@/pages/app/ControlsPage')),
    customers: lazy(() => import('@/pages/app/CustomersPage')),
    'information-types': lazy(() => import('@/pages/app/InformationTypesPage')),
    assets: lazy(() => import('@/pages/app/AssetsPage')),
    'sustainability-aspects': lazy(() => import('@/pages/app/SustainabilityAspectsPage')),
    suppliers: lazy(() => import('@/pages/app/SuppliersPage')),
    agreements: lazy(() => import('@/pages/app/AgreementsPage')),
    'process-performance': lazy(() => import('@/pages/app/ProcessPerformancePage')),
    objectives: lazy(() => import('@/pages/app/ObjectivesPage')),
    'compliance-evaluation': lazy(() => import('@/pages/app/ComplianceEvaluationPage')),
    'compliance-evaluation-evaluate': lazy(() => import('@/pages/app/ComplianceEvaluationEvaluatePage')),
    risks: lazy(() => import('@/pages/app/RisksPage')),
    'projects': lazy(() => import('@/pages/app/ProjectsPage')),
    observations: lazy(() => import('@/pages/app/ObservationsPage')),
    incidents: lazy(() => import('@/pages/app/IncidentsPage')),
    'control-actions': lazy(() => import('@/pages/app/ControlActionsPage')),
    activities: lazy(() => import('@/pages/app/ActivitiesPage')),
    'activity-flows': lazy(() => import('@/pages/app/ActivityFlowsPage')),
    employees: lazy(() => import('@/pages/app/EmployeesPage')),
    roles: lazy(() => import('@/pages/app/RolesPage')),
    qualifications: lazy(() => import('@/pages/app/QualificationsPage')),
    compentences: lazy(() => import('@/pages/app/CompentencesPage')),
    'assessment-settings': lazy(() => import('@/pages/app/AssessmentSettingsPage')),
    'supplier-categories': lazy(() => import('@/pages/app/SupplierCategoriesPage')),
    'activity-flow-templates': lazy(() => import('@/pages/app/ActivityFlowTemplatesPage')),
    'Project types': lazy(() => import('@/pages/app/ProjectTypesPage')),
    users: lazy(() => import('@/pages/app/UsersPage')),
    sites: lazy(() => import('@/pages/app/SitesPage')),
    departments: lazy(() => import('@/pages/app/DepartmentsPage')),
    'user-notification-settings': lazy(() => import('@/pages/app/UserNotificationSettingsPage')),
    'access-groups': lazy(() => import('@/pages/app/AccessGroupsPage')),
    'custom-properties': lazy(() => import('@/pages/app/CustomPropertiesPage')),
    'api-tokens': lazy(() => import('@/pages/app/ApiTokensPage')),
    tags: lazy(() => import('@/pages/app/TagsPage')),
    process: lazy(() => import('../pages/app/ProcessesPage')),
    processes: lazy(() => import('../pages/app/ProcessesPage')),
    'process-editor': lazy(() => import('../pages/app/ProcessEditorPage')),
    'document-editor': lazy(() => import('../pages/app/DocumentEditorPage')),
    'company-dashboard': lazy(() => import('@/pages/app/CompanyDashboardPage')),
};

export function resolveAppRouteElement(route: AppSectionRoute) {
    const PageComponent = pageComponents[route.key];

    if (!PageComponent) {
        // No dedicated page exists for this route key yet
        return null;
    }

    return <PageComponent route={route} />;
}
