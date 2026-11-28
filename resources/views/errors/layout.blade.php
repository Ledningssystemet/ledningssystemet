<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title')</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            body {
                margin: 0;
                background: #f7fafc;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                line-height: 1.5;
            }
            .error-page {
                display: flex;
                min-height: 100vh;
                align-items: center;
                justify-content: center;
                padding: 3rem 1.5rem;
                color: #26343d;
                text-align: center;
            }
            .error-page__content { width: min(100%, 620px); }
            .error-page__illustration { display: block; width: min(100%, 360px); height: auto; margin: 0 auto 1rem; }
            .error-page__number { fill: #e5eaea; font-size: 112px; font-weight: 700; letter-spacing: -7px; }
            .error-page__title { margin: 0 0 .6rem; color: #26343d; font-size: clamp(1.7rem, 4vw, 2.25rem); font-weight: 700; }
            .error-page__message { margin: 0 auto 1.5rem; color: #596c77; font-size: 1.05rem; overflow-wrap: anywhere; }
            .error-page__actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .75rem; }
            .error-page__button {
                display: inline-flex;
                align-items: center;
                gap: .375rem;
                padding: .375rem .75rem;
                border: 1px solid #e2e8f0;
                border-radius: .375rem;
                background: #fff;
                color: #162437;
                font-size: .875rem;
                font-weight: 500;
                text-decoration: none;
                transition: background-color .15s, color .15s;
            }
            .error-page__button:hover { background: #f7fafc; }
            .error-page__button:focus-visible { outline: 2px solid #0f304d; outline-offset: 2px; }
            @media (max-width: 480px) {
                .error-page { padding: 2rem 1rem; }
                .error-page__illustration { width: min(100%, 310px); }
                .error-page__actions { flex-direction: column; }
            }
        </style>
    </head>
    <body>
        <main class="error-page" role="alert">
            <div class="error-page__content">
                <svg class="error-page__illustration" viewBox="0 0 360 200" aria-hidden="true">
                    @if (trim($__env->yieldContent('code')) === '404')
                        <text class="error-page__number" x="180" y="104" text-anchor="middle">404</text>
                        <ellipse cx="184" cy="174" rx="125" ry="12" fill="#e5ecea" />
                        <path d="m122 79 48-15 47 15 48-15 11 91-50 13-47-15-48 15z" fill="#f8fbf8" stroke="#174b52" stroke-width="2.5" stroke-linejoin="round" />
                        <path d="m170 64-1 89m48-74 8 89m-47-15 47-13 50 15" fill="none" stroke="#9cc8c5" stroke-width="2" />
                        <path d="M151 128c20 17 42 13 53-6 14-24 30-28 48-12" fill="none" stroke="#f59a46" stroke-width="3" stroke-dasharray="6 7" stroke-linecap="round" />
                        <circle cx="254" cy="110" r="5" fill="#f59a46" />
                        <text x="267" y="126" fill="#f28b35" font-size="39" font-weight="700">?</text>
                        <circle cx="112" cy="119" r="43" fill="#62c5bd" stroke="#174b52" stroke-width="3" />
                        <circle cx="112" cy="119" r="34" fill="#fbfcf9" stroke="#174b52" stroke-width="2" />
                        <path d="m106 124 23-25-10 31-23 13z" fill="#ef8a38" stroke="#174b52" stroke-width="1.5" />
                        <path d="m106 124 13 6-23 13z" fill="#89c8c3" stroke="#174b52" stroke-width="1.5" />
                        <circle cx="112" cy="119" r="4" fill="#174b52" />
                        <path d="M112 87v7m0 50v7m-32-32h7m50 0h7" stroke="#174b52" stroke-width="2" stroke-linecap="round" />
                        <path d="M105 76v-9a7 7 0 0 1 14 0v9" fill="#62c5bd" stroke="#174b52" stroke-width="3" />
                        <circle cx="112" cy="62" r="15" fill="none" stroke="#174b52" stroke-width="3" />
                        <path d="m292 45 1-13m10 17 9-9m-4 18 12-3" stroke="#f28b35" stroke-width="3" stroke-linecap="round" />
                    @else
                        <ellipse cx="180" cy="174" rx="100" ry="12" fill="#e5ecea" />
                        <path d="M168 38a14 14 0 0 1 24 0l67 112a14 14 0 0 1-12 21H113a14 14 0 0 1-12-21z" fill="#e7f3f1" stroke="#174b52" stroke-width="3" stroke-linejoin="round" />
                        <path d="M180 77v42" stroke="#f28b35" stroke-width="9" stroke-linecap="round" />
                        <circle cx="180" cy="140" r="5" fill="#f28b35" />
                    @endif
                </svg>
                <h1 class="error-page__title">@yield('message')</h1>
                @hasSection('code')
                    <p class="error-page__message">@yield('code')</p>
                @endif
                <div class="error-page__actions">
                    <a class="error-page__button" href="{{ url('/') }}">{{ __('Go to homepage') }} <span aria-hidden="true">→</span></a>
                    <a class="error-page__button" href="">{{ __('Retry') }}</a>
                </div>
            </div>
        </main>
    </body>
</html>
