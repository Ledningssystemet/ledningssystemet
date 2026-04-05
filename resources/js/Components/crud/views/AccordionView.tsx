import React from 'react';
import { Edit, Trash2, ChevronUp, ChevronDown } from 'lucide-react';
import { CrudRecord, ColumnDef, CrudTableConfig } from '@/types/crud';

interface AccordionViewProps {
  data: CrudRecord[];
  columns: ColumnDef[];
  config: CrudTableConfig;
  expandedId?: string | number | null;
  onExpand: (id: string | number | null) => void;
  onEdit: (record: CrudRecord) => void;
  onDelete: (record: CrudRecord) => void;
}

export function AccordionView({
  data,
  columns,
  config,
  expandedId = null,
  onExpand,
  onEdit,
  onDelete,
}: AccordionViewProps) {
  const visibleColumns = columns.filter(col => !col.hidden);

  return (
    <div className="space-y-2">
      {data.length === 0 ? (
        <div className="text-center py-8 text-gray-500">
          No data found
        </div>
      ) : (
        data.map(record => {
          const isExpanded = expandedId === record.id;

          return (
            <div
              key={record.id}
              className="border border-gray-200 rounded-lg overflow-hidden hover:border-gray-300 transition"
            >
              {/* Header / Summary */}
              <button
                onClick={() => onExpand(isExpanded ? null : record.id)}
                className="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-50 transition"
              >
                <div className="flex items-center gap-3 flex-1 min-w-0">
                  {isExpanded ? (
                    <ChevronUp size={18} className="text-gray-500 flex-shrink-0" />
                  ) : (
                    <ChevronDown size={18} className="text-gray-500 flex-shrink-0" />
                  )}
                  <div className="text-left min-w-0">
                    <p className="font-medium text-gray-900 truncate">
                      {visibleColumns
                        .slice(0, 2)
                        .map(col => {
                          const value = record[col.key];
                          return col.format ? col.format(value) : value;
                        })
                        .filter(Boolean)
                        .join(' - ')}
                    </p>
                  </div>
                </div>
              </button>

              {/* Details / Body */}
              {isExpanded && (
                <div className="border-t border-gray-200 px-4 py-3 bg-gray-50">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    {visibleColumns.slice(1).map(column => {
                      const value = record[column.key];
                      return (
                        <div key={column.key}>
                          <p className="text-xs font-semibold text-gray-600 uppercase">
                            {column.label}
                          </p>
                          <p className="text-sm text-gray-900 mt-1">
                            {column.render
                              ? column.render(record)
                              : column.format ? column.format(value) : value || '-'}
                          </p>
                        </div>
                      );
                    })}
                  </div>

                  {/* Actions */}
                  {(config.editable || config.deletable) && (
                    <div className="flex gap-2 pt-3 border-t border-gray-200">
                      {config.editable && (
                        <button
                          onClick={() => onEdit(record)}
                          className="inline-flex items-center gap-2 px-3 py-2 text-sm bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 transition"
                        >
                          <Edit size={16} />
                          Edit
                        </button>
                      )}
                      {config.deletable && (
                        <button
                          onClick={() => onDelete(record)}
                          className="inline-flex items-center gap-2 px-3 py-2 text-sm bg-red-50 text-red-700 rounded-lg hover:bg-red-100 transition"
                        >
                          <Trash2 size={16} />
                          Delete
                        </button>
                      )}
                      {config.actions?.custom && (
                        config.actions.custom.map((action, idx) => (
                          <button
                            key={idx}
                            onClick={() => action.onClick(record)}
                            className="inline-flex items-center gap-2 px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
                          >
                            {action.icon}
                            {action.label}
                          </button>
                        ))
                      )}
                    </div>
                  )}
                </div>
              )}
            </div>
          );
        })
      )}
    </div>
  );
}

