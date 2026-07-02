<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0"/>
    @if(!empty($pluginImportMap['imports']))
        <script type="importmap">@json($pluginImportMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endif
    <script>
        window.__APP_PLUGIN_RUNTIME__ = @json($pluginRuntimeConfig ?? ['plugins' => []], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    </script>
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
