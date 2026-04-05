import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';
import AppPageShell from './AppPageShell';

const content = {
    sv: {
        toneLabel: 'Händelsestyrt',
        summary: 'Hantera observationer i ett sammanhållet flöde med registrering, prioritering och uppföljning.',
        highlights: [
            'Samla nya observationer från verksamheten på ett ställe',
            'Prioritera sådant som kräver snabb uppföljning eller eskalering',
            'Knyt observationer till ansvarig, datum och förbättringsarbete',
        ],
        nextSteps: [
            'Skapa vy för nya observationer',
            'Lägg till filtrering på status, risknivå och ansvarig',
            'Koppla observationer till avvikelse- och revisionsflöden',
        ],
    },
    en: {
        toneLabel: 'Event driven',
        summary: 'Manage observations in a unified flow with registration, prioritization, and follow-up.',
        highlights: [
            'Collect new observations from the organization in one place',
            'Prioritize items that require rapid follow-up or escalation',
            'Connect observations to an owner, date, and improvement work',
        ],
        nextSteps: [
            'Create a view for newly reported observations',
            'Add filtering by status, risk level, and owner',
            'Connect observations to findings and audit workflows',
        ],
    },
};

interface ObservationPageProps {
    route: AppSectionRoute;
}

export default function ObservationPage({ route }: ObservationPageProps) {
    const { locale } = useTranslations();
    const localeKey = locale.toLowerCase().startsWith('sv') ? 'sv' : 'en';
    const data = content[localeKey];

    return (
        <AppPageShell
            title={route.label}
            description={route.description}
            categoryLabel={route.categoryLabel}
            sectionLabel={route.sectionLabel}
            toneLabel={data.toneLabel}
            routeKey={route.key}
            summary={data.summary}
            highlightsTitle={localeKey === 'sv' ? 'Fokusområden' : 'Focus areas'}
            highlights={data.highlights}
            nextStepsTitle={localeKey === 'sv' ? 'Föreslagna nästa steg' : 'Suggested next steps'}
            nextSteps={data.nextSteps}
        />
    );
}

