<?php

declare(strict_types=1);

/**
 * Generate doc/POLICY_PERMISSION_MODEL_MAPPING.md from policy files.
 *
 * Usage:
 *   php scripts/generate_policy_permission_model_mapping.php
 *   php scripts/generate_policy_permission_model_mapping.php --output=doc/POLICY_PERMISSION_MODEL_MAPPING.md
 *   php scripts/generate_policy_permission_model_mapping.php --no-timestamp
 */

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Could not resolve project root.\n");
    exit(1);
}

$options = parseOptions($argv);
$outputRelativePath = $options['output'] ?? 'doc/POLICY_PERMISSION_MODEL_MAPPING.md';
$includeTimestamp = !isset($options['no-timestamp']);

$outputPath = normalizePath($root, $outputRelativePath);
$policiesDir = normalizePath($root, 'app/Policies');
$crudConfigPath = normalizePath($root, 'config/generic_crud.php');

if (!is_dir($policiesDir)) {
    fwrite(STDERR, "Policies directory not found: {$policiesDir}\n");
    exit(1);
}

$targetMethods = ['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'];
$basePermissions = ['superadmin.edit', 'systemadministrator.edit'];

$permissionMap = [];
$modelMap = [];
$specialRows = [];

$policyFiles = glob($policiesDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
natcasesort($policyFiles);

foreach ($policyFiles as $policyFile) {
    $contents = file_get_contents($policyFile);
    if (!is_string($contents) || $contents === '') {
        continue;
    }

    preg_match('/class\s+(\w+)/', $contents, $classMatch);
    $policyClass = $classMatch[1] ?? basename((string) $policyFile, '.php');

    preg_match_all(
        '/public\s+function\s+(\w+)\s*\((.*?)\)\s*:\s*[^{}]+{/s',
        $contents,
        $methodMatches,
        PREG_OFFSET_CAPTURE
    );

    foreach (($methodMatches[0] ?? []) as $index => $fullMethodMatch) {
        $method = $methodMatches[1][$index][0] ?? null;
        if (!is_string($method) || !in_array($method, $targetMethods, true)) {
            continue;
        }

        $params = $methodMatches[2][$index][0] ?? '';
        $offset = (int) $fullMethodMatch[1] + strlen((string) $fullMethodMatch[0]);
        $body = extractMethodBody($contents, $offset);

        $model = detectModelName((string) $params);
        $claims = extractClaims($body);
        $delegates = extractDelegates($body);

        $alwaysFalse = preg_match('/\breturn\s+false\s*;/', $body) === 1 && $claims === [];
        $alwaysTrue = preg_match('/\breturn\s+true\s*;/', $body) === 1 && $claims === [];

        if ($alwaysFalse || $alwaysTrue || $delegates !== []) {
            $specialRows[] = [
                'policy' => $policyClass,
                'model' => $model ?? '-',
                'ability' => $method,
                'type' => $alwaysFalse ? 'always_false' : ($alwaysTrue ? 'always_true' : 'delegated'),
                'details' => $delegates !== [] ? implode(', ', $delegates) : '-',
            ];
        }

        foreach ($claims as $claim) {
            $entryKey = ($model ?? '-') . '|' . $policyClass;
            $permissionMap[$claim][$entryKey]['model'] = $model ?? '-';
            $permissionMap[$claim][$entryKey]['policy'] = $policyClass;
            $permissionMap[$claim][$entryKey]['abilities'][$method] = true;

            if ($model !== null) {
                $modelMap[$model][$claim][$policyClass][$method] = true;
            }
        }
    }
}

foreach ($basePermissions as $permission) {
    $permissionMap[$permission] = $permissionMap[$permission] ?? [];
}

$crudResourceModels = [];
if (file_exists($crudConfigPath)) {
    $cfg = require $crudConfigPath;
    foreach (($cfg['resources'] ?? []) as $resource => $modelClass) {
        if (!is_string($resource) || !is_string($modelClass)) {
            continue;
        }

        if (str_contains($modelClass, '\\')) {
            $parts = explode('\\', $modelClass);
            $modelShort = (string) end($parts);
            $crudResourceModels[$modelShort] = $resource;
        }
    }
}

ksort($permissionMap, SORT_NATURAL | SORT_FLAG_CASE);
ksort($modelMap, SORT_NATURAL | SORT_FLAG_CASE);
ksort($crudResourceModels, SORT_NATURAL | SORT_FLAG_CASE);
usort(
    $specialRows,
    static fn (array $a, array $b): int => [$a['policy'], $a['model'], $a['ability']] <=> [$b['policy'], $b['model'], $b['ability']]
);

$markdown = buildMarkdown($permissionMap, $modelMap, $specialRows, $crudResourceModels, $policiesDir, $includeTimestamp);

$outputDir = dirname($outputPath);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

file_put_contents($outputPath, $markdown);

fwrite(STDOUT, "Generated: {$outputPath}\n");
fwrite(STDOUT, 'Permissions: ' . count($permissionMap) . "\n");
fwrite(STDOUT, 'Models with explicit claims: ' . count($modelMap) . "\n");

function parseOptions(array $argv): array
{
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }

        $pair = explode('=', substr($arg, 2), 2);
        $key = $pair[0];
        $value = $pair[1] ?? true;
        $options[$key] = $value;
    }

    return $options;
}

