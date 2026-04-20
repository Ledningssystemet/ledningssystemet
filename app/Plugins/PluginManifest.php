<?php

namespace App\Plugins;

final class PluginManifest
{
    /**
     * @param  array<int, string>  $backendProviders
     * @param  array<int, array<string, mixed>>  $navigationCategories
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $basePath,
        public readonly ?string $backendEntry,
        public readonly array $backendProviders,
        public readonly ?string $frontendEntry,
        public readonly array $navigationCategories,
        public readonly ?array $meta = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $basePath): ?self
    {
        $id = self::normalizePluginId($data['id'] ?? null);

        if ($id === null) {
            return null;
        }

        $backend = is_array($data['backend'] ?? null) ? $data['backend'] : [];
        $frontend = is_array($data['frontend'] ?? null) ? $data['frontend'] : [];
        $navigation = is_array($data['navigation'] ?? null) ? $data['navigation'] : [];

        $backendProviders = array_values(array_filter(array_map(
            static fn (mixed $path): ?string => self::normalizeRelativePath($path),
            is_array($backend['providers'] ?? null) ? $backend['providers'] : [],
        )));

        return new self(
            id: $id,
            name: (string) ($data['name'] ?? $id),
            version: (string) ($data['version'] ?? '0.0.0'),
            basePath: $basePath,
            backendEntry: self::normalizeRelativePath($backend['entry'] ?? null),
            backendProviders: $backendProviders,
            frontendEntry: self::normalizeRelativePath($frontend['entry'] ?? null),
            navigationCategories: self::normalizeNavigationCategories($navigation),
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $navigation
     * @return array<int, array<string, mixed>>
     */
    private static function normalizeNavigationCategories(array $navigation): array
    {
        $categories = [];

        foreach (is_array($navigation['categories'] ?? null) ? $navigation['categories'] : [] as $category) {
            if (! is_array($category)) {
                continue;
            }

            $label = self::normalizeText($category['label'] ?? null);
            $columns = [];

            foreach (is_array($category['columns'] ?? null) ? $category['columns'] : [] as $column) {
                if (! is_array($column)) {
                    continue;
                }

                $items = [];

                foreach (is_array($column['items'] ?? null) ? $column['items'] : [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $key = self::normalizeText($item['key'] ?? null);
                    $itemLabel = self::normalizeText($item['label'] ?? null);
                    $icon = self::normalizeText($item['icon'] ?? null);

                    if ($key === null || $itemLabel === null || $icon === null) {
                        continue;
                    }

                    $items[] = array_filter([
                        'key' => $key,
                        'label' => $itemLabel,
                        'icon' => $icon,
                        'description' => self::normalizeText($item['description'] ?? null),
                        'href' => self::normalizeHref($item['href'] ?? null),
                    ], static fn (mixed $value): bool => $value !== null);
                }

                if ($items === []) {
                    continue;
                }

                $columns[] = array_filter([
                    'heading' => self::normalizeText($column['heading'] ?? null),
                    'items' => $items,
                ], static fn (mixed $value): bool => $value !== null);
            }

            if ($label === null || $columns === []) {
                continue;
            }

            $categories[] = array_filter([
                'label' => $label,
                'categoryIcon' => self::normalizeText($category['categoryIcon'] ?? null),
                'columns' => $columns,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return $categories;
    }

    public function hasFrontendEntry(): bool
    {
        return $this->frontendEntry !== null;
    }

    public function frontendSpecifier(): string
    {
        return 'plugin:'.$this->id;
    }

    public function frontendPublicDirectory(): ?string
    {
        if ($this->frontendEntry === null) {
            return null;
        }

        return explode('/', $this->frontendEntry)[0] ?? null;
    }

    public function absolutePath(string $relativePath): string
    {
        return rtrim($this->basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    public function resolvePublicAsset(string $relativePath): ?string
    {
        $normalizedPath = self::normalizeRelativePath($relativePath);
        $publicDirectory = $this->frontendPublicDirectory();

        if ($normalizedPath === null || $publicDirectory === null) {
            return null;
        }

        if ($normalizedPath !== $publicDirectory && ! str_starts_with($normalizedPath, $publicDirectory.'/')) {
            return null;
        }

        $absolutePath = $this->absolutePath($normalizedPath);

        return is_file($absolutePath) ? $absolutePath : null;
    }

    private static function normalizePluginId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $id = trim($value);

        if ($id === '' || ! preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id)) {
            return null;
        }

        return $id;
    }

    private static function normalizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : $text;
    }

    private static function normalizeHref(mixed $value): ?string
    {
        $href = self::normalizeText($value);

        if ($href === null) {
            return null;
        }

        if (preg_match('/^(https?:)?\/\//', $href) === 1) {
            return $href;
        }

        return str_starts_with($href, '/') ? $href : '/'.$href;
    }

    private static function normalizeRelativePath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $path = str_replace('\\', '/', trim($value));

        if ($path === '' || str_starts_with($path, '/')) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));

        if ($segments === []) {
            return null;
        }

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                return null;
            }
        }

        return implode('/', $segments);
    }
}

