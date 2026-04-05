import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';
import AppPageShell from './AppPageShell';

const content = {
    sv: {
        toneLabel: 'Identitet',
        summary: 'Ge användaren en samlad bild av profil, behörigheter, kontaktuppgifter och personliga val.',
        highlights: [
            'Visa grunddata för användaren och tillhörande organisation',
            'Samla roller, accessgrupper och andra tilldelade behörigheter',
            'Förbered yta för kompetenser, signaturer och personliga inställningar',
        ],
        nextSteps: [
            'Bygg redigering av profiluppgifter',
            'Lägg till sektion för behörighetsöversikt',
            'Visa historik för uppdateringar och inloggningar',
        ],
    },
    en: {
        toneLabel: 'Identity',
        summary: 'Give the user a consolidated view of profile, permissions, contact details, and personal choices.',
        highlights: [
            'Show core user data and the connected organization',
            'Collect roles, access groups, and other assigned permissions',
            'Prepare space for competences, signatures, and personal settings',
        ],
        nextSteps: [
            'Build profile editing',
            'Add a permission overview section',
            'Show update and sign-in history',
        ],
    },
};

interface MinProfilPageProps {
    route: AppSectionRoute;
}

export default function MinProfilPage({ route }: MinProfilPageProps) {
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

