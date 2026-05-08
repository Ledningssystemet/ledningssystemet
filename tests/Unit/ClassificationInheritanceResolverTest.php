<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\InformationType;
use App\Services\Classification\InheritedClassificationResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassificationInheritanceResolverTest extends TestCase
{
    private int $processId;

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

        if (! Schema::hasTable('processes')) {
            Schema::create('processes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->boolean('isstartprocess')->default(false);
                $table->boolean('dataprocessor')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('confidentiality_classes')) {
            Schema::create('confidentiality_classes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->unsignedInteger('ordinal');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('integrity_classes')) {
            Schema::create('integrity_classes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->unsignedInteger('ordinal');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('availability_classes')) {
            Schema::create('availability_classes', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->unsignedInteger('ordinal');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->unsignedBigInteger('confidentiality_class_id')->nullable();
                $table->unsignedBigInteger('integrity_class_id')->nullable();
                $table->unsignedBigInteger('availability_class_id')->nullable();
                $table->unsignedInteger('mtd')->nullable();
                $table->unsignedInteger('rpo')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('information_types')) {
            Schema::create('information_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->unsignedBigInteger('confidentiality_class_id')->nullable();
                $table->unsignedBigInteger('integrity_class_id')->nullable();
                $table->unsignedBigInteger('availability_class_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_information_type')) {
            Schema::create('asset_information_type', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->unsignedBigInteger('information_type_id');
                $table->unsignedBigInteger('process_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('asset_asset_dependancy')) {
            Schema::create('asset_asset_dependancy', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('dependant_asset_id');
                $table->unsignedBigInteger('depending_asset_id');
                $table->boolean('inherit_confidentiality')->default(false);
                $table->boolean('inherit_integrity')->default(false);
                $table->boolean('inherit_availability')->default(false);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        DB::table('asset_asset_dependancy')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('asset_information_type')->truncate();
        if (Schema::hasTable('processes')) {
            DB::table('processes')->truncate();
        }
        if (Schema::hasTable('departments')) {
            DB::table('departments')->truncate();
        }
        DB::table('information_types')->truncate();
        DB::table('assets')->truncate();
        DB::table('availability_classes')->truncate();
        DB::table('integrity_classes')->truncate();
        DB::table('confidentiality_classes')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Classification Test Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->processId = (int) DB::table('processes')->insertGetId([
            'name' => 'Classification Test Process',
            'description' => 'Process fixture for classification inheritance tests',
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        InheritedClassificationResolver::bumpCacheVersion();
    }

    public function test_asset_explicit_classification_overrides_inherited_values(): void
    {
        $low = $this->insertClass('confidentiality_classes', 'Low', 1);
        $high = $this->insertClass('confidentiality_classes', 'High', 5);

        $assetId = DB::table('assets')->insertGetId([
            'name' => 'Asset A',
            'confidentiality_class_id' => $low,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $informationTypeId = DB::table('information_types')->insertGetId([
            'name' => 'Info A',
            'confidentiality_class_id' => $high,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('asset_information_type')->insert([
            'asset_id' => $assetId,
            'information_type_id' => $informationTypeId,
            'process_id' => $this->processId,
        ]);

        $asset = Asset::query()->findOrFail($assetId);

        $this->assertSame($low, $asset->effective_confidentiality_class_id);
    }

    public function test_asset_inherits_highest_classification_from_information_types_and_dependencies(): void
    {
        $low = $this->insertClass('confidentiality_classes', 'Low', 1);
        $high = $this->insertClass('confidentiality_classes', 'High', 5);

        $targetAssetId = DB::table('assets')->insertGetId([
            'name' => 'Target Asset',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dependingAssetId = DB::table('assets')->insertGetId([
            'name' => 'Depending Asset',
            'confidentiality_class_id' => $high,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $informationTypeId = DB::table('information_types')->insertGetId([
            'name' => 'Info B',
            'confidentiality_class_id' => $low,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('asset_information_type')->insert([
            'asset_id' => $targetAssetId,
            'information_type_id' => $informationTypeId,
            'process_id' => $this->processId,
        ]);

        DB::table('asset_asset_dependancy')->insert([
            'dependant_asset_id' => $targetAssetId,
            'depending_asset_id' => $dependingAssetId,
            'inherit_confidentiality' => true,
            'inherit_integrity' => false,
            'inherit_availability' => false,
        ]);

        $asset = Asset::query()->findOrFail($targetAssetId);

        $this->assertSame($high, $asset->effective_confidentiality_class_id);
    }

    public function test_information_type_inherits_from_related_assets_and_handles_cycles(): void
    {
        $medium = $this->insertClass('integrity_classes', 'Medium', 3);

        $assetAId = DB::table('assets')->insertGetId([
            'name' => 'Asset A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assetBId = DB::table('assets')->insertGetId([
            'name' => 'Asset B',
            'integrity_class_id' => $medium,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('asset_asset_dependancy')->insert([
            'dependant_asset_id' => $assetAId,
            'depending_asset_id' => $assetBId,
            'inherit_confidentiality' => false,
            'inherit_integrity' => true,
            'inherit_availability' => false,
        ]);

        DB::table('asset_asset_dependancy')->insert([
            'dependant_asset_id' => $assetBId,
            'depending_asset_id' => $assetAId,
            'inherit_confidentiality' => false,
            'inherit_integrity' => true,
            'inherit_availability' => false,
        ]);

        $informationTypeId = DB::table('information_types')->insertGetId([
            'name' => 'Info C',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('asset_information_type')->insert([
            'asset_id' => $assetAId,
            'information_type_id' => $informationTypeId,
            'process_id' => $this->processId,
        ]);

        $informationType = InformationType::query()->findOrFail($informationTypeId);

        $this->assertSame($medium, $informationType->effective_integrity_class_id);
    }

    private function insertClass(string $table, string $name, int $ordinal): int
    {
        return (int) DB::table($table)->insertGetId([
            'name' => $name,
            'description' => $name.' description',
            'ordinal' => $ordinal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

