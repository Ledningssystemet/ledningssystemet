import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';
import AppPageShell from './AppPageShell';

const content = {
    sv: {
        toneLabel: 'Personligt',
        summary: 'Samla användarinställningar, preferenser och framtida systemval på en egen appsida.',
        highlights: [
            'Visa personliga inställningar utan att lämna React-appen',
            'Förbered plats för notifieringar, språk och standardvyer',
            'Isolera systemnära val från Blade-baserade administrationssidor',
        ],
        nextSteps: [
            'Bygg formulär för personliga preferenser',
            'Lägg till sektion för notifieringskanaler',
            'Förbered framtida systeminställningar per roll eller kund',
        ],
    },
    en: {
        toneLabel: 'Personal',
        summary: 'Collect user settings, preferences, and future system choices on a dedicated app page.',
        highlights: [
            'Show personal settings without leaving the React app',
            'Prepare space for notifications, language, and default views',
            'Isolate system-related choices from Blade-based admin pages',
        ],
        nextSteps: [
            'Build forms for personal preferences',
            'Add a section for notification channels',
            'Prepare future system settings per role or customer',
        ],
    },
};

interface SettingsPageProps {
    route: AppSectionRoute;
}

export default function SettingsPage({ route }: SettingsPageProps) {
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

