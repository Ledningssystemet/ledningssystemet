<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sqlFile = $root . '/database/schema/baseline_schema.sql';
$modelsDir = $root . '/app/Models';

if (!is_file($sqlFile)) {
    fwrite(STDERR, "Schema file not found: {$sqlFile}\n");
    exit(1);
}

if (!is_dir($modelsDir)) {
    fwrite(STDERR, "Models directory not found: {$modelsDir}\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Failed to read schema file.\n");
    exit(1);
}

$tables = parseTables($sql);
if ($tables === []) {
    fwrite(STDERR, "No tables parsed from schema.\n");
    exit(1);
}

$modelFiles = glob($modelsDir . '/*.php') ?: [];
$modelClasses = array_map(static function (string $path): string {
    return pathinfo($path, PATHINFO_FILENAME);
}, $modelFiles);
sort($modelClasses);

$tableNames = array_keys($tables);
$classToTable = [];
$tableToClass = [];

foreach ($modelClasses as $class) {
    $table = resolveTableForClass($class, $tableNames);
    if ($table === null) {
        fwrite(STDERR, "Could not map class {$class} to table.\n");
        continue;
    }

    $classToTable[$class] = $table;
    $tableToClass[$table] = $class;
}

$incomingFks = buildIncomingFks($tables);
$morphPairsByTable = detectMorphPairsByTable($tables);
$globalMorphTargets = [];
foreach ($morphPairsByTable as $table => $pairs) {
    if (!isset($tableToClass[$table])) {
        continue;
    }

    foreach ($pairs as $pair) {
        $globalMorphTargets[] = [
            'table' => $table,
            'class' => $tableToClass[$table],
            'name' => $pair['name'],
            'typeColumn' => $pair['typeColumn'],
            'idColumn' => $pair['idColumn'],
        ];
    }
}

$pivotLinks = detectPivotLinks($tables, $tableToClass);

foreach ($modelClasses as $class) {
    if (!isset($classToTable[$class])) {
        continue;
    }

    $table = $classToTable[$class];
    $meta = $tables[$table];
    $content = renderModel(
        $class,
        $table,
        $meta,
        $tableToClass,
        $incomingFks[$table] ?? [],
        $morphPairsByTable[$table] ?? [],
        $globalMorphTargets,
        $pivotLinks
    );

    file_put_contents($modelsDir . '/' . $class . '.php', $content);
}

echo 'Regenerated ' . count($classToTable) . " model files.\n";

function parseTables(string $sql): array
{
    $tables = [];

    preg_match_all('/CREATE TABLE `([^`]+)` \((.*?)\) ENGINE=/si', $sql, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $table = $match[1];
        $body = $match[2];
        $lines = preg_split('/\R/', $body) ?: [];

        $columns = [];
        $fks = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $line = rtrim($line, ',');
            if ($line === '') {
                continue;
            }

            if (preg_match('/^`([^`]+)`\s+(.+)$/', $line, $columnMatch) === 1) {
                $name = $columnMatch[1];
                $definition = $columnMatch[2];
                $typeInfo = extractColumnType($definition);
                $columns[$name] = [
                    'name' => $name,
                    'type' => $typeInfo['type'],
                    'rest' => $typeInfo['rest'],
                    'nullable' => stripos($definition, 'NOT NULL') === false,
                    'autoIncrement' => stripos($definition, 'AUTO_INCREMENT') !== false,
                    'hasJsonCheck' => stripos($definition, 'json_valid(') !== false,
                ];
                continue;
            }

            if (preg_match('/FOREIGN KEY \(`([^`]+)`\) REFERENCES `([^`]+)` \(`([^`]+)`\)/i', $line, $fkMatch) === 1) {
                $fks[] = [
                    'column' => $fkMatch[1],
                    'referencesTable' => $fkMatch[2],
                    'referencesColumn' => $fkMatch[3],
                ];
            }
        }

        $tables[$table] = [
            'columns' => $columns,
            'fks' => $fks,
        ];
    }

    ksort($tables);

    return $tables;
}

