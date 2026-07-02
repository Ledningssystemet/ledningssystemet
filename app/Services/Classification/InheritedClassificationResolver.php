<?php

namespace App\Services\Classification;

use App\Models\Asset;
use App\Models\AssetAssetDependancy;
use App\Models\AssetInformationType;
use App\Models\AvailabilityClass;
use App\Models\ConfidentialityClass;
use App\Models\InformationType;
use App\Models\IntegrityClass;
use Illuminate\Support\Facades\Cache;

class InheritedClassificationResolver
{
    public const CONFIDENTIALITY = 'confidentiality';

    public const INTEGRITY = 'integrity';

    public const AVAILABILITY = 'availability';

    private const CACHE_VERSION_KEY = 'classification:effective:version';

    /**
     * @var array<string, int|null>
     */
    private static array $requestMemo = [];

    public function resolveAsset(Asset|int $asset, string $dimension): ?int
    {
        $assetId = $asset instanceof Asset ? (int) $asset->id : (int) $asset;

        if ($assetId <= 0) {
            return null;
        }

        return $this->resolveAssetById($assetId, $dimension, [], []);
    }

    public function resolveInformationType(InformationType|int $informationType, string $dimension): ?int
    {
        $informationTypeId = $informationType instanceof InformationType ? (int) $informationType->id : (int) $informationType;

        if ($informationTypeId <= 0) {
            return null;
        }

        return $this->resolveInformationTypeById($informationTypeId, $dimension, [], []);
    }

    public static function bumpCacheVersion(): void
    {
        $current = (int) Cache::get(self::CACHE_VERSION_KEY, 1);
        Cache::forever(self::CACHE_VERSION_KEY, $current + 1);
        self::$requestMemo = [];
    }

    private function resolveAssetById(int $assetId, string $dimension, array $visitedAssetIds, array $visitedInformationTypeIds): ?int
    {
        $memoKey = $this->memoKey('asset', $dimension, $assetId);

        if (array_key_exists($memoKey, self::$requestMemo)) {
            $memoizedValue = self::$requestMemo[$memoKey];

            return is_int($memoizedValue) ? $memoizedValue : null;
        }

        $cached = $this->getCachedValue('asset', $dimension, $assetId);
        if ($cached !== null) {
            return self::$requestMemo[$memoKey] = $cached;
        }

        if (isset($visitedAssetIds[$assetId])) {
            return self::$requestMemo[$memoKey] = null;
        }

        $visitedAssetIds[$assetId] = true;

        $column = $this->classIdColumn($dimension);
        $asset = Asset::query()->find($assetId, ['id', $column]);

        if (! $asset instanceof Asset) {
            return self::$requestMemo[$memoKey] = null;
        }

        $explicitClassId = $asset->getAttribute($column);
        if ($explicitClassId !== null) {
            $explicitClassId = (int) $explicitClassId;
            $this->storeCachedValue('asset', $dimension, $assetId, $explicitClassId);

            return self::$requestMemo[$memoKey] = $explicitClassId;
        }

        $candidates = [];

        $informationTypeIds = AssetInformationType::query()
            ->where('asset_id', $assetId)
            ->pluck('information_type_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($informationTypeIds as $informationTypeId) {
            $inheritedClassId = $this->resolveInformationTypeById($informationTypeId, $dimension, $visitedAssetIds, $visitedInformationTypeIds);

            if ($inheritedClassId !== null) {
                $candidates[] = $inheritedClassId;
            }
        }

        $inheritColumn = $this->inheritFlagColumn($dimension);

        $dependingAssetIds = AssetAssetDependancy::query()
            ->where('dependant_asset_id', $assetId)
            ->where($inheritColumn, true)
            ->pluck('depending_asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        foreach ($dependingAssetIds as $dependingAssetId) {
            $inheritedClassId = $this->resolveAssetById($dependingAssetId, $dimension, $visitedAssetIds, $visitedInformationTypeIds);

            if ($inheritedClassId !== null) {
                $candidates[] = $inheritedClassId;
            }
        }

        $effectiveClassId = $this->highestByOrdinal($dimension, $candidates);

        $this->storeCachedValue('asset', $dimension, $assetId, $effectiveClassId);

        return self::$requestMemo[$memoKey] = $effectiveClassId;
    }

