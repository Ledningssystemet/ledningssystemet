import React from 'react';
import { Edit, Trash2 } from 'lucide-react';
import { CrudRecord, ColumnDef, CrudTableConfig } from '@/types/crud';

interface MasterDetailViewProps {
  data: CrudRecord[];
  columns: ColumnDef[];
  config: CrudTableConfig;
  selectedId?: string | number | null;
  onSelect: (id: string | number | null) => void;
  onEdit: (record: CrudRecord) => void;
  onDelete: (record: CrudRecord) => void;
}

export function MasterDetailView({
  data,
  columns,
  config,
  selectedId = null,
  onSelect,
  onEdit,
  onDelete,
}: MasterDetailViewProps) {
  const selectedRecord = data.find(r => r.id === selectedId);
  const visibleColumns = columns.filter(col => !col.hidden);
  const summaryColumns = visibleColumns.slice(0, 2);

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 h-[600px]">
      {/* Master List */}
      <div className="md:col-span-1 border border-gray-200 rounded-lg overflow-hidden flex flex-col">
        <div className="flex-1 overflow-y-auto">
          {data.length === 0 ? (
            <div className="p-4 text-center text-gray-500">No data found</div>
          ) : (
            <div className="divide-y divide-gray-200">
              {data.map(record => (
                <button
                  key={record.id}
                  onClick={() => onSelect(selectedId === record.id ? null : record.id)}
                  className={`w-full text-left px-4 py-3 transition ${
                    selectedId === record.id
                      ? 'bg-blue-50 border-l-4 border-blue-600'
                      : 'hover:bg-gray-50'
                  }`}
                >
                  <p className="font-medium text-sm text-gray-900 truncate">
                    {summaryColumns
                      .map(col => {
                        const value = record[col.key];
                        return col.format ? col.format(value) : value;
                      })
                      .filter(Boolean)
                      .join(' - ')}
                  </p>
                </button>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Detail View */}
      <div className="md:col-span-2 border border-gray-200 rounded-lg overflow-hidden flex flex-col">
        {selectedRecord ? (
          <>
            <div className="flex-1 overflow-y-auto p-6">
              <div className="space-y-6">
                {visibleColumns.map(column => (
                  <div key={column.key}>
                    <p className="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                      {column.label}
                    </p>
                    <p className="mt-2 text-base text-gray-900">
                      {column.render
                        ? column.render(selectedRecord)
                        : column.format
                        ? column.format(selectedRecord[column.key])
                        : selectedRecord[column.key] || '-'}
                    </p>
                  </div>
                ))}
              </div>
            </div>

            {/* Actions */}
            {(config.editable || config.deletable) && (
              <div className="border-t border-gray-200 px-6 py-4 flex gap-2 bg-gray-50">
                {config.editable && (
                  <button
                    onClick={() => onEdit(selectedRecord)}
                    className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition"
                  >
                    <Edit size={18} />
                    Edit
                  </button>
                )}
                {config.deletable && (
                  <button
                    onClick={() => onDelete(selectedRecord)}
                    className="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition"
                  >
                    <Trash2 size={18} />
                    Delete
                  </button>
                )}
              </div>
            )}
          </>
        ) : (
          <div className="flex items-center justify-center h-full text-gray-500">
            Select a record to view details
          </div>
        )}
      </div>
    </div>
  );
}

