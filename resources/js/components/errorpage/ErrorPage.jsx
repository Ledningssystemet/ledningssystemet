import React from 'react';
import './errorpage.css';
import '../../../css/utilities.css';

export default function ErrorPage({ errorType, title, text, homeLabel, retryLabel }) {
    return <section className="ls-error-page" role="alert">
        <div className="ls-error-page__content">
            <svg className="ls-error-page__illustration" viewBox="0 0 360 200" aria-hidden="true">
                {errorType === 'pageNotFound' ? <>
                <text className="ls-error-page__number" x="180" y="104" textAnchor="middle">
                    404
                </text>
                <ellipse cx="184" cy="174" rx="125" ry="12" fill="#e5ecea" />
                <path d="m122 79 48-15 47 15 48-15 11 91-50 13-47-15-48 15z"
                    fill="#f8fbf8" stroke="#174b52" strokeWidth="2.5" strokeLinejoin="round" />
                <path d="m170 64-1 89m48-74 8 89m-47-15 47-13 50 15"
                    fill="none" stroke="#9cc8c5" strokeWidth="2" />
                <path d="M151 128c20 17 42 13 53-6 14-24 30-28 48-12"
                    fill="none" stroke="#f59a46" strokeWidth="3" strokeDasharray="6 7" strokeLinecap="round" />
                <circle cx="254" cy="110" r="5" fill="#f59a46" />
                <text x="267" y="126" fill="#f28b35" fontSize="39" fontWeight="700">?</text>
                <circle cx="112" cy="119" r="43" fill="#62c5bd" stroke="#174b52" strokeWidth="3" />
                <circle cx="112" cy="119" r="34" fill="#fbfcf9" stroke="#174b52" strokeWidth="2" />
                <path d="m106 124 23-25-10 31-23 13z" fill="#ef8a38" stroke="#174b52" strokeWidth="1.5" />
                <path d="m106 124 13 6-23 13z" fill="#89c8c3" stroke="#174b52" strokeWidth="1.5" />
                <circle cx="112" cy="119" r="4" fill="#174b52" />
                <path d="M112 87v7m0 50v7m-32-32h7m50 0h7" stroke="#174b52" strokeWidth="2" strokeLinecap="round" />
                <path d="M105 76v-9a7 7 0 0 1 14 0v9" fill="#62c5bd" stroke="#174b52" strokeWidth="3" />
                <circle cx="112" cy="62" r="15" fill="none" stroke="#174b52" strokeWidth="3" />
                <path d="m292 45 1-13m10 17 9-9m-4 18 12-3" stroke="#f28b35" strokeWidth="3" strokeLinecap="round" />
                </> : <>
                    <ellipse cx="180" cy="174" rx="100" ry="12" fill="#e5ecea" />
                    <path d="M168 38a14 14 0 0 1 24 0l67 112a14 14 0 0 1-12 21H113a14 14 0 0 1-12-21z"
                        fill="#e7f3f1" stroke="#174b52" strokeWidth="3" strokeLinejoin="round" />
                    <path d="M180 77v42" stroke="#f28b35" strokeWidth="9" strokeLinecap="round" />
                    <circle cx="180" cy="140" r="5" fill="#f28b35" />
                </>}
            </svg>
            <h1 className="ls-error-page__title">{title}</h1>
            <p className="ls-error-page__message">{text}</p>
            <div className="ls-error-page__actions">
                <a className="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-foreground no-underline transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 border bg-card hover:bg-surface-hover" href="/">
                    {homeLabel} <span aria-hidden="true">→</span>
                </a>
                <button className="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 border bg-card hover:bg-surface-hover" type="button" onClick={() => window.location.reload()}>
                    {retryLabel}
                </button>
            </div>
        </div>
    </section>;
}
