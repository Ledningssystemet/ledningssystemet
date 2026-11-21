import React, { type ReactNode } from "react";
import type { Item, ModuleConfig } from "../helpers/types";
import { t } from "../../../lib/i18n";

type Props = {
  items: Item[];
  config: ModuleConfig;
  selected: Item | null;
  selectedId: string | null;
  onSelect: (id: string) => void;
  renderStatus: (item: Item) => ReactNode;
  renderDetails: (item: Item) => ReactNode;
};

export function MasterDetailView({ items, config, selected, selectedId, onSelect, renderStatus, renderDetails }: Props) {
  return (
    <div className="grid md:grid-cols-[minmax(240px,1fr)_2fr]">
      <ul className="border-b md:border-b-0 md:border-r">
        {items.map((item) => (
          <li key={item.id}>
            <button onClick={() => onSelect(item.id)} className={`w-full border-b px-4 py-2.5 text-left hover:bg-surface-hover ${item.id === selectedId ? "border-l-4 border-l-primary bg-accent" : ""}`}>
              <div className="font-medium">{item[config.titleKey]}{renderStatus(item)}</div>
              <div className="text-xs text-muted-foreground">{config.subtitleKeys.map((key) => item[key]).filter(Boolean).join(" · ")}</div>
            </button>
          </li>
        ))}
      </ul>
      <div className="min-w-0 p-4">
        {selected ? (
          <>
            <h3 className="mb-4 text-xl font-semibold">{selected[config.titleKey]}{renderStatus(selected)}</h3>
            {renderDetails(selected)}
          </>
        ) : <p className="text-muted-foreground">{t("Select an item from the list.")}</p>}
      </div>
    </div>
  );
}
