<?php

namespace App\Plugins;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Throwable;

class PluginRuntime
{
    /**
     * @var array<string, PluginManifest>|null
     */
    private ?array $manifests = null;

    /**
     * @var array<string, Plugin>
     */
    private array $plugins = [];

    private bool $backendRegistered = false;

    private bool $backendBooted = false;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly Application $app,
        private readonly PluginHookRegistry $hooks,
        private readonly array $config,
    ) {
    }

    /**
     * @return array<int, PluginManifest>
     */
    public function manifests(): array
    {
        if ($this->manifests === null) {
            $this->manifests = $this->discoverManifests();
        }

        return array_values($this->manifests);
    }

    public function manifest(string $pluginId): ?PluginManifest
    {
        if ($this->manifests === null) {
            $this->manifests = $this->discoverManifests();
        }

        return $this->manifests[$pluginId] ?? null;
    }

    public function registerBackendPlugins(): void
    {
        if ($this->backendRegistered) {
            return;
        }

        foreach ($this->manifests() as $manifest) {
            foreach ($manifest->backendProviders as $providerPath) {
                $this->registerServiceProvider($manifest, $providerPath);
            }

            if ($manifest->backendEntry !== null) {
                $plugin = $this->resolvePluginInstance($manifest, $manifest->backendEntry);

                if ($plugin !== null) {
                    $plugin->register($this->app);
                    $this->plugins[$manifest->id] = $plugin;
                }
            }
        }

        $this->backendRegistered = true;
    }

    public function bootBackendPlugins(): void
    {
        if ($this->backendBooted) {
            return;
        }

        $this->registerBackendPlugins();

        foreach ($this->plugins as $pluginId => $plugin) {
            try {
                $plugin->boot($this->app);
                $this->hooks->doAction('plugins.booted', $pluginId, $plugin, $this->app);
            } catch (Throwable $exception) {
                Log::warning('Failed to boot plugin.', [
                    'plugin' => $pluginId,
                    'exception' => $exception,
                ]);
            }
        }

        $this->backendBooted = true;
    }

    public function handleRequest(Request $request, Closure $destination): mixed
    {
        $this->bootBackendPlugins();

        $pipeline = array_reduce(
            array_reverse($this->plugins),
            fn (Closure $next, Plugin $plugin): Closure => fn (Request $currentRequest): mixed => $plugin->handleRequest($currentRequest, $next),
            $destination,
        );

        return $pipeline($request);
    }

    /**
     * @param  array<string, mixed>  $shared
     * @return array<string, mixed>
     */
    public function extendInertiaShared(array $shared, Request $request): array
    {
        $this->bootBackendPlugins();

        $shared = $this->hooks->applyFilters('inertia.shared', $shared, $request, $this->app);

        foreach ($this->plugins as $plugin) {
            $shared = $plugin->extendInertiaShared($shared, $request);
        }

        return $shared;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<int, array<string, mixed>>
     */
    public function extendMenu(array $categories, ?Request $request = null): array
    {
        if ($request !== null && $request->user() === null) {
            return $categories;
        }

        $this->bootBackendPlugins();

        $categories = $this->hooks->applyFilters('navigation.menu.categories', $categories, $request, $this->app);
        $categories = $this->mergeManifestNavigation($categories);

        foreach ($this->plugins as $plugin) {
            $categories = $plugin->extendMenu($categories, $request);
        }

        return $categories;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function frontendImportMap(): array
    {
        $imports = [];

        foreach ($this->manifests() as $manifest) {
            if (! $manifest->hasFrontendEntry()) {
                continue;
            }

            $imports[$manifest->frontendSpecifier()] = route('plugins.asset', [
                'plugin' => $manifest->id,
                'path' => $manifest->frontendEntry,
            ]);
        }

        return [
            'imports' => $imports,
        ];
    }

    /**
     * @return array{plugins: array<int, array<string, mixed>>}
     */
    public function frontendRuntimeConfig(?Request $request = null): array
    {
        $this->bootBackendPlugins();

        $plugins = [];

        foreach ($this->manifests() as $manifest) {
            if (! $manifest->hasFrontendEntry()) {
                continue;
            }

            $plugins[] = [
                'id' => $manifest->id,
                'name' => $manifest->name,
                'version' => $manifest->version,
                'specifier' => $manifest->frontendSpecifier(),
                'context' => ($this->plugins[$manifest->id] ?? null)?->frontendConfig($request) ?? [],
                'meta' => $manifest->meta ?? [],
            ];
        }

        return [
            'plugins' => $plugins,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @return array<int, array<string, mixed>>
     */
    private function mergeManifestNavigation(array $categories): array
    {
        $seenKeys = [];
        $seenTargets = [];

        foreach ($categories as $category) {
            foreach (($category['columns'] ?? []) as $column) {
                foreach (($column['items'] ?? []) as $item) {
                    if (is_string($item['key'] ?? null)) {
                        $seenKeys[$item['key']] = true;
                    }

                    $target = $this->navigationItemTarget(is_array($item) ? $item : []);

                    if ($target !== null) {
                        $seenTargets[$target] = true;
                    }
                }
            }
        }

        foreach ($this->manifests() as $manifest) {
            foreach ($manifest->navigationCategories as $category) {
                $normalizedCategory = $this->filterNavigationCategoryItems($category, $seenKeys, $seenTargets);

                if ($normalizedCategory === null) {
                    continue;
                }

                $categories = $this->mergeNavigationCategory($categories, $normalizedCategory);
            }
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $category
     * @param  array<string, bool>  $seenKeys
     * @param  array<string, bool>  $seenTargets
     * @return array<string, mixed>|null
     */
    private function filterNavigationCategoryItems(array $category, array &$seenKeys, array &$seenTargets): ?array
    {
        $columns = [];

        foreach (($category['columns'] ?? []) as $column) {
            if (! is_array($column)) {
                continue;
            }

            $items = [];

            foreach (($column['items'] ?? []) as $item) {
                if (! is_array($item) || ! is_string($item['key'] ?? null)) {
                    continue;
                }

                $target = $this->navigationItemTarget($item);

                if (isset($seenKeys[$item['key']]) || ($target !== null && isset($seenTargets[$target]))) {
                    continue;
                }

                $seenKeys[$item['key']] = true;

                if ($target !== null) {
                    $seenTargets[$target] = true;
                }

                $items[] = $item;
            }

            if ($items === []) {
                continue;
            }

            $normalizedColumn = $column;
            $normalizedColumn['items'] = $items;
            $columns[] = $normalizedColumn;
        }

        if ($columns === []) {
            return null;
        }

        $normalizedCategory = $category;
        $normalizedCategory['columns'] = $columns;

        return $normalizedCategory;
    }

    /**
     * @param  array<int, array<string, mixed>>  $categories
     * @param  array<string, mixed>  $category
     * @return array<int, array<string, mixed>>
     */
    private function mergeNavigationCategory(array $categories, array $category): array
    {
        foreach ($categories as $index => $existingCategory) {
            if (($existingCategory['label'] ?? null) !== ($category['label'] ?? null)) {
                continue;
            }

            $existingColumns = is_array($existingCategory['columns'] ?? null) ? $existingCategory['columns'] : [];

            foreach ($category['columns'] as $incomingColumn) {
                $columnMerged = false;

                foreach ($existingColumns as $columnIndex => $existingColumn) {
                    if (($existingColumn['heading'] ?? null) !== ($incomingColumn['heading'] ?? null)) {
                        continue;
                    }

                    $existingColumns[$columnIndex]['items'] = [
                        ...(is_array($existingColumn['items'] ?? null) ? $existingColumn['items'] : []),
                        ...(is_array($incomingColumn['items'] ?? null) ? $incomingColumn['items'] : []),
                    ];
                    $columnMerged = true;
                    break;
                }

                if (! $columnMerged) {
                    $existingColumns[] = $incomingColumn;
                }
            }

            $categories[$index]['columns'] = $existingColumns;

            if (! isset($categories[$index]['categoryIcon']) && isset($category['categoryIcon'])) {
                $categories[$index]['categoryIcon'] = $category['categoryIcon'];
            }

            return $categories;
        }

        $categories[] = $category;

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function navigationItemTarget(array $item): ?string
    {
        $href = is_string($item['href'] ?? null) ? trim($item['href']) : null;

        if ($href !== null && preg_match('/^(https?:)?\/\//', $href) === 1) {
            return $href;
        }

        $target = $href;

        if (($target === null || $target === '') && is_string($item['key'] ?? null) && $item['key'] !== '') {
            $target = '/'.$item['key'];
        }

        if ($target === null || $target === '') {
            return null;
        }

        if (str_starts_with($target, '/app')) {
            $target = substr($target, 4) ?: '/';
        }

        return str_starts_with($target, '/') ? $target : '/'.$target;
    }

    /**
     * @return array<string, PluginManifest>
     */
    private function discoverManifests(): array
    {
        if (! (bool) ($this->config['enabled'] ?? true)) {
            return [];
        }

        $manifests = [];

        foreach ((array) ($this->config['paths'] ?? []) as $pluginRoot) {
            if (! is_string($pluginRoot) || trim($pluginRoot) === '' || ! is_dir($pluginRoot)) {
                continue;
            }

            foreach (File::directories($pluginRoot) as $pluginDirectory) {
                $manifestPath = rtrim($pluginDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'plugin.json';

                if (! is_file($manifestPath)) {
                    continue;
                }

                try {
                    $decoded = json_decode((string) File::get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable $exception) {
                    Log::warning('Failed to parse plugin manifest.', [
                        'manifest' => $manifestPath,
                        'exception' => $exception,
                    ]);

                    continue;
                }

                $manifest = PluginManifest::fromArray(is_array($decoded) ? $decoded : [], $pluginDirectory);

                if ($manifest === null) {
                    Log::warning('Skipping invalid plugin manifest.', ['manifest' => $manifestPath]);
                    continue;
                }

                $manifests[$manifest->id] = $manifest;
            }
        }

        ksort($manifests);

        return $manifests;
    }

    private function registerServiceProvider(PluginManifest $manifest, string $relativePath): void
    {
        try {
            $provider = $this->requirePluginFile($manifest, $relativePath);
            $resolvedProvider = $this->resolveServiceProvider($provider, $manifest, $relativePath);

            if ($resolvedProvider !== null) {
                $this->app->register($resolvedProvider);
            }
        } catch (Throwable $exception) {
            Log::warning('Failed to register plugin service provider.', [
                'plugin' => $manifest->id,
                'provider' => $relativePath,
                'exception' => $exception,
            ]);
        }
    }

    private function resolvePluginInstance(PluginManifest $manifest, string $relativePath): ?Plugin
    {
        try {
            $plugin = $this->requirePluginFile($manifest, $relativePath);

            if ($plugin instanceof Closure) {
                $plugin = $plugin($this->app, $manifest, $this->hooks);
            }

            if ($plugin instanceof Plugin) {
                return $plugin;
            }

            Log::warning('Plugin backend entry did not return a valid plugin instance.', [
                'plugin' => $manifest->id,
                'entry' => $relativePath,
                'returned_type' => get_debug_type($plugin),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to load plugin backend entry.', [
                'plugin' => $manifest->id,
                'entry' => $relativePath,
                'exception' => $exception,
            ]);
        }

        return null;
    }

    private function resolveServiceProvider(mixed $provider, PluginManifest $manifest, string $relativePath): ServiceProvider|string|null
    {
        if ($provider instanceof Closure) {
            $provider = $provider($this->app, $manifest, $this->hooks);
        }

        if ($provider instanceof ServiceProvider) {
            return $provider;
        }

        if (is_string($provider) && is_subclass_of($provider, ServiceProvider::class)) {
            return $provider;
        }

        Log::warning('Plugin provider file did not return a valid service provider.', [
            'plugin' => $manifest->id,
            'provider' => $relativePath,
            'returned_type' => get_debug_type($provider),
        ]);

        return null;
    }

    private function requirePluginFile(PluginManifest $manifest, string $relativePath): mixed
    {
        $absolutePath = $manifest->absolutePath($relativePath);

        if (! is_file($absolutePath)) {
            throw new \RuntimeException(sprintf('Plugin file [%s] does not exist.', $absolutePath));
        }

        return require $absolutePath;
    }
}

