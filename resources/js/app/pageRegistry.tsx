import React, { JSX, lazy } from 'react';
import type { AppSectionRoute } from '@/app/routes';

// Lazy load page components – one file per route key
const pageComponents: Record<string, React.LazyExoticComponent<({ route }: { route: AppSectionRoute }) => JSX.Element>> = {
    'my-profile': lazy(() => import('@/Pages/app/MyProfilePage')),
    'chemical-register': lazy(() => import('@/Pages/app/ChemicalRegisterPage')),
    documents: lazy(() => import('@/Pages/app/DocumentsPage')),
    'requirement-sources': lazy(() => import('@/Pages/app/RequirementSourcesPage')),
    'gdpr-register': lazy(() => import('@/Pages/app/GdprRegisterPage')),
    'information-handling-plan': lazy(() => import('@/Pages/app/InformationHandlingPlanPage')),
    controls: lazy(() => import('@/Pages/app/ControlsPage')),
    customers: lazy(() => import('@/Pages/app/CustomersPage')),
    'information-types': lazy(() => import('@/Pages/app/InformationTypesPage')),
    assets: lazy(() => import('@/Pages/app/AssetsPage')),
    'sustainability-aspects': lazy(() => import('@/Pages/app/SustainabilityAspectsPage')),
    suppliers: lazy(() => import('@/Pages/app/SuppliersPage')),
    agreements: lazy(() => import('@/Pages/app/AgreementsPage')),
    'process-performance': lazy(() => import('@/Pages/app/ProcessPerformancePage')),
    objectives: lazy(() => import('@/Pages/app/ObjectivesPage')),
    'compliance-evaluation': lazy(() => import('@/Pages/app/ComplianceEvaluationPage')),
    'compliance-evaluation-evaluate': lazy(() => import('@/Pages/app/ComplianceEvaluationEvaluatePage')),
    risks: lazy(() => import('@/Pages/app/RisksPage')),
    'projects': lazy(() => import('@/Pages/app/ProjectsPage')),
    observations: lazy(() => import('@/Pages/app/ObservationsPage')),
    incidents: lazy(() => import('@/Pages/app/IncidentsPage')),
    'control-actions': lazy(() => import('@/Pages/app/ControlActionsPage')),
    activities: lazy(() => import('@/Pages/app/ActivitiesPage')),
    'activity-flows': lazy(() => import('@/Pages/app/ActivityFlowsPage')),
    employees: lazy(() => import('@/Pages/app/EmployeesPage')),
    roles: lazy(() => import('@/Pages/app/RolesPage')),
    qualifications: lazy(() => import('@/Pages/app/QualificationsPage')),
    compentences: lazy(() => import('@/Pages/app/CompentencesPage')),
    'assessment-settings': lazy(() => import('@/Pages/app/AssessmentSettingsPage')),
    'supplier-categories': lazy(() => import('@/Pages/app/SupplierCategoriesPage')),
    'activity-flow-templates': lazy(() => import('@/Pages/app/ActivityFlowTemplatesPage')),
    'Project types': lazy(() => import('@/Pages/app/ProjectTypesPage')),
    users: lazy(() => import('@/Pages/app/UsersPage')),
    sites: lazy(() => import('@/Pages/app/SitesPage')),
    departments: lazy(() => import('@/Pages/app/DepartmentsPage')),
    'user-notification-settings': lazy(() => import('@/Pages/app/UserNotificationSettingsPage')),
    'access-groups': lazy(() => import('@/Pages/app/AccessGroupsPage')),
    'custom-properties': lazy(() => import('@/Pages/app/CustomPropertiesPage')),
    'api-tokens': lazy(() => import('@/Pages/app/ApiTokensPage')),
    tags: lazy(() => import('@/Pages/app/TagsPage')),
    process: lazy(() => import('../Pages/app/ProcessesPage')),
    processes: lazy(() => import('../Pages/app/ProcessesPage')),
    'process-editor': lazy(() => import('../Pages/app/ProcessEditorPage')),
    'document-editor': lazy(() => import('../Pages/app/DocumentEditorPage')),
    'company-dashboard': lazy(() => import('@/Pages/app/CompanyDashboardPage')),
};

export function resolveAppRouteElement(route: AppSectionRoute) {
    const PageComponent = pageComponents[route.key];

    if (!PageComponent) {
        // No dedicated page exists for this route key yet
        return null;
    }

    return <PageComponent route={route} />;
}
