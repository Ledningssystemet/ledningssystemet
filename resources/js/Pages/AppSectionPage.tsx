import AppPageShell from '@/Pages/app/AppPageShell';
import { useTranslations } from '@/hooks/useTranslations';

interface AppSectionPageProps {
    title: string;
    description?: string;
    categoryLabel?: string;
    sectionLabel?: string;
    routeKey?: string;
}

export default function AppSectionPage({
    title,
    description,
    categoryLabel,
    sectionLabel,
    routeKey,
}: AppSectionPageProps) {
    const { t } = useTranslations();

    return (
        <AppPageShell
            title={title}
            description={description}
            categoryLabel={categoryLabel}
            sectionLabel={sectionLabel}
            routeKey={routeKey}
            summary={description ?? t('ui.app.workspace_description', { page: title })}
            highlightsTitle={t('ui.app.focus_title')}
            highlights={[
                t('ui.app.default_focus_one', { page: title }),
                t('ui.app.default_focus_two', { page: title }),
                t('ui.app.default_focus_three', { page: title }),
            ]}
            nextStepsTitle={t('ui.app.next_steps_title')}
            nextSteps={[
                t('ui.app.default_next_step_one', { page: title }),
                t('ui.app.default_next_step_two', { page: title }),
                t('ui.app.default_next_step_three', { page: title }),
            ]}
        />
    );
}

