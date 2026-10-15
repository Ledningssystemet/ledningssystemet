<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Ledningssystemet\Ledningssystemet\Models\Asset;
use Ledningssystemet\Ledningssystemet\Support\TagMorphType;
use Tests\TestCase;

class TagNamespaceCompatibilityTest extends TestCase
{
    public function testBothModelNamespacesResolveToTheAvailableModel(): void
    {
        $this->assertSame(
            Asset::class,
            TagMorphType::resolve('App\\Models\\Asset'),
        );
        $this->assertSame(
            Asset::class,
            TagMorphType::resolve(Asset::class),
        );
    }

    public function testTagRelationQueriesBothNamespaceTypes(): void
    {
        $databaseConfig = config('database');
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('sqlite');

        try {
            $asset = new Asset;
            $asset->setRawAttributes(['id' => 1]);
            $relation = $asset->tags();

            $this->assertContains('App\\Models\\Asset', $relation->getBindings());
            $this->assertContains(Asset::class, $relation->getBindings());
            $this->assertStringContainsString('object_tags_type', $relation->toSql());
            $this->assertContains('App\\Models\\Asset', $relation->newPivotQuery()->getBindings());
            $this->assertContains(Asset::class, $relation->newPivotQuery()->getBindings());
        } finally {
            DB::purge('sqlite');
            config(['database' => $databaseConfig]);
        }
    }
}
