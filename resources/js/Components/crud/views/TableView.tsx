import React from 'react';
import { Edit, Trash2, ArrowUpDown } from 'lucide-react';
import { CrudRecord, ColumnDef, CrudTableConfig, CrudSort } from '@/types/crud';

interface TableViewProps {
  data: CrudRecord[];
  columns: ColumnDef[];
  config: CrudTableConfig;
  selectedRows: Set<string | number>;
  onSelectRow: (id: string | number) => void;
  onSelectAll: (selected: boolean) => void;
  onSort?: (sort: CrudSort | null) => void;
  currentSort?: CrudSort | null;
  onEdit: (record: CrudRecord) => void;
  onDelete: (record: CrudRecord) => void;
  onBulkEdit?: (ids: (string | number)[]) => void;
}

export function TableView({
  data,
  columns,
  config,
  selectedRows,
  onSelectRow,
  onSelectAll,
  onSort,
  currentSort,
  onEdit,
  onDelete,
  onBulkEdit,
}: TableViewProps) {
  const visibleColumns = columns.filter(col => !col.hidden);
  const allSelected = data.length > 0 && data.every(r => selectedRows.has(r.id));
  const someSelected = selectedRows.size > 0 && !allSelected;

  const handleColumnSort = (column: ColumnDef) => {
    if (!column.sortable || !onSort) return;

    if (currentSort?.field === column.key) {
      // Toggle direction
      if (currentSort.direction === 'asc') {
        onSort({ field: column.key, direction: 'desc' });
      } else {
        onSort(null);
      }
    } else {
      onSort({ field: column.key, direction: 'asc' });
    }
  };

  return (
    <div className="overflow-x-auto border border-gray-200 rounded-lg">
      <table className="w-full">
        <thead className="bg-gray-50 border-b border-gray-200">
          <tr>
            {/* Checkbox Column */}
            <th className="w-12 px-4 py-3">
              <input
                type="checkbox"
                ref={el => {
                  if (el) el.indeterminate = someSelected;
                }}
                checked={allSelected}
                onChange={e => onSelectAll(e.target.checked)}
                className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              />
            </th>

            {/* Data Columns */}
            {visibleColumns.map(column => (
              <th
                key={column.key}
                className={`px-4 py-3 text-left text-sm font-semibold text-gray-900 ${
                  column.width ? `w-[${column.width}]` : ''
                }`}
              >
                {column.headerRender ? (
                  column.headerRender()
                ) : (
                  <div className="flex items-center gap-2">
                    <span>{column.label}</span>
                    {column.sortable && onSort && (
                      <button
                        onClick={() => handleColumnSort(column)}
                        className="p-1 hover:bg-gray-200 rounded transition"
                        title="Sort"
                      >
                        <ArrowUpDown
                          size={14}
                          className={
                            currentSort?.field === column.key
                              ? 'text-blue-600'
                              : 'text-gray-400'
                          }
                        />
                      </button>
                    )}
                  </div>
                )}
              </th>
            ))}

            {/* Actions Column */}
            {(config.editable || config.deletable || config.actions?.custom) && (
              <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 w-20">
                Actions
              </th>
            )}
          </tr>
        </thead>

        <tbody className="divide-y divide-gray-200">
          {data.length === 0 ? (
            <tr>
              <td
                colSpan={visibleColumns.length + 2}
                className="px-4 py-8 text-center text-gray-500"
              >
                No data found
              </td>
            </tr>
          ) : (
            data.map(record => (
              <tr
                key={record.id}
                className={`hover:bg-gray-50 transition ${
                  selectedRows.has(record.id) ? 'bg-blue-50' : ''
                }`}
              >
                {/* Checkbox */}
                <td className="px-4 py-3">
                  <input
                    type="checkbox"
                    checked={selectedRows.has(record.id)}
                    onChange={() => onSelectRow(record.id)}
                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                </td>

                {/* Data */}
                {visibleColumns.map(column => (
                  <td key={column.key} className="px-4 py-3 text-sm text-gray-900">
                    {column.render
                      ? column.render(record)
                      : column.format ? column.format(record[column.key]) : record[column.key] || '-'}
                  </td>
                ))}

                {/* Actions */}
                {(config.editable || config.deletable || config.actions?.custom) && (
                  <td className="px-4 py-3">
                    <div className="flex gap-2">
                      {config.editable && (
                        <button
                          onClick={() => onEdit(record)}
                          className="p-1.5 text-blue-600 hover:bg-blue-100 rounded transition"
                          title="Edit"
                        >
                          <Edit size={16} />
                        </button>
                      )}
                      {config.deletable && (
                        <button
                          onClick={() => onDelete(record)}
                          className="p-1.5 text-red-600 hover:bg-red-100 rounded transition"
                          title="Delete"
                        >
                          <Trash2 size={16} />
                        </button>
                      )}
                    </div>
                  </td>
                )}
              </tr>
            ))
          )}
        </tbody>
      </table>

      {/* Bulk Edit Bar */}
      {selectedRows.size > 0 && onBulkEdit && (
        <div className="px-4 py-3 bg-blue-50 border-t border-gray-200 flex items-center justify-between">
          <span className="text-sm font-medium text-gray-700">
            {selectedRows.size} row{selectedRows.size !== 1 ? 's' : ''} selected
          </span>
          <button
            onClick={() => onBulkEdit(Array.from(selectedRows))}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition text-sm"
          >
            Edit Selected
          </button>
        </div>
      )}
    </div>
  );
}

