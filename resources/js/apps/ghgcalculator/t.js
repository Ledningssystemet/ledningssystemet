export const t = (str) =>
   typeof window !== 'undefined' && typeof window.translateString === 'function'
      ? window.translateString(str)
      : str;
