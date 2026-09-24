export function useTranslations() {
  type Translator = ((key: string, _replacements?: Record<string, string | number>) => string) & {
    t: (key: string, _replacements?: Record<string, string | number>) => string;
  };

  const translate = ((key: string, _replacements?: Record<string, string | number>) => {
    // @ts-ignore
    if (typeof window !== "undefined" && typeof window.translateString === "function") {
      // @ts-ignore
      return window.translateString(key);
    }

    return key;
  }) as Translator;

  translate.t = translate;

  return translate;
}