function extractColumnType(string $definition): array
{
    if (preg_match('/^(enum\((?:[^()]|\([^)]*\))*\)|set\((?:[^()]|\([^)]*\))*\)|[a-zA-Z]+(?:\([^)]*\))?)(.*)$/i', $definition, $match) === 1) {
        return [
            'type' => strtolower(trim($match[1])),
            'rest' => trim($match[2]),
        ];
    }

    $parts = preg_split('/\s+/', $definition, 2);

    return [
        'type' => strtolower($parts[0] ?? 'string'),
        'rest' => trim($parts[1] ?? ''),
    ];
}

function resolveTableForClass(string $class, array $tableNames): ?string
{
    $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class));

    $candidates = [
        $snake,
        pluralize($snake),
    ];

    if (str_ends_with($snake, '_status_email_setting')) {
        $candidates[] = substr($snake, 0, -strlen('_status_email_setting')) . '_status_email_settings';
    }

    $manual = [
        'PasswordReset' => ['password_resets', 'password_reset_tokens'],
    ];

    foreach ($manual[$class] ?? [] as $candidate) {
        array_unshift($candidates, $candidate);
    }

    foreach (array_unique($candidates) as $candidate) {
        if (in_array($candidate, $tableNames, true)) {
            return $candidate;
        }
    }

    $best = null;
    $bestDistance = PHP_INT_MAX;
    foreach ($tableNames as $tableName) {
        $distance = levenshtein($snake, $tableName);
        if ($distance < $bestDistance) {
            $bestDistance = $distance;
            $best = $tableName;
        }
    }

    return $bestDistance <= 6 ? $best : null;
}

function pluralize(string $word): string
{
    if (preg_match('/[^aeiou]y$/', $word) === 1) {
        return substr($word, 0, -1) . 'ies';
    }

    if (preg_match('/(s|x|z|ch|sh)$/', $word) === 1) {
        return $word . 'es';
    }

    return $word . 's';
}

function singularize(string $word): string
{
    if (preg_match('/ies$/', $word) === 1) {
        return substr($word, 0, -3) . 'y';
    }

    if (preg_match('/(ses|xes|zes|ches|shes)$/', $word) === 1) {
        return substr($word, 0, -2);
    }

    if (str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
        return substr($word, 0, -1);
    }

    return $word;
}

function buildIncomingFks(array $tables): array
{
    $incoming = [];

    foreach ($tables as $table => $meta) {
        foreach ($meta['fks'] as $fk) {
            $incoming[$fk['referencesTable']][] = [
                'fromTable' => $table,
                'fromColumn' => $fk['column'],
                'toColumn' => $fk['referencesColumn'],
            ];
        }
    }

    return $incoming;
}

function detectMorphPairsByTable(array $tables): array
{
    $pairs = [];

    foreach ($tables as $table => $meta) {
        $columns = array_keys($meta['columns']);
        foreach ($columns as $column) {
            if (!str_ends_with($column, '_type')) {
                continue;
            }

            $base = substr($column, 0, -5);
            $idColumn = $base . '_id';
            if (!in_array($idColumn, $columns, true)) {
                continue;
            }

            $pairs[$table][] = [
                'name' => $base,
                'typeColumn' => $column,
                'idColumn' => $idColumn,
            ];
        }
    }

    return $pairs;
}

function detectPivotLinks(array $tables, array $tableToClass): array
{
    $links = [];

    foreach ($tables as $pivotTable => $meta) {
        if (count($meta['fks']) !== 2) {
            continue;
        }

        $fkA = $meta['fks'][0];
        $fkB = $meta['fks'][1];

        if (!isset($tableToClass[$fkA['referencesTable']], $tableToClass[$fkB['referencesTable']])) {
            continue;
        }

        $allowed = ['id', $fkA['column'], $fkB['column'], 'created_at', 'updated_at'];
        $extraColumns = array_values(array_filter(
            array_keys($meta['columns']),
            static fn(string $col): bool => !in_array($col, $allowed, true)
        ));

        if ($extraColumns !== []) {
            continue;
        }

        $links[] = [
            'pivotTable' => $pivotTable,
            'leftTable' => $fkA['referencesTable'],
            'leftColumn' => $fkA['column'],
            'rightTable' => $fkB['referencesTable'],
            'rightColumn' => $fkB['column'],
            'pivotColumns' => array_keys($meta['columns']),
        ];
    }

    return $links;
}

