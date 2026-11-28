import React from 'react';
import { warningClass } from './authStyles.jsx';

export function AlertList({ errors = [], status = '' }) {
    return (
        <>
            {errors.length > 0 && (
                <div className={warningClass} role="alert">
                    <ul className="m-0 list-none space-y-1 p-0">
                        {errors.map((error, idx) => <li key={idx}>{error}</li>)}
                    </ul>
                </div>
            )}
            {status && (
                <div className="mb-4 rounded-md border bg-badge-info px-3 py-2 text-sm text-badge-info-foreground" role="status">
                    {status}
                </div>
            )}
        </>
    );
}

