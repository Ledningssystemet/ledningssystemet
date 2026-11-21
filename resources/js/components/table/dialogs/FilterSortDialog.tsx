import React from "react";
import * as Dialog from "@radix-ui/react-dialog";
import * as Label from "@radix-ui/react-label";
import { MultiSelect, TagSelect } from "../components/MultiSelect";
import { RSelect } from "../components/RSelect";
import type { ModuleConfig, SortState } from "../helpers/types";
import { normalizeOptions } from "../helpers/logic";
import { btnOutline, btnPrimary, dialogBox, overlay } from "../helpers/styles";
import { t } from "../../../lib/i18n";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  config: ModuleConfig;
  filters: Record<string, string | string[]>;
  setFilters: (filters: Record<string, string | string[]>) => void;
  setFilter: (key: string, value: string | string[]) => void;
  allValues: (key: string) => string[];
  sort: SortState;
  setSort: (sort: SortState | ((current: SortState) => SortState)) => void;
  setPage: (page: number) => void;
};

const ALL = "__all__";
const NONE = "__none__";
export function FilterSortDialog({ open, onOpenChange, config, filters, setFilters, setFilter, allValues, sort, setSort, setPage }: Props) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <div className="ls-table">
          <Dialog.Overlay className={overlay} />
          <Dialog.Content className={dialogBox}>
            <Dialog.Title className="mb-4 text-lg font-semibold">{t("Filter and sorting")}</Dialog.Title>
            <Dialog.Description className="sr-only">{t("Select sorting and filters for the list.")}</Dialog.Description>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t("Sorting")}</h3>
            <div className="mb-5 grid grid-cols-2 gap-3">
              <div><Label.Root className="mb-1 block text-sm">{t("Sort by")}</Label.Root>
                <RSelect label={t("Sort by")} value={sort.key ?? NONE} onChange={(value) => setSort((current) => ({ ...current, key: value === NONE ? null : value }))} placeholder={t("None")}
                  options={[{ value: NONE, label: t("None") }, ...config.columns.filter((column) => column.sortable).map((column) => ({ value: column.key, label: column.label }))]} /></div>
              <div><Label.Root className="mb-1 block text-sm">{t("Direction")}</Label.Root>
                <RSelect label={t("Direction")} value={sort.dir} onChange={(value) => setSort((current) => ({ ...current, dir: value as "asc" | "desc" }))} placeholder=""
                  options={[{ value: "asc", label: t("Ascending") }, { value: "desc", label: t("Descending") }]} /></div>
            </div>
            {config.filters.length > 0 && <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">{t("Filter")}</h3>}
            <div className="space-y-3">
              {config.filters.map((filter) => {
                const options = normalizeOptions(filter.options ?? allValues(filter.key));
                return <div key={filter.key}><Label.Root className="mb-1 block text-sm">{filter.label}{filter.mode === "multi" && <span className="ml-1 text-xs text-muted-foreground">{t("(multiple choice)")}</span>}{filter.mode === "tags" && <span className="ml-1 text-xs text-muted-foreground">{t("(multiple choice, search tags)")}</span>}</Label.Root>
                  {filter.mode === "multi" ? (
                    <MultiSelect label={filter.label} options={options} value={(filters[filter.key] as string[]) ?? []} onChange={(value) => setFilter(filter.key, value)} />
                  ) : filter.mode === "tags" ? (
                    <TagSelect label={filter.label} id={`filter-${filter.key}`} allowCreate={false} suggestions={options.map((option) => option.value)} value={(filters[filter.key] as string[]) ?? []} onChange={(value) => setFilter(filter.key, value)} />
                  ) : (
                    <RSelect label={filter.label} value={(filters[filter.key] as string) || ALL} placeholder={t("All")} onChange={(value) => setFilter(filter.key, value === ALL ? "" : value)}
                      options={[{ value: ALL, label: t("All") }, ...options]} />
                  )}</div>;
              })}
            </div>
            <div className="mt-6 flex justify-between">
              <button className={btnOutline} onClick={() => { setFilters({}); setSort({ key: null, dir: "asc" }); setPage(1); }}>{t("Clear all")}</button>
              <Dialog.Close className={btnPrimary}>{t("Done")}</Dialog.Close>
            </div>
          </Dialog.Content>
        </div>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
