import * as Dialog from "@radix-ui/react-dialog";
import * as Label from "@radix-ui/react-label";
import { MultiSelect, TagSelect } from "../MultiSelect";
import { RSelect } from "../components/RSelect";
import type { ModuleConfig, SortState } from "../types";
import { btnOutline, btnPrimary, dialogBox, overlay } from "../helpers/styles";

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
const so = (values: (string | number)[]) => values.map((value) => ({ value: String(value), label: String(value) }));

export function FilterSortDialog({ open, onOpenChange, config, filters, setFilters, setFilter, allValues, sort, setSort, setPage }: Props) {
  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <div className="ls-table">
          <Dialog.Overlay className={overlay} />
          <Dialog.Content className={dialogBox}>
            <Dialog.Title className="mb-4 text-lg font-semibold">Filter och sortering</Dialog.Title>
            <Dialog.Description className="sr-only">Välj sortering och filter för listan.</Dialog.Description>
            <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Sortering</h3>
            <div className="mb-5 grid grid-cols-2 gap-3">
              <div><Label.Root className="mb-1 block text-sm">Sortera på</Label.Root>
                <RSelect label="Sortera på" value={sort.key ?? NONE} onChange={(value) => setSort((current) => ({ ...current, key: value === NONE ? null : value }))} placeholder="Ingen"
                  options={[{ value: NONE, label: "Ingen" }, ...config.columns.filter((column) => column.sortable).map((column) => ({ value: column.key, label: column.label }))]} /></div>
              <div><Label.Root className="mb-1 block text-sm">Riktning</Label.Root>
                <RSelect label="Riktning" value={sort.dir} onChange={(value) => setSort((current) => ({ ...current, dir: value as "asc" | "desc" }))} placeholder=""
                  options={[{ value: "asc", label: "Stigande (A–Ö)" }, { value: "desc", label: "Fallande (Ö–A)" }]} /></div>
            </div>
            {config.filters.length > 0 && <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Filter</h3>}
            <div className="space-y-3">
              {config.filters.map((filter) => (
                <div key={filter.key}><Label.Root className="mb-1 block text-sm">{filter.label}{filter.mode === "multi" && <span className="ml-1 text-xs text-muted-foreground">(flerval)</span>}{filter.mode === "tags" && <span className="ml-1 text-xs text-muted-foreground">(flerval, sök tagg)</span>}</Label.Root>
                  {filter.mode === "multi" ? (
                    <MultiSelect label={filter.label} options={filter.options ?? allValues(filter.key)} value={(filters[filter.key] as string[]) ?? []} onChange={(value) => setFilter(filter.key, value)} />
                  ) : filter.mode === "tags" ? (
                    <TagSelect label={filter.label} id={`filter-${filter.key}`} allowCreate={false} suggestions={filter.options ?? allValues(filter.key)} value={(filters[filter.key] as string[]) ?? []} onChange={(value) => setFilter(filter.key, value)} />
                  ) : (
                    <RSelect label={filter.label} value={(filters[filter.key] as string) || ALL} placeholder="Alla" onChange={(value) => setFilter(filter.key, value === ALL ? "" : value)}
                      options={[{ value: ALL, label: "Alla" }, ...so(filter.options ?? allValues(filter.key))]} />
                  )}</div>
              ))}
            </div>
            <div className="mt-6 flex justify-between">
              <button className={btnOutline} onClick={() => { setFilters({}); setSort({ key: null, dir: "asc" }); setPage(1); }}>Rensa alla</button>
              <Dialog.Close className={btnPrimary}>Klar</Dialog.Close>
            </div>
          </Dialog.Content>
        </div>
      </Dialog.Portal>
    </Dialog.Root>
  );
}
