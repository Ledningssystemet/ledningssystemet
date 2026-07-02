<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Risk;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RiskContextPlaceholderAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('risks')) {
            Schema::create('risks', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('context_type')->nullable();
                $table->unsignedBigInteger('department_id')->default(1);
                $table->unsignedBigInteger('context_id')->nullable();
                $table->text('scenariodescription')->nullable();
                $table->text('consequencedescription')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('risks')->truncate();
        DB::table('departments')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_translated_attributes_replace_name_placeholder_case_insensitively(): void
    {
        $department = new Department(['name' => 'IT Drift']);

        $risk = new Risk([
            'name' => 'Risk for {NAME}',
            'scenariodescription' => 'Scenario: {name} / {NaMe}',
            'consequencedescription' => 'Consequence in {nAmE}',
            'context_type' => Department::class,
            'context_id' => 123,
        ]);

        $risk->setRelation('int_context', $department);

        $this->assertContains('translated_name', $risk->getAppends());
        $this->assertContains('translated_scenariodescription', $risk->getAppends());
        $this->assertContains('translated_consequencedescription', $risk->getAppends());

        $this->assertSame('Risk for IT Drift', $risk->translated_name);
        $this->assertSame('Scenario: IT Drift / IT Drift', $risk->translated_scenariodescription);
        $this->assertSame('Consequence in IT Drift', $risk->translated_consequencedescription);

        // Keep existing accessor behavior for name while making replacement case-insensitive.
        $this->assertSame('Risk for IT Drift', $risk->name);
    }

    public function test_translated_attributes_fall_back_to_original_value_when_context_cannot_be_resolved(): void
    {
        $risk = new Risk([
            'name' => 'Legacy {name}',
            'scenariodescription' => 'Scenario {NAME}',
            'consequencedescription' => 'Consequence {name}',
            'context_type' => 'App\\Models\\RemovedContextModel',
            'context_id' => 1,
        ]);

        $this->assertSame('Legacy {name}', $risk->translated_name);
        $this->assertSame('Scenario {NAME}', $risk->translated_scenariodescription);
        $this->assertSame('Consequence {name}', $risk->translated_consequencedescription);
        $this->assertSame('Legacy {name}', $risk->name);
    }

    public function test_translated_attributes_lazy_load_context_type_and_context_id_when_missing_from_select(): void
    {
        // Create department first
        $department = new Department(['name' => 'Security Team']);
        $department->timestamps = false;
        $deptId = DB::table('departments')->insertGetId([
            'name' => 'Security Team',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create risk with context
        $riskId = DB::table('risks')->insertGetId([
            'name' => 'Risk for {name}',
            'context_type' => Department::class,
            'context_id' => $deptId,
            'scenariodescription' => 'Scenario {NAME}',
            'consequencedescription' => 'Consequence {name}',
            'department_id' => $deptId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Fetch risk without context_type and context_id in select
        // This simulates the user's scenario where $select doesn't include these fields
        $risk = Risk::query()
            ->select(['id', 'name', 'scenariodescription', 'consequencedescription', 'department_id'])
            ->find($riskId);

        // Verify fields are not in attributes before accessing translated attributes
        $this->assertNull($risk->getAttributes()['context_type'] ?? null);
        $this->assertNull($risk->getAttributes()['context_id'] ?? null);

        // Access translated attributes (triggers lazy-load internally)
        $translatedName = $risk->translated_name;
        $translatedScenario = $risk->translated_scenariodescription;
        $translatedConsequence = $risk->translated_consequencedescription;

        // Verify lazy-loaded attributes work correctly
        $this->assertSame('Risk for Security Team', $translatedName);
        $this->assertSame('Scenario Security Team', $translatedScenario);
        $this->assertSame('Consequence Security Team', $translatedConsequence);

        // IMPORTANT: After lazy-load and accessing translated attributes,
        // context_type and context_id should still NOT be in final attributes
        // (they were only used internally, then cleaned up)
        $this->assertNull($risk->getAttributes()['context_type'] ?? null);
        $this->assertNull($risk->getAttributes()['context_id'] ?? null);
    }
}

