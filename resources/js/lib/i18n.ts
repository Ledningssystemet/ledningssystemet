export const t = (key: string): string => {
    const translate = typeof window !== 'undefined'
        ? (window as Window & { translateString?: (key: string) => string }).translateString
        : undefined;
    return typeof translate === 'function' ? translate(key) : key;
};