function normalizePath(string $root, string $path): string
{
    $isWindowsAbsolute = strlen($path) > 2
        && ctype_alpha($path[0])
        && $path[1] === ':'
        && ($path[2] === '\\' || $path[2] === '/');

    if ($isWindowsAbsolute || str_starts_with($path, '/')) {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}

function extractMethodBody(string $contents, int $offset): string
{
    $depth = 1;
    $cursor = $offset;
    $length = strlen($contents);

    while ($cursor < $length && $depth > 0) {
        $char = $contents[$cursor];
        if ($char === '{') {
            $depth++;
        } elseif ($char === '}') {
            $depth--;
        }
        $cursor++;
    }

    return substr($contents, $offset, max(0, ($cursor - 1) - $offset));
}

function detectModelName(string $params): ?string
{
    if (preg_match('/\$\w+\s*=\s*new\s+([A-Za-z_][A-Za-z0-9_]*)/', $params, $modelMatch) === 1) {
        return $modelMatch[1];
    }

    preg_match_all('/\b([A-Z][A-Za-z0-9_]*)\s*\$\w+/', $params, $typedMatches);
    $typed = array_values(array_filter($typedMatches[1] ?? [], static fn (string $t): bool => $t !== 'User'));

    return $typed === [] ? null : (string) end($typed);
}

function extractClaims(string $body): array
{
    $claims = [];
    if (preg_match_all('/haveAnyAccessRights\(\s*\[(.*?)]\s*\)/s', $body, $claimBlocks) !== 1 && ($claimBlocks[1] ?? []) === []) {
        return [];
    }

    foreach ($claimBlocks[1] ?? [] as $claimBlock) {
        preg_match_all("/'([^']+)'/", $claimBlock, $claimMatches);
        foreach ($claimMatches[1] ?? [] as $claim) {
            $claims[$claim] = true;
        }
    }

    $result = array_keys($claims);
    sort($result, SORT_NATURAL | SORT_FLAG_CASE);

    return $result;
}

function extractDelegates(string $body): array
{
    preg_match_all('/\$user->can\(\s*\'([^\']+)\'/s', $body, $delegateMatches);
    $delegates = array_values(array_unique($delegateMatches[1] ?? []));
    sort($delegates, SORT_NATURAL | SORT_FLAG_CASE);

    return $delegates;
}

function buildMarkdown(
    array $permissionMap,
    array $modelMap,
    array $specialRows,
    array $crudResourceModels,
    string $policiesDir,
    bool $includeTimestamp
): string {
    $md = [];
    $md[] = '# POLICY PERMISSION MODEL MAPPING';
    $md[] = '';
    $md[] = 'Mappning av konfigurerade behorigheter (claims) till modellatkomst via policies, samt omvand mappning.';
    $md[] = '';
    $md[] = '## Snabbstart';
    $md[] = '';
    $md[] = '1. Sok i tabellen **Behorighet -> Modell/Policy/Ability** for att se vilka modeller och abilities en viss claim ger atkomst till.';
    $md[] = '2. Sok i tabellen **Modell -> Behorighet/Policy/Ability** for att se vilka claims som styr en viss modell.';
    $md[] = '3. Kontrollera **Specialfall i Policies** for abilities som ar hardkodade eller delegerade via `$user->can(...)`.';
    $md[] = '';
    $md[] = '## Innehallsforteckning';
    $md[] = '';
    $md[] = '- [Behorighet -> Modell/Policy/Ability](#behorighet---modellpolicyability)';
    $md[] = '- [Modell -> Behorighet/Policy/Ability](#modell---behorighetpolicyability)';
    $md[] = '- [Specialfall i Policies](#specialfall-i-policies)';
    $md[] = '- [CRUD-resurser utan explicit claim i policy](#crud-resurser-utan-explicit-claim-i-policy)';
    $md[] = '- [Noteringar](#noteringar)';
    $md[] = '';

    if ($includeTimestamp) {
        $md[] = '- Genererad: ' . date('Y-m-d H:i:s');
    }

    $md[] = '- Kallor: `app/Policies/*.php`, `app/Models/AccessGroup.php` (`allClaims()`), `config/generic_crud.php`';
    $md[] = '- Omfang: Endast claims identifierade i `haveAnyAccessRights([...])` i policy-metoder';
    $md[] = '';

    $md[] = '## Behorighet -> Modell/Policy/Ability';
    $md[] = '';
    $md[] = '| Behorighet | Modell | Policy | Abilities |';
    $md[] = '|---|---|---|---|';

    foreach ($permissionMap as $permission => $entries) {
        if ($entries === []) {
            $md[] = '| `' . $permission . '` | - | - | - |';
            continue;
        }

        ksort($entries, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($entries as $entry) {
            $abilities = array_keys($entry['abilities'] ?? []);
            sort($abilities, SORT_NATURAL | SORT_FLAG_CASE);
            $md[] = '| `' . $permission . '` | `' . $entry['model'] . '` | `' . $entry['policy'] . '` | `' . implode(', ', $abilities) . '` |';
        }
    }

    $md[] = '';
    $md[] = '## Modell -> Behorighet/Policy/Ability';
    $md[] = '';
    $md[] = '| Modell | Behorighet | Policy | Abilities |';
    $md[] = '|---|---|---|---|';

    foreach ($modelMap as $model => $permissions) {
        ksort($permissions, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($permissions as $permission => $policies) {
            ksort($policies, SORT_NATURAL | SORT_FLAG_CASE);
            foreach ($policies as $policy => $abilitiesSet) {
                $abilities = array_keys($abilitiesSet);
                sort($abilities, SORT_NATURAL | SORT_FLAG_CASE);
                $md[] = '| `' . $model . '` | `' . $permission . '` | `' . $policy . '` | `' . implode(', ', $abilities) . '` |';
            }
        }
    }

    $md[] = '';
    $md[] = '## Specialfall i Policies';
    $md[] = '';
    $md[] = '| Policy | Modell | Ability | Typ | Detalj |';
    $md[] = '|---|---|---|---|---|';
    foreach ($specialRows as $row) {
        $md[] = '| `' . $row['policy'] . '` | `' . $row['model'] . '` | `' . $row['ability'] . '` | `' . $row['type'] . '` | `' . $row['details'] . '` |';
    }

    $md[] = '';
    $md[] = '## CRUD-resurser utan explicit claim i policy';
    $md[] = '';
    $md[] = '| Resource | Modell | Policy-fil | Claim hittad |';
    $md[] = '|---|---|---|---|';

    foreach ($crudResourceModels as $model => $resource) {
        $policyClass = $model . 'Policy';
        $policyFile = $policiesDir . DIRECTORY_SEPARATOR . $policyClass . '.php';
        $hasPolicy = file_exists($policyFile);
        $hasClaim = isset($modelMap[$model]);
        $md[] = '| `' . $resource . '` | `' . $model . '` | `' . ($hasPolicy ? $policyClass : '-') . '` | `' . ($hasClaim ? 'ja' : 'nej') . '` |';
    }

    $md[] = '';
    $md[] = '## Noteringar';
    $md[] = '';
    $md[] = '- `restore` och `forceDelete` ar i de flesta policies hardkodade till `false` och inkluderar ofta inga claims.';
    $md[] = '- Vissa abilities delegerar till andra policy-checkar via `$user->can(...)`; se avsnittet *Specialfall i Policies*.';
    $md[] = '- Om en behorighet saknar rader i tabell 1 betyder det att den finns som baskonfiguration men utan direkt `haveAnyAccessRights`-anvandning i policies.';

    return implode(PHP_EOL, $md) . PHP_EOL;
}

