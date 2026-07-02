<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ __('api.documentation.page_title') }}</title>
    @viteReactRefresh
    @vite(['resources/js/swagger-docs.ts'])
    <style>
        body {
            margin: 0;
            background: #f8fafc;
            color: #0f172a;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .api-docs-shell {
            max-width: 1280px;
            margin: 0 auto;
            padding: {{ $embedded ? '0' : '24px' }};
        }

        .api-docs-header {
            display: {{ $embedded ? 'none' : 'block' }};
            margin-bottom: 16px;
        }

        .api-docs-header h1 {
            margin: 0 0 8px;
            font-size: 1.875rem;
            line-height: 2.25rem;
        }

        .api-docs-header p {
            margin: 0 0 8px;
            color: #475569;
        }

        .api-docs-header a {
            color: #2563eb;
            text-decoration: none;
        }

        .api-docs-header a:hover {
            text-decoration: underline;
        }

        .swagger-ui .topbar {
            display: {{ $embedded ? 'none' : 'flex' }};
        }
    </style>
</head>
<body>
    <div class="api-docs-shell">
        <header class="api-docs-header">
            <h1>{{ __('api.documentation.page_title') }}</h1>
            <p>{{ __('api.documentation.page_description') }}</p>
            <p><a href="{{ $specUrl }}">{{ __('api.documentation.spec_link_label') }}</a></p>
        </header>

        <div id="swagger-ui" data-spec-url="{{ $specUrl }}"></div>
    </div>
</body>
</html>

