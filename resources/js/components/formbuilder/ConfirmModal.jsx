import React from 'react';
import { t } from './t.js';

export function ConfirmModal({ open, title, message, warning = false, onConfirm, onClose }) {
    if (!open) return null;
    return (
        <div className="modal show d-block" tabIndex="-1" style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
            <div className="modal-dialog">
                <div className="modal-content">
                    <div className={`modal-header${warning ? ' bg-warning-subtle' : ''}`}>
                        <h5 className="modal-title">{title}</h5>
                        <button type="button" className="btn-close" onClick={onClose}></button>
                    </div>
                    <div className="modal-body">{message}</div>
                    <div className="modal-footer">
                        <button type="button" className="btn btn-secondary" onClick={onClose}>{t('Cancel')}</button>
                        <button
                            type="button"
                            className={`btn ${warning ? 'btn-danger' : 'btn-primary'}`}
                            onClick={onConfirm}
                        >
                            {t('Confirm')}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
