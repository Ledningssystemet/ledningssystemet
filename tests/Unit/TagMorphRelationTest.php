<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Ledningssystemet\Ledningssystemet\Models\Asset;
use Tests\TestCase;

class TagMorphRelationTest extends TestCase
{
    public function testTagRelationUsesOnlyTheModelsMorphType(): void
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

            $this->assertInstanceOf(MorphToMany::class, $relation);
            $this->assertContains($asset->getMorphClass(), $relation->getBindings());
            $this->assertNotContains('App\\Models\\Asset', $relation->getBindings());
            $this->assertStringContainsString('object_tags_type', $relation->toSql());
            $this->assertContains($asset->getMorphClass(), $relation->newPivotQuery()->getBindings());
            $this->assertNotContains('App\\Models\\Asset', $relation->newPivotQuery()->getBindings());
        } finally {
            DB::purge('sqlite');
            config(['database' => $databaseConfig]);
        }
    }
}