function renderModel(
    string $class,
    string $table,
    array $meta,
    array $tableToClass,
    array $incoming,
    array $localMorphPairs,
    array $globalMorphTargets,
    array $pivotLinks
): string {
    $columns = $meta['columns'];
    $fillable = [];
    foreach ($columns as $name => $column) {
        if (in_array($name, ['id', 'created_at', 'updated_at'], true)) {
            continue;
        }
        $fillable[] = $name;
    }

    $casts = [];
    foreach ($columns as $name => $column) {
        $cast = castForColumn($column);
        if ($cast !== null) {
            $casts[$name] = $cast;
        }
    }

    $rules = [];
    foreach ($fillable as $attribute) {
        $rules[$attribute] = validationRulesForColumn($columns[$attribute], $meta['fks']);
    }

    $imports = [
        'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
        'Illuminate\\Database\\Eloquent\\Model',
        'Illuminate\\Support\\Facades\\Validator',
    ];

    $relations = [];
    $usedMethods = [];
    $returnTypes = [];

    foreach ($meta['fks'] as $fk) {
        if (!isset($tableToClass[$fk['referencesTable']])) {
            continue;
        }

        $targetClass = $tableToClass[$fk['referencesTable']];
        $base = preg_replace('/_id$/', '', $fk['column']) ?: $fk['column'];
        $method = uniqueMethod('int_' . $base, $usedMethods);
        $returnTypes['BelongsTo'] = true;

        $relations[] = "    public function {$method}(): BelongsTo\n    {\n        return \$this->belongsTo({$targetClass}::class, '{$fk['column']}');\n    }\n";
    }

    $incomingByTable = [];
    foreach ($incoming as $fk) {
        if (!isset($tableToClass[$fk['fromTable']])) {
            continue;
        }
        $incomingByTable[$fk['fromTable']][] = $fk;
    }

    foreach ($incomingByTable as $fromTable => $group) {
        $fromClass = $tableToClass[$fromTable];
        $multi = count($group) > 1;

        foreach ($group as $fk) {
            $methodBase = 'int_' . $fromTable;
            if ($multi) {
                $methodBase .= '_by_' . preg_replace('/_id$/', '', $fk['fromColumn']);
            }
            $method = uniqueMethod($methodBase, $usedMethods);
            $returnTypes['HasMany'] = true;
            $relations[] = "    public function {$method}(): HasMany\n    {\n        return \$this->hasMany({$fromClass}::class, '{$fk['fromColumn']}', '{$fk['toColumn']}');\n    }\n";
        }
    }

    foreach ($pivotLinks as $pivot) {
        $otherTable = null;
        $localColumn = null;
        $otherColumn = null;

        if ($pivot['leftTable'] === $table) {
            $otherTable = $pivot['rightTable'];
            $localColumn = $pivot['leftColumn'];
            $otherColumn = $pivot['rightColumn'];
        } elseif ($pivot['rightTable'] === $table) {
            $otherTable = $pivot['leftTable'];
            $localColumn = $pivot['rightColumn'];
            $otherColumn = $pivot['leftColumn'];
        }

        if ($otherTable === null || !isset($tableToClass[$otherTable])) {
            continue;
        }

        $otherClass = $tableToClass[$otherTable];
        $otherMethodBase = 'int_' . pluralize(singularize($otherTable));
        $method = uniqueMethod($otherMethodBase, $usedMethods);
        $returnTypes['BelongsToMany'] = true;

        $withTimestamps = in_array('created_at', $pivot['pivotColumns'], true) && in_array('updated_at', $pivot['pivotColumns'], true);
        $extraPivotColumns = array_values(array_filter(
            $pivot['pivotColumns'],
            static fn(string $col): bool => !in_array($col, ['id', $localColumn, $otherColumn, 'created_at', 'updated_at'], true)
        ));

        $chain = '';
        if ($extraPivotColumns !== []) {
            $list = "'" . implode("', '", $extraPivotColumns) . "'";
            $chain .= "\n            ->withPivot([{$list}])";
        }
        if ($withTimestamps) {
            $chain .= "\n            ->withTimestamps()";
        }

        $relations[] = "    public function {$method}(): BelongsToMany\n    {\n        return \$this->belongsToMany({$otherClass}::class, '{$pivot['pivotTable']}', '{$localColumn}', '{$otherColumn}'){$chain};\n    }\n";
    }

    foreach ($localMorphPairs as $pair) {
        $method = uniqueMethod('int_' . $pair['name'], $usedMethods);
        $returnTypes['MorphTo'] = true;
        $relations[] = "    public function {$method}(): MorphTo\n    {\n        return \$this->morphTo('{$pair['name']}', '{$pair['typeColumn']}', '{$pair['idColumn']}');\n    }\n";
    }

    foreach ($globalMorphTargets as $target) {
        if ($target['table'] === $table) {
            continue;
        }

        $method = uniqueMethod('int_' . $target['table'] . '_as_' . $target['name'], $usedMethods);
        $returnTypes['MorphMany'] = true;
        $relations[] = "    public function {$method}(): MorphMany\n    {\n        return \$this->morphMany({$target['class']}::class, '{$target['name']}', '{$target['typeColumn']}', '{$target['idColumn']}');\n    }\n";
    }

    ksort($returnTypes);
    foreach (array_keys($returnTypes) as $type) {
        $imports[] = "Illuminate\\Database\\Eloquent\\Relations\\{$type}";
    }

    $imports = array_values(array_unique($imports));
    sort($imports);

    $isUser = $class === 'User';
    if ($isUser) {
        $imports = array_values(array_filter($imports, static fn(string $i): bool => $i !== 'Illuminate\\Database\\Eloquent\\Model'));
        $imports[] = 'Illuminate\\Foundation\\Auth\\User as Authenticatable';
        $imports[] = 'Illuminate\\Notifications\\Notifiable';
        sort($imports);
    }

    $prettySingular = readableClassName($class);
    $prettyPlural = pluralizePretty($prettySingular);

    $renderImports = '';
    foreach ($imports as $import) {
        $renderImports .= 'use ' . $import . ";\n";
    }

    $fillableList = implode(', ', array_map(static fn(string $a): string => "'{$a}'", $fillable));

    $castsRender = "";
    if ($casts !== []) {
        $castsLines = [];
        foreach ($casts as $attribute => $cast) {
            $castsLines[] = "            '{$attribute}' => '{$cast}',";
        }
        $castsRender = "\n    protected function casts(): array\n    {\n        return [\n" . implode("\n", $castsLines) . "\n        ];\n    }\n";
    }

    $rulesLines = [];
    foreach ($rules as $attribute => $attributeRules) {
        $joined = "'" . implode("', '", $attributeRules) . "'";
        $rulesLines[] = "            '{$attribute}' => [{$joined}],";
    }

    $relationsRender = implode("\n", $relations);

    $extends = $isUser ? 'Authenticatable' : 'Model';
    $traits = $isUser ? 'HasFactory, Notifiable' : 'HasFactory';

    return "<?php\n\nnamespace App\\Models;\n\n{$renderImports}\nclass {$class} extends {$extends}\n{\n    use {$traits};\n\n    protected \$table = '{$table}';\n\n    protected \$fillable = [{$fillableList}];\n{$castsRender}
    public static function validationRules(): array\n    {\n        return [\n" . implode("\n", $rulesLines) . "\n        ];\n    }\n\n    protected static function booted(): void\n    {\n        static::saving(function (self \$model): void {\n            Validator::make(\$model->attributesToArray(), static::validationRules())->validate();\n        });\n    }\n\n    public static function getPrettyName(\$plural = false): string\n    {\n        return \$plural ? '{$prettyPlural}' : '{$prettySingular}';\n    }\n\n{$relationsRender}}\n";
}

