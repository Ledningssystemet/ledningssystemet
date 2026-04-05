import React, { useState } from 'react';
import { CrudRecord, ColumnDef, CrudTableConfig } from '@/types/crud';
import { Modal } from './Modal';
import { AlertCircle } from 'lucide-react';

interface CreateModalProps {
  isOpen: boolean;
  columns: ColumnDef[];
  config: CrudTableConfig;
  onClose: () => void;
  onSave: (data: Omit<CrudRecord, 'id'>) => Promise<void>;
}

export function CreateModal({ isOpen, columns, config, onClose, onSave }: CreateModalProps) {
  const [formData, setFormData] = useState<Record<string, any>>({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  React.useEffect(() => {
    if (isOpen) {
      setFormData({});
      setError(null);
    }
  }, [isOpen]);

  const editableColumns = columns.filter(col => col.editable !== false && col.key !== 'id');

  const handleChange = (key: string, value: any) => {
    setFormData(prev => ({
      ...prev,
      [key]: value,
    }));
    setError(null);
  };

  const handleSave = async () => {
    setLoading(true);
    setError(null);

    try {
      await onSave(formData);
      onClose();
    } catch (err: any) {
      if (err.response?.data?.errors) {
        const errorMessages = Object.values(err.response.data.errors)
          .flat()
          .join(', ');
        setError(errorMessages);
      } else {
        setError(err.response?.data?.message || 'An error occurred while creating');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      title={`Create New ${config.title || 'Record'}`}
      onClose={onClose}
      size="lg"
      footer={
        <>
          <button
            onClick={onClose}
            disabled={loading}
            className="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            onClick={handleSave}
            disabled={loading}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition disabled:opacity-50"
          >
            {loading ? 'Creating...' : 'Create'}
          </button>
        </>
      }
    >
      {error && (
        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex gap-2">
          <AlertCircle size={20} className="text-red-600 flex-shrink-0" />
          <p className="text-sm text-red-700">{error}</p>
        </div>
      )}

      <div className="space-y-4">
        {editableColumns.map(column => (
          <div key={column.key}>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              {column.label}
            </label>

            {column.type === 'boolean' ? (
              <input
                type="checkbox"
                checked={Boolean(formData[column.key])}
                onChange={e => handleChange(column.key, e.target.checked)}
                className="h-4 w-4 text-blue-600 rounded"
                disabled={loading}
              />
            ) : column.type === 'number' ? (
              <input
                type="number"
                value={formData[column.key] || ''}
                onChange={e => handleChange(column.key, e.target.value ? Number(e.target.value) : '')}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                disabled={loading}
              />
            ) : column.type === 'date' ? (
              <input
                type="date"
                value={formData[column.key] || ''}
                onChange={e => handleChange(column.key, e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                disabled={loading}
              />
            ) : column.type === 'datetime' ? (
              <input
                type="datetime-local"
                value={formData[column.key] || ''}
                onChange={e => handleChange(column.key, e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                disabled={loading}
              />
            ) : (
              <textarea
                value={formData[column.key] || ''}
                onChange={e => handleChange(column.key, e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                rows={2}
                disabled={loading}
              />
            )}
          </div>
        ))}
      </div>
    </Modal>
  );
}

