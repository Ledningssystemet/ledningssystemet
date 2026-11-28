export type BulkStep = "fields" | "form" | null;
export type SaveResult = { fields?: Record<string, string>; message?: string } | null;
