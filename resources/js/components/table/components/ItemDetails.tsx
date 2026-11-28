import React, { type ReactNode } from "react";
import { ItemForm } from "../forms/ItemForm";
import type { ColumnConfig, DetailSection, Item, ModuleConfig } from "../helpers/types";
import { badgeTone } from "../helpers/tones";

type Props = {
  config: ModuleConfig;
  item: Item;
  listColumns: ColumnConfig[];
  editing: Item | null;
  inlineEditing: boolean;
  tagSuggestions: (key: string) => string[];
  renderActions: (item: Item) => ReactNode;
  renderCommands?: (item: Item) => ReactNode;
  renderValue: (key: string, value: any, item?: Item) => ReactNode;
  renderChildTable: (item: Item, section: DetailSection) => ReactNode;
  onCancelEdit: () => void;
  onSave: (item: Item) => Promise<{ fields?: Record<string, string>; message?: string } | null>;
};

export function ItemDetails({ config, item, listColumns, editing, inlineEditing, tagSuggestions, renderActions, renderCommands, renderValue, renderChildTable, onCancelEdit, onSave }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <div className="flex justify-end empty:hidden">{renderActions(item)}</div>
      {inlineEditing && editing?.id === item.id ? (
        <ItemForm tagSuggestions={tagSuggestions} config={config} item={editing} isNew={false} onCancel={onCancelEdit} onSave={onSave} />
      ) : (
        <>
          {config.status?.(item)?.messages?.map((message, index) => (
            <p key={index} role="status" className={`rounded-md px-3 py-2 text-sm ${badgeTone[config.status!(item)!.tone]}`}>{message}</p>
          ))}
          <dl className="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
            {listColumns.filter((column) => column.key !== config.titleKey && !(config.hiddenFields?.(item) ?? []).includes(column.key)).map((column) => (
              <div className="mb-3" key={column.key}><dt className="text-xs uppercase tracking-wide text-muted-foreground">{column.label}</dt><dd className="text-sm">{renderValue(column.key, item[column.key], item)}</dd></div>
            ))}
          </dl>
          {config.detailSections.map((section) => (
            <section key={section.key}>
              <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{section.label}</h4>
              {section.type === "textarea" && <p className="text-sm">{item[section.key] || "–"}</p>}
              {section.type === "properties" && (
                <table className="w-full text-sm"><tbody>
                  {Object.entries(item[section.key] ?? {}).map(([key, value]) => (
                    <tr key={key} className="border-b last:border-0"><td className="py-1 pr-4 text-muted-foreground">{key}</td><td className="py-1">{String(value)}</td></tr>
                  ))}
                </tbody></table>
              )}
              {section.type === "table" && renderChildTable(item, section)}
              {section.type === "tags" && (
                <div className="flex flex-wrap gap-1">{(item[section.key] ?? []).map((tag: string) => <span key={tag} className="rounded bg-secondary px-2 py-0.5 text-xs">{tag}</span>)}</div>
              )}
            </section>
          ))}
          <div className="flex justify-end empty:hidden">{renderCommands?.(item)}</div>
        </>
      )}
    </div>
  );
}