    private function resolveInformationTypeById(int $informationTypeId, string $dimension, array $visitedAssetIds, array $visitedInformationTypeIds): ?int
    {
        $memoKey = $this->memoKey('information_type', $dimension, $informationTypeId);

        if (array_key_exists($memoKey, self::$requestMemo)) {
            $memoizedValue = self::$requestMemo[$memoKey];

            return is_int($memoizedValue) ? $memoizedValue : null;
        }

        $cached = $this->getCachedValue('information_type', $dimension, $informationTypeId);
        if ($cached !== null) {
            return self::$requestMemo[$memoKey] = $cached;
        }

        if (isset($visitedInformationTypeIds[$informationTypeId])) {
            return self::$requestMemo[$memoKey] = null;
        }

        $visitedInformationTypeIds[$informationTypeId] = true;

        $column = $this->classIdColumn($dimension);
        $informationType = InformationType::query()->find($informationTypeId, ['id', $column]);

        if (! $informationType instanceof InformationType) {
            return self::$requestMemo[$memoKey] = null;
        }

        $explicitClassId = $informationType->getAttribute($column);
        if ($explicitClassId !== null) {
            $explicitClassId = (int) $explicitClassId;
            $this->storeCachedValue('information_type', $dimension, $informationTypeId, $explicitClassId);

            return self::$requestMemo[$memoKey] = $explicitClassId;
        }

        $assetIds = AssetInformationType::query()
            ->where('information_type_id', $informationTypeId)
            ->pluck('asset_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $candidates = [];

        foreach ($assetIds as $assetId) {
            $inheritedClassId = $this->resolveAssetById($assetId, $dimension, $visitedAssetIds, $visitedInformationTypeIds);

            if ($inheritedClassId !== null) {
                $candidates[] = $inheritedClassId;
            }
        }

        $effectiveClassId = $this->highestByOrdinal($dimension, $candidates);

        $this->storeCachedValue('information_type', $dimension, $informationTypeId, $effectiveClassId);

        return self::$requestMemo[$memoKey] = $effectiveClassId;
    }

    /**
     * @param array<int, int> $classIds
     */
    private function highestByOrdinal(string $dimension, array $classIds): ?int
    {
        if ($classIds === []) {
            return null;
        }

        $ordinalsByClassId = $this->classOrdinals($dimension);
        $highestClassId = null;
        $highestOrdinal = null;

        foreach ($classIds as $classId) {
            $ordinal = $ordinalsByClassId[$classId] ?? null;

            if ($ordinal === null) {
                continue;
            }

            if ($highestOrdinal === null || $ordinal > $highestOrdinal) {
                $highestOrdinal = $ordinal;
                $highestClassId = $classId;
            }
        }

        return $highestClassId;
    }

    /**
     * @return array<int, int>
     */
    private function classOrdinals(string $dimension): array
    {
        $cacheKey = sprintf(
            'classification:ordinals:%s:v%d',
            $dimension,
            $this->cacheVersion(),
        );

        /** @var array<int, int> $ordinals */
        $ordinals = Cache::rememberForever($cacheKey, function () use ($dimension): array {
            return match ($dimension) {
                self::CONFIDENTIALITY => ConfidentialityClass::query()->pluck('ordinal', 'id')->map(static fn (mixed $v): int => (int) $v)->all(),
                self::INTEGRITY => IntegrityClass::query()->pluck('ordinal', 'id')->map(static fn (mixed $v): int => (int) $v)->all(),
                self::AVAILABILITY => AvailabilityClass::query()->pluck('ordinal', 'id')->map(static fn (mixed $v): int => (int) $v)->all(),
                default => throw new \InvalidArgumentException('Unsupported classification dimension: '.$dimension),
            };
        });

        return $ordinals;
    }

    private function classIdColumn(string $dimension): string
    {
        return match ($dimension) {
            self::CONFIDENTIALITY => 'confidentiality_class_id',
            self::INTEGRITY => 'integrity_class_id',
            self::AVAILABILITY => 'availability_class_id',
            default => throw new \InvalidArgumentException('Unsupported classification dimension: '.$dimension),
        };
    }

    private function inheritFlagColumn(string $dimension): string
    {
        return match ($dimension) {
            self::CONFIDENTIALITY => 'inherit_confidentiality',
            self::INTEGRITY => 'inherit_integrity',
            self::AVAILABILITY => 'inherit_availability',
            default => throw new \InvalidArgumentException('Unsupported classification dimension: '.$dimension),
        };
    }

    private function cacheVersion(): int
    {
        $version = (int) Cache::get(self::CACHE_VERSION_KEY, 1);

        if ($version <= 0) {
            $version = 1;
            Cache::forever(self::CACHE_VERSION_KEY, $version);
        }

        return $version;
    }

    private function memoKey(string $type, string $dimension, int $id): string
    {
        return $type.':'.$dimension.':'.$id;
    }

    private function cacheKey(string $type, string $dimension, int $id): string
    {
        return sprintf('classification:effective:%s:%s:%d:v%d', $type, $dimension, $id, $this->cacheVersion());
    }

    private function getCachedValue(string $type, string $dimension, int $id): ?int
    {
        $cached = Cache::get($this->cacheKey($type, $dimension, $id));

        if ($cached === null) {
            return null;
        }

        $value = (int) $cached;

        return $value > 0 ? $value : null;
    }

    private function storeCachedValue(string $type, string $dimension, int $id, ?int $value): void
    {
        Cache::forever($this->cacheKey($type, $dimension, $id), $value ?? 0);
    }
}

