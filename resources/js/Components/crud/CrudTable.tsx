import React, { useState, useCallback } from 'react';
import { Plus, AlertCircle, Loader } from 'lucide-react';
import { CrudTableConfig, ViewMode, CrudRecord } from '@/types/crud';
import { useCrudTable } from '@/hooks/useCrudTable';
import { FilterBar } from './filters/FilterBar';
import { TableView } from './views/TableView';
import { AccordionView } from './views/AccordionView';
import { MasterDetailView } from './views/MasterDetailView';
import { EditModal } from './modals/EditModal';
import { CreateModal } from './modals/CreateModal';
import { DeleteConfirm } from './modals/DeleteConfirm';

interface CrudTableProps {
  config: CrudTableConfig;
  viewMode?: ViewMode;
  onViewModeChange?: (mode: ViewMode) => void;
}

export function CrudTable({
  config,
  viewMode = 'table',
  onViewModeChange,
}: CrudTableProps) {
  const {
    state,
    setFilters,
    setSearch,
    setSort,
    setPage,
    toggleSelectRow,
    selectAll,
    createRecord,
    updateRecord,
    deleteRecord,
    bulkUpdate,
    refresh,
  } = useCrudTable({
    resource: config.resource,
    paginate: config.paginate,
    perPage: config.perPage,
  });

  const [editingRecord, setEditingRecord] = useState<CrudRecord | null>(null);
  const [deleteRecord_id, setDeleteRecord_id] = useState<string | number | null>(null);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [bulkEditIds, setBulkEditIds] = useState<(string | number)[] | null>(null);
  const [expandedAccordionId, setExpandedAccordionId] = useState<string | number | null>(null);
  const [selectedDetailId, setSelectedDetailId] = useState<string | number | null>(null);

  // Handle Edit
  const handleEdit = useCallback((record: CrudRecord) => {
    setEditingRecord(record);
    setShowEditModal(true);
  }, []);

  // Handle Create
  const handleCreate = useCallback(async (data: Omit<CrudRecord, 'id'>) => {
    await createRecord(data);
    setShowCreateModal(false);
  }, [createRecord]);

  // Handle Update
  const handleUpdate = useCallback(async (data: Partial<CrudRecord>) => {
    if (bulkEditIds) {
      // Bulk update
      await bulkUpdate({ ids: bulkEditIds, data });
      setBulkEditIds(null);
      setShowEditModal(false);
    } else if (editingRecord) {
      // Single update
      await updateRecord(editingRecord.id, data);
      setShowEditModal(false);
    }
  }, [editingRecord, bulkEditIds, updateRecord, bulkUpdate]);

  // Handle Delete
  const handleDelete = useCallback(async (record: CrudRecord) => {
    setDeleteRecord_id(record.id);
    setShowDeleteConfirm(true);
  }, []);

  // Confirm Delete
  const handleConfirmDelete = useCallback(async () => {
    if (deleteRecord_id) {
      await deleteRecord(deleteRecord_id);
      setDeleteRecord_id(null);
      setShowDeleteConfirm(false);
    }
  }, [deleteRecord_id, deleteRecord]);

  // Handle Bulk Edit
  const handleBulkEdit = useCallback((ids: (string | number)[]) => {
    setBulkEditIds(ids);
    setEditingRecord(null);
    setShowEditModal(true);
  }, []);

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          {config.title && (
            <h2 className="text-2xl font-bold text-gray-900">{config.title}</h2>
          )}
          {config.description && (
            <p className="text-gray-600 text-sm mt-1">{config.description}</p>
          )}
        </div>

        <div className="flex gap-2">
          {/* View Mode Selector */}
          {onViewModeChange && (
            <div className="flex gap-1 border border-gray-300 rounded-lg p-1">
              {(['table', 'accordion', 'master-detail'] as const).map(mode => (
                <button
                  key={mode}
                  onClick={() => onViewModeChange(mode)}
                  className={`px-3 py-1.5 text-sm font-medium rounded transition ${
                    viewMode === mode
                      ? 'bg-blue-600 text-white'
                      : 'text-gray-700 hover:bg-gray-100'
                  }`}
                >
                  {mode === 'master-detail' ? 'Master/Detail' : mode.charAt(0).toUpperCase() + mode.slice(1)}
                </button>
              ))}
            </div>
          )}

          {/* Create Button */}
          {config.creatable && (
            <button
              onClick={() => setShowCreateModal(true)}
              className="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition"
            >
              <Plus size={18} />
              New
            </button>
          )}

          {/* Refresh Button */}
          <button
            onClick={refresh}
            disabled={state.loading}
            className="px-3 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition disabled:opacity-50"
          >
            ↻
          </button>
        </div>
      </div>

      {/* Error Message */}
      {state.error && (
        <div className="p-4 bg-red-50 border border-red-200 rounded-lg flex gap-3">
          <AlertCircle size={20} className="text-red-600 flex-shrink-0" />
          <p className="text-red-700">{state.error}</p>
        </div>
      )}

      {/* Filter Bar */}
      <FilterBar
        columns={config.columns}
        onSearchChange={setSearch}
        onFiltersChange={setFilters}
        onSortChange={setSort}
        search={state.search}
        filters={state.filters}
        sort={state.sort}
      />

      {/* Loading State */}
      {state.loading && (
        <div className="flex items-center justify-center py-12 text-gray-500">
          <Loader size={24} className="animate-spin mr-2" />
          Loading...
        </div>
      )}

      {/* Views */}
      {!state.loading && (
        <>
          {viewMode === 'table' && (
            <TableView
              data={state.data}
              columns={config.columns}
              config={config}
              selectedRows={state.selectedRows}
              onSelectRow={toggleSelectRow}
              onSelectAll={selectAll}
              onSort={setSort}
              currentSort={state.sort}
              onEdit={handleEdit}
              onDelete={handleDelete}
              onBulkEdit={handleBulkEdit}
            />
          )}

          {viewMode === 'accordion' && (
            <AccordionView
              data={state.data}
              columns={config.columns}
              config={config}
              expandedId={expandedAccordionId}
              onExpand={setExpandedAccordionId}
              onEdit={handleEdit}
              onDelete={handleDelete}
            />
          )}

          {viewMode === 'master-detail' && (
            <MasterDetailView
              data={state.data}
              columns={config.columns}
              config={config}
              selectedId={selectedDetailId}
              onSelect={setSelectedDetailId}
              onEdit={handleEdit}
              onDelete={handleDelete}
            />
          )}
        </>
      )}

      {/* Pagination */}
      {config.paginate && state.pagination.total > state.pagination.perPage && !state.loading && (
        <div className="flex items-center justify-between">
          <p className="text-sm text-gray-600">
            Showing {state.data.length} of {state.pagination.total} records
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => setPage(state.pagination.page - 1)}
              disabled={state.pagination.page === 1}
              className="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
            >
              Previous
            </button>
            <span className="px-3 py-2 text-sm text-gray-600">
              Page {state.pagination.page} of {state.pagination.total ? Math.ceil(state.pagination.total / state.pagination.perPage) : 1}
            </span>
            <button
              onClick={() => setPage(state.pagination.page + 1)}
              disabled={state.pagination.page >= (state.pagination.total ? Math.ceil(state.pagination.total / state.pagination.perPage) : 1)}
              className="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      )}

      {/* Modals */}
      <CreateModal
        isOpen={showCreateModal}
        columns={config.columns}
        config={config}
        onClose={() => setShowCreateModal(false)}
        onSave={handleCreate}
      />

      <EditModal
        isOpen={showEditModal}
        record={bulkEditIds ? null : editingRecord}
        columns={config.columns}
        config={config}
        onClose={() => {
          setShowEditModal(false);
          setEditingRecord(null);
          setBulkEditIds(null);
        }}
        onSave={handleUpdate}
      />

      <DeleteConfirm
        isOpen={showDeleteConfirm}
        onClose={() => setShowDeleteConfirm(false)}
        onConfirm={handleConfirmDelete}
      />
    </div>
  );
}

