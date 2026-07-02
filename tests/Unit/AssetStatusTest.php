<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Services\Classification\InheritedClassificationResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssetStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

        if (! Schema::hasTable('property_tabs')) {
            Schema::create('property_tabs', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('context');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('properties')) {
            Schema::create('properties', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('property_tab_id');
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('object_properties')) {
            Schema::create('object_properties', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('property_id');
                $table->string('object_properties_type');
                $table->unsignedBigInteger('object_properties_id');
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('object_properties')->truncate();
        DB::table('properties')->truncate();
        DB::table('property_tabs')->truncate();
        DB::table('asset_asset_dependancy')->truncate();
        DB::table('asset_information_type')->truncate();
        DB::table('information_types')->truncate();
        DB::table('assets')->truncate();
        DB::table('availability_classes')->truncate();
        DB::table('integrity_classes')->truncate();
        DB::table('confidentiality_classes')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        InheritedClassificationResolver::bumpCacheVersion();
    }

    public function test_get_items_status_counts_assets_missing_effective_classification(): void
    {
        DB::table('assets')->insert([
            'name' => 'Unclassified asset',
            'responsible_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $statuses = Asset::getItemsStatus();
        $classificationStatus = collect($statuses)->firstWhere('text', 'Asset without classification');

        $this->assertNotNull($classificationStatus);
        $this->assertSame(1, $classificationStatus['count']);
    }

    public function test_status_uses_inherited_effective_classifications(): void
    {
        $responsibleUserId = DB::table('users')->insertGetId([
            'name' => 'Asset Responsible',
            'email' => 'asset.responsible.'.uniqid('', true).'@example.com',
            'password' => 'secret',
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $confidentialityClassId = $this->insertClass('confidentiality_classes', 'Confidential', 3);
        $integrityClassId = $this->insertClass('integrity_classes', 'Correct', 2);
        $availabilityClassId = $this->insertClass('availability_classes', 'Available', 4);

        $assetId = DB::table('assets')->insertGetId([
            'name' => 'Inherited asset',
            'responsible_user_id' => $responsibleUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $informationTypeId = DB::table('information_types')->insertGetId([
            'name' => 'Inherited information type',
            'confidentiality_class_id' => $confidentialityClassId,
            'integrity_class_id' => $integrityClassId,
            'availability_class_id' => $availabilityClassId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Inherited Department '.uniqid('', true),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = DB::table('processes')->insertGetId([
            'name' => 'Inherited Process '.uniqid('', true),
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('asset_information_type')->insert([
            'asset_id' => $assetId,
            'information_type_id' => $informationTypeId,
            'process_id' => $processId,
        ]);

        $asset = Asset::query()->findOrFail($assetId);
        $status = $asset->status;

        $this->assertSame($confidentialityClassId, $asset->effective_confidentiality_class_id);
        $this->assertSame($integrityClassId, $asset->effective_integrity_class_id);
        $this->assertSame($availabilityClassId, $asset->effective_availability_class_id);
        $this->assertSame('success', $status['level']);
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
