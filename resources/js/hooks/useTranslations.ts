import { usePage } from '@inertiajs/react';

interface SharedProps {
  locale?: string;
  translations?: Record<string, any>;
  [key: string]: unknown;
}

function resolvePath(source: any, path: string): string | undefined {
  const parts = path.split('.');
  let current = source;

  for (const part of parts) {
    if (current == null || typeof current !== 'object' || !(part in current)) {
      return undefined;
    }

    current = current[part];
  }

  return typeof current === 'string' ? current : undefined;
}

function interpolate(template: string, replacements: Record<string, string | number>): string {
  return Object.entries(replacements).reduce((result, [key, value]) => {
    return result.replace(new RegExp(`:${key}`, 'g'), String(value));
  }, template);
}

export function useTranslations() {
  const page = usePage();
  const props = page.props as SharedProps;
  const translations = props.translations ?? {};

  const t = (key: string, replacements: Record<string, string | number> = {}): string => {
    const value = resolvePath(translations, key);

    if (value == null) {
      return key;
    }

    return interpolate(value, replacements);
  };

  return {
    locale: props.locale ?? 'en',
    t,
  };
}

