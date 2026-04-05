import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';
import AppPageShell from './AppPageShell';

const content = {
    sv: {
        toneLabel: 'Support',
        summary: 'Samla hjälptexter, guider och kontaktvägar i en gemensam ingång för användarstöd.',
        highlights: [
            'Visa vanliga frågor, guider och arbetsinstruktioner',
            'Skapa en tydlig väg till support och utbildningsmaterial',
            'Ge varje modul en naturlig plats för kontextuell hjälp',
        ],
        nextSteps: [
            'Lägg till sökbar hjälpkatalog',
            'Visa länkar till dokumentation och supportärenden',
            'Koppla hjälpinnehåll till respektive modul eller vy',
        ],
    },
    en: {
        toneLabel: 'Support',
        summary: 'Collect help texts, guides, and contact paths in a shared entry point for user support.',
        highlights: [
            'Show FAQs, guides, and work instructions',
            'Create a clear path to support and training material',
            'Give each module a natural place for contextual help',
        ],
        nextSteps: [
            'Add a searchable help catalog',
            'Show links to documentation and support cases',
            'Connect help content to each module or view',
        ],
    },
};

interface HelpPageProps {
    route: AppSectionRoute;
}

export default function HelpPage({ route }: HelpPageProps) {
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

