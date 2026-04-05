import React from 'react';
import { Modal } from './Modal';
import { AlertCircle } from 'lucide-react';

interface DeleteConfirmProps {
  isOpen: boolean;
  title?: string;
  message?: string;
  onClose: () => void;
  onConfirm: () => Promise<void>;
  loading?: boolean;
}

export function DeleteConfirm({
  isOpen,
  title = 'Delete Record',
  message = 'Are you sure you want to delete this record? This action cannot be undone.',
  onClose,
  onConfirm,
  loading = false,
}: DeleteConfirmProps) {
  const [isLoading, setIsLoading] = React.useState(false);

  const handleConfirm = async () => {
    setIsLoading(true);
    try {
      await onConfirm();
      onClose();
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <Modal
      isOpen={isOpen}
      title={title}
      onClose={onClose}
      size="sm"
      footer={
        <>
          <button
            onClick={onClose}
            disabled={isLoading || loading}
            className="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition disabled:opacity-50"
          >
            Cancel
          </button>
          <button
            onClick={handleConfirm}
            disabled={isLoading || loading}
            className="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition disabled:opacity-50"
          >
            {isLoading || loading ? 'Deleting...' : 'Delete'}
          </button>
        </>
      }
    >
      <div className="flex gap-3 pt-2">
        <AlertCircle size={24} className="text-red-600 flex-shrink-0" />
        <p className="text-gray-700">{message}</p>
      </div>
    </Modal>
  );
}

