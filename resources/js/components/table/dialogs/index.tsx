import React from "react";
import { BulkDeleteDialog } from "./BulkDeleteDialog";
import { BulkEditDialog } from "./BulkEditDialog";
import { DeleteDialog } from "./DeleteDialog";
import { EditDialog } from "./EditDialog";
import { FilterSortDialog } from "./FilterSortDialog";
import type { BulkStep, SaveResult } from "./types";
import type { Item, ModuleConfig, SortState } from "../helpers/types";

type Props = {
  config: ModuleConfig;
  filters: Record<string, string | string[]>;
  setFilters: (filters: Record<string, string | string[]>) => void;
  setFilter: (key: string, value: string | string[]) => void;
  allValues: (key: string) => string[];
  sort: SortState;
  setSort: (sort: SortState | ((current: SortState) => SortState)) => void;
  setPage: (page: number) => void;
  filterOpen: boolean;
  setFilterOpen: (open: boolean) => void;
  editing: Item | null;
  setEditing: (item: Item | null) => void;
  isNew: boolean;
  save: (item: Item) => Promise<SaveResult>;
  bulkStep: BulkStep;
  setBulkStep: (step: BulkStep) => void;
  checkedCount: number;
  checkedItems: Item[];
  canUpdate: (item: Item) => boolean;
  bulkFields: string[];
  setBulkFields: (fields: string[] | ((current: string[]) => string[])) => void;
  bulkConfig: ModuleConfig;
  bulkStart: () => Item;
  saveBulk: (item: Item) => Promise<SaveResult>;
  bulkDelete: boolean;
  setBulkDelete: (open: boolean) => void;
  bulkBusy: boolean;
  runBulkDelete: () => void;
  deleting: Item | null;
  setDeleting: (item: Item | null) => void;
  deleteError: string | null;
  setDeleteError: (message: string | null) => void;
  canDelete: (item: Item) => boolean;
  remove: (item: Item) => void;
};

export function TableDialogs(props: Props) {
  const {
    config, filters, setFilters, setFilter, allValues, sort, setSort, setPage,
    filterOpen, setFilterOpen, editing, setEditing, isNew, save, bulkStep,
    setBulkStep, checkedCount, checkedItems, canUpdate, bulkFields, setBulkFields,
    bulkConfig, bulkStart, saveBulk, bulkDelete, setBulkDelete, bulkBusy,
    runBulkDelete, deleting, setDeleting, deleteError, setDeleteError, canDelete, remove,
  } = props;

  return (
    <>
      <FilterSortDialog
        open={filterOpen}
        onOpenChange={setFilterOpen}
        config={config}
        filters={filters}
        setFilters={setFilters}
        setFilter={setFilter}
        allValues={allValues}
        sort={sort}
        setSort={setSort}
        setPage={setPage}
      />
      <EditDialog config={config} editing={editing} setEditing={setEditing} isNew={isNew} save={save} allValues={allValues} />
      <BulkEditDialog
        config={config}
        step={bulkStep}
        setStep={setBulkStep}
        checkedCount={checkedCount}
        checkedItems={checkedItems}
        canUpdate={canUpdate}
        fields={bulkFields}
        setFields={setBulkFields}
        bulkConfig={bulkConfig}
        bulkStart={bulkStart}
        saveBulk={saveBulk}
        allValues={allValues}
      />
      <BulkDeleteDialog
        open={bulkDelete}
        setOpen={setBulkDelete}
        busy={bulkBusy}
        checkedCount={checkedCount}
        checkedItems={checkedItems}
        canDelete={canDelete}
        runBulkDelete={runBulkDelete}
        titleKey={config.titleKey}
      />
      <DeleteDialog config={config} deleting={deleting} setDeleting={setDeleting} deleteError={deleteError} setDeleteError={setDeleteError} remove={remove} />
    </>
  );
}
