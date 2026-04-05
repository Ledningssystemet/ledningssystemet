import AppPageShell from '@/Pages/app/AppPageShell';
import type { AppSectionRoute } from '@/app/routes';
import { ReactNode } from 'react';

export interface AppPageContentProps {
    toneLabel: string;
    summary: string;
    highlights: string[];
    nextSteps: string[];
}

export function createAppPage(content: AppPageContentProps) {
    return (route: AppSectionRoute, highlightsTitle: string, nextStepsTitle: string, aside?: ReactNode) => (
        <AppPageShell
            title={route.label}
            description={route.description}
            categoryLabel={route.categoryLabel}
            sectionLabel={route.sectionLabel}
            toneLabel={content.toneLabel}
            routeKey={route.key}
            summary={content.summary}
            highlightsTitle={highlightsTitle}
            highlights={content.highlights}
            nextStepsTitle={nextStepsTitle}
            nextSteps={content.nextSteps}
            aside={aside}
        />
    );
}

