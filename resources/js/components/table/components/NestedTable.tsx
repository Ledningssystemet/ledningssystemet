import { Plus, Trash2 } from "lucide-react";
import type { DetailSection } from "../types";
import { btnOutline, inputCls } from "../helpers/styles";

type TableSection = Pick<DetailSection, "columns">;
type EditableTableSection = Pick<DetailSection, "label" | "columns">;

export function NestedTable({ section, rows }: { section: TableSection; rows: Record<string, any>[] }) {
  const columns = section.columns ?? [];
  if (!rows.length) return <p className="text-sm text-muted-foreground">Inga rader.</p>;
  return (
    <div className="overflow-x-auto rounded-md border">
      <table className="w-full text-sm">
        <thead className="bg-muted/60 text-left"><tr>{columns.map((column) => <th key={column.key} className="px-3 py-1.5 font-medium">{column.label}</th>)}</tr></thead>
        <tbody>{rows.map((row, index) => <tr key={index} className="border-t">{columns.map((column) => <td key={column.key} className="px-3 py-1.5">{String(row[column.key] ?? "")}</td>)}</tr>)}</tbody>
      </table>
    </div>
  );
}

export function NestedTableEditor({ section, rows, onChange }: { section: EditableTableSection; rows: Record<string, any>[]; onChange: (rows: Record<string, any>[]) => void }) {
  const columns = section.columns ?? [];
  const update = (index: number, key: string, value: string) => onChange(rows.map((row, rowIndex) => (rowIndex === index ? { ...row, [key]: value } : row)));
  return (
    <div className="space-y-2">
      {rows.length > 0 && (
        <table className="w-full text-sm">
          <thead className="text-left"><tr>{columns.map((column) => <th key={column.key} className="pb-1 pr-2 font-normal text-muted-foreground">{column.label}</th>)}<th /></tr></thead>
          <tbody>{rows.map((row, index) => (
            <tr key={index}>
              {columns.map((column) => <td key={column.key} className="pb-1 pr-2"><input aria-label={`${column.label} rad ${index + 1}`} className={inputCls} value={row[column.key] ?? ""} onChange={(event) => update(index, column.key, event.target.value)} /></td>)}
              <td className="pb-1"><button type="button" aria-label={`Ta bort rad ${index + 1}`} className={`${btnOutline} px-2 text-destructive`} onClick={() => onChange(rows.filter((_, rowIndex) => rowIndex !== index))}><Trash2 className="h-4 w-4" /></button></td>
            </tr>
          ))}</tbody>
        </table>
      )}
      <button type="button" className={btnOutline} onClick={() => onChange([...rows, Object.fromEntries(columns.map((column) => [column.key, ""]))])}><Plus className="h-4 w-4" />Lägg till rad</button>
    </div>
  );
}
