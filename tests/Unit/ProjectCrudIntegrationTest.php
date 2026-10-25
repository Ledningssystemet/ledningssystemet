<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ledningssystemet\Ledningssystemet\Models\ControlAction;
use Ledningssystemet\Ledningssystemet\Models\ActivityLog;
use Ledningssystemet\Ledningssystemet\Models\Risk;
use Spatie\QueryBuilder\QueryBuilder;
use Tests\TestCase;

class ProjectCrudIntegrationTest extends TestCase
{
    public function testDepartmentRiskModelNoLongerExposesProjectAssociation(): void
    {
        $risk = new Risk;
        $this->assertNotContains('project_id', $risk->getVisible());
        $this->assertNotContains('project_id', $risk->getFillable());
    }

    public function testControlActionsAreScopedThroughTheirRiskMappings(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The pdo_sqlite extension is required for CRUD filter tests.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        Schema::create('control_actions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('control_action_mappings', function (Blueprint $table) {
            $table->unsignedBigInteger('control_action_id');
            $table->unsignedBigInteger('risk_id');
        });
        DB::table('control_actions')->insert([
            ['id' => 1, 'name' => 'Shared action'],
            ['id' => 2, 'name' => 'Other project'],
            ['id' => 3, 'name' => 'Unlinked action'],
        ]);
        DB::table('risks')->insert([
            ['id' => 10],
            ['id' => 11],
            ['id' => 12],
        ]);
        DB::table('control_action_mappings')->insert([
            ['control_action_id' => 1, 'risk_id' => 10],
            ['control_action_id' => 1, 'risk_id' => 11],
            ['control_action_id' => 2, 'risk_id' => 12],
        ]);

        foreach ([
            ['risk_id', 10, [1]],
            ['risk_id', 12, [2]],
            ['name', 'Shared', [1]],
        ] as [$filter, $value, $expected]) {
            $request = Request::create('/', 'GET', ['filter' => [$filter => $value]]);
            $ids = QueryBuilder::for(ControlAction::query(), $request)
                ->allowedFilters(...ControlAction::getAllowedFilters())
                ->pluck('control_actions.id')->all();
            $this->assertSame($expected, $ids);
        }
    }

    public function testHistoryCrudFiltersUseThePolymorphicSubjectColumns(): void
    {
        $request = Request::create('/', 'GET', [
            'filter' => ['object_id' => 7, 'object_type' => Risk::class],
        ]);
        $query = QueryBuilder::for(ActivityLog::query(), $request)
            ->allowedFilters(...ActivityLog::getAllowedFilters());

        $this->assertStringContainsString('subject_id', $query->toSql());
        $this->assertStringContainsString('subject_type', $query->toSql());
        $this->assertSame([7, Risk::class], $query->getBindings());
    }
}
