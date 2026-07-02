<?php

$configuredPaths = env('PLUGIN_PATHS', base_path('plugins'));

$paths = array_values(array_filter(array_map(
    static fn (string $path): string => trim($path),
    explode(PATH_SEPARATOR, (string) $configuredPaths),
)));

return [
    'enabled' => env('PLUGINS_ENABLED', true),
    'paths' => $paths,
    'asset_route_prefix' => trim((string) env('PLUGIN_ASSET_ROUTE_PREFIX', 'plugin-assets'), '/'),
];

