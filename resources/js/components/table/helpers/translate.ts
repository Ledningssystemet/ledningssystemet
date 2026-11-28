import { t } from "../../../lib/i18n";

export function translate(key: string, values: Record<string, string | number>): string {
  return t(key).replace(/\{(\w+)\}/g, (match, name: string) =>
    Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match
  );
}