function castForColumn(array $column): ?string
{
    $type = $column['type'];

    if (str_starts_with($type, 'tinyint(1)')) {
        return 'boolean';
    }

    if (str_starts_with($type, 'date') && $type !== 'datetime') {
        return 'date';
    }

    if (str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
        return 'datetime';
    }

    if ($column['hasJsonCheck']) {
        return 'array';
    }

    if ($type === 'json') {
        return 'array';
    }

    return null;
}

function validationRulesForColumn(array $column, array $tableFks): array
{
    $rules = [];

    $required = !$column['nullable'] && !$column['autoIncrement'];
    $rules[] = $required ? 'required' : 'nullable';

    $type = $column['type'];

    if (str_starts_with($type, 'tinyint(1)')) {
        $rules[] = 'boolean';
    } elseif (preg_match('/^(bigint|int|smallint|mediumint|tinyint)/', $type) === 1) {
        $rules[] = 'integer';
        if (stripos($column['rest'], 'unsigned') !== false) {
            $rules[] = 'min:0';
        }
    } elseif (preg_match('/^(decimal|double|float)/', $type) === 1) {
        $rules[] = 'numeric';
    } elseif (str_starts_with($type, 'date') || str_starts_with($type, 'datetime') || str_starts_with($type, 'timestamp')) {
        $rules[] = 'date';
    } elseif ($column['hasJsonCheck'] || $type === 'json') {
        $rules[] = 'array';
    } elseif (str_starts_with($type, 'enum(')) {
        $rules[] = 'string';
        if (preg_match('/^enum\((.*)\)$/', $type, $enumMatch) === 1) {
            $rawValues = str_getcsv($enumMatch[1], ',', "'", '\\');
            $values = array_map(static fn(string $v): string => trim($v, "'\""), $rawValues);
            if ($values !== []) {
                $rules[] = 'in:' . implode(',', $values);
            }
        }
    } elseif (preg_match('/(char|text|blob|binary)/', $type) === 1) {
        $rules[] = 'string';
        if (preg_match('/^(varchar|char)\((\d+)\)/', $type, $lenMatch) === 1) {
            $rules[] = 'max:' . $lenMatch[2];
        }
    }

    foreach ($tableFks as $fk) {
        if ($fk['column'] === $column['name']) {
            $rules[] = "exists:{$fk['referencesTable']},{$fk['referencesColumn']}";
            break;
        }
    }

    return array_values(array_unique($rules));
}

function uniqueMethod(string $base, array &$used): string
{
    $candidate = $base;
    $index = 2;

    while (isset($used[$candidate])) {
        $candidate = $base . '_' . $index;
        $index++;
    }

    $used[$candidate] = true;

    return $candidate;
}

function readableClassName(string $class): string
{
    preg_match_all('/[A-Z]+(?![a-z])|[A-Z][a-z]*/', $class, $parts);
    $words = $parts[0] ?: [$class];
    return implode(' ', array_map(static function (string $word): string {
        if (strlen($word) <= 2 && strtoupper($word) === $word) {
            return $word;
        }

        return ucfirst(strtolower($word));
    }, $words));
}

function pluralizePretty(string $pretty): string
{
    $words = explode(' ', $pretty);
    $last = array_pop($words) ?? $pretty;

    if (preg_match('/[^aeiou]y$/i', $last) === 1) {
        $lastPlural = substr($last, 0, -1) . 'ies';
    } elseif (preg_match('/(s|x|z|ch|sh)$/i', $last) === 1) {
        $lastPlural = $last . 'es';
    } else {
        $lastPlural = $last . 's';
    }

    $words[] = $lastPlural;

    return implode(' ', $words);
}

