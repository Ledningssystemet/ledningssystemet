<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class ModelConventionsTest extends TestCase
{
    private const SENSITIVE_EXACT_ATTRIBUTES = [
        'password',
        'remember_token',
        'token',
        'authtoken',
        'api_key',
        'secret',
        'secret_key',
        'private_key',
        'public_key',
        'access_token',
        'refresh_token',
        'client_secret',
    ];

    #[DataProvider('modelClassProvider')]
    public function test_model_has_pretty_name_and_non_empty_values(string $modelClass): void
    {
        self::assertTrue(
            method_exists($modelClass, 'getPrettyName'),
            "{$modelClass} is missing static getPrettyName()."
        );

        $singular = $modelClass::getPrettyName(false);
        $plural = $modelClass::getPrettyName(true);

        self::assertIsString($singular, "{$modelClass}::getPrettyName(false) must return a string.");
        self::assertIsString($plural, "{$modelClass}::getPrettyName(true) must return a string.");
        self::assertNotSame('', trim($singular), "{$modelClass}::getPrettyName(false) returned an empty string.");
        self::assertNotSame('', trim($plural), "{$modelClass}::getPrettyName(true) returned an empty string.");
    }

    #[DataProvider('modelClassProvider')]
    public function test_model_has_validation_configured_for_all_fillable_attributes(string $modelClass): void
    {
        self::assertTrue(
            method_exists($modelClass, 'validationRules'),
            "{$modelClass} is missing static validationRules()."
        );

        $rules = $modelClass::validationRules();

        self::assertIsArray($rules, "{$modelClass}::validationRules() must return an array.");

        $model = new $modelClass();
        self::assertInstanceOf(Model::class, $model, "{$modelClass} must be an Eloquent model.");

        $fillable = $model->getFillable();

        $missingRules = array_values(array_diff($fillable, array_keys($rules)));
        $extraRules = array_values(array_diff(array_keys($rules), $fillable));

        self::assertSame(
            [],
            $missingRules,
            "{$modelClass}::validationRules() is missing keys for fillable attributes: " . implode(', ', $missingRules)
        );

        self::assertSame(
            [],
            $extraRules,
            "{$modelClass}::validationRules() has keys that are not fillable: " . implode(', ', $extraRules)
        );

        foreach ($fillable as $attribute) {
            self::assertArrayHasKey(
                $attribute,
                $rules,
                "{$modelClass}::validationRules() is missing rules for fillable attribute '{$attribute}'."
            );

            $attributeRules = $rules[$attribute];

            if (is_string($attributeRules)) {
                self::assertNotSame(
                    '',
                    trim($attributeRules),
                    "{$modelClass} has an empty validation rule string for '{$attribute}'."
                );
                continue;
            }

            self::assertIsArray(
                $attributeRules,
                "{$modelClass} has invalid validation rules for '{$attribute}'. Expected string or array."
            );
            self::assertNotEmpty($attributeRules, "{$modelClass} has no validation rules for '{$attribute}'.");
        }
    }

    #[DataProvider('modelClassProvider')]
    public function test_sensitive_attributes_are_hidden_from_serialization(string $modelClass): void
    {
        $model = new $modelClass();
        self::assertInstanceOf(Model::class, $model, "{$modelClass} must be an Eloquent model.");

        $fillable = $model->getFillable();
        $ruleKeys = method_exists($modelClass, 'validationRules') ? array_keys((array) $modelClass::validationRules()) : [];
        $candidateAttributes = array_values(array_unique(array_merge($fillable, $ruleKeys)));

        $sensitiveAttributes = array_values(array_filter(
            $candidateAttributes,
            static fn (string $attribute): bool => self::isSensitiveAttribute($attribute)
        ));

        if ($sensitiveAttributes === []) {
            self::assertTrue(true);
            return;
        }

        $hidden = $model->getHidden();

        foreach ($sensitiveAttributes as $attribute) {
            self::assertContains(
                $attribute,
                $hidden,
                "{$modelClass} must hide sensitive attribute '{$attribute}' via \$hidden."
            );
        }

        $payload = [];
        foreach ($sensitiveAttributes as $attribute) {
            $payload[$attribute] = 'sensitive-test-value';
        }

        $model->forceFill($payload);

        $arrayData = $model->toArray();
        $jsonData = json_decode($model->toJson(), true);
        self::assertIsArray($jsonData, "{$modelClass}::toJson() must decode to an array payload.");

        foreach ($sensitiveAttributes as $attribute) {
            self::assertArrayNotHasKey(
                $attribute,
                $arrayData,
                "{$modelClass} serialized sensitive attribute '{$attribute}' in toArray()."
            );
            self::assertArrayNotHasKey(
                $attribute,
                $jsonData,
                "{$modelClass} serialized sensitive attribute '{$attribute}' in toJson()."
            );
        }
    }

    /**
     * @return array<int, array{0: class-string<Model>}>
     */
    public static function modelClassProvider(): array
    {
        $modelsPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models';
        $files = glob($modelsPath . DIRECTORY_SEPARATOR . '*.php') ?: [];

        $classes = [];

        foreach ($files as $file) {
            $baseName = pathinfo($file, PATHINFO_FILENAME);
            $class = 'App\\Models\\' . $baseName;

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract()) {
                continue;
            }

            if (!$reflection->isSubclassOf(Model::class)) {
                continue;
            }

            $classes[] = [$class];
        }

        usort($classes, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        return $classes;
    }

    private static function isSensitiveAttribute(string $attribute): bool
    {
        $attribute = strtolower($attribute);

        if (in_array($attribute, self::SENSITIVE_EXACT_ATTRIBUTES, true)) {
            return true;
        }

        if (str_ends_with($attribute, '_tokens')) {
            return false;
        }

        if (str_ends_with($attribute, '_token')) {
            return true;
        }

        if (preg_match('/(?:^|_)(api|secret|private|public|access|refresh|encryption|signing)_?key$/', $attribute) === 1) {
            return true;
        }

        return false;
    }
}

