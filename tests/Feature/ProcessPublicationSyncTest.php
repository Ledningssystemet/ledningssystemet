<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProcessPublicationSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('enabled')->default(true);
                $table->string('remember_token', 100)->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('access_group_user')) {
            DB::table('access_group_user')->truncate();
        }
        DB::table('users')->truncate();
        DB::table('process_links')->truncate();
        DB::table('information_type_process_activity')->truncate();
        DB::table('asset_information_type')->truncate();
        DB::table('process_activities')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_publish_endpoint_syncs_process_activities_information_types_assets_and_process_links(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Publication', 'process.publication@example.com'), 'sanctum');

        $context = $this->createProcessContext('Process Publication');
        $linkedProcessName = 'Linked Process '.Str::random(10);

        DB::table('processes')->insert([
            'name' => $linkedProcessName,
            'description' => 'Linked process target',
            'department_id' => $context['department_id'],
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $informationTypeName = 'Published Information '.Str::random(8);
        $assetName = 'Published Asset '.Str::random(8);
        $xml = $this->validBpmnXml($linkedProcessName, $informationTypeName, $assetName);

        $processId = DB::table('processes')->insertGetId([
            'name' => $context['prefix'].' draft',
            'description' => 'Draft before publish',
            'department_id' => $context['department_id'],
            'bpmn' => $xml,
            'publishedbpmn' => null,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/processes/'.$processId.'/publish', [
            'bpmn' => $xml,
        ]);

        $response->assertOk();

        $process = DB::table('processes')->where('id', $processId)->first();
        $this->assertNotNull($process);
        $this->assertSame($xml, $process->publishedbpmn);

        $activities = DB::table('process_activities')
            ->where('process_id', $processId)
            ->orderBy('ordinal')
            ->get();

        $this->assertCount(2, $activities);
        $this->assertSame(['Receive Request', 'Review Request'], $activities->pluck('name')->all());

        $informationType = DB::table('information_types')->where('name', $informationTypeName)->first();
        $asset = DB::table('assets')->where('name', $assetName)->first();

        $this->assertNotNull($informationType);
        $this->assertNotNull($asset);

        $activityIds = $activities->pluck('id')->all();

        $informationTypeLinks = DB::table('information_type_process_activity')
            ->where('information_type_id', $informationType->id)
            ->whereIn('process_activity_id', $activityIds)
            ->get();

        $this->assertCount(2, $informationTypeLinks);

        $assetInformationTypeLinks = DB::table('asset_information_type')
            ->where('process_id', $processId)
            ->where('information_type_id', $informationType->id)
            ->where('asset_id', $asset->id)
            ->get();

        $this->assertCount(1, $assetInformationTypeLinks);

        $linkedProcessId = (int) DB::table('processes')->where('name', $linkedProcessName)->value('id');

        $processLinks = DB::table('process_links')
            ->where('process_id', $processId)
            ->pluck('linked_process_id')
            ->all();

        $this->assertSame([$linkedProcessId], $processLinks);
    }

    public function test_republishing_process_replaces_process_scoped_publication_artifacts(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Republishing', 'process.republishing@example.com'), 'sanctum');

        $context = $this->createProcessContext('Process Republishing');
        $firstLinkedProcessName = 'Linked First '.Str::random(8);
        $secondLinkedProcessName = 'Linked Second '.Str::random(8);

        foreach ([$firstLinkedProcessName, $secondLinkedProcessName] as $linkedProcessName) {
            DB::table('processes')->insert([
                'name' => $linkedProcessName,
                'description' => 'Linked process target',
                'department_id' => $context['department_id'],
                'isstartprocess' => false,
                'dataprocessor' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $firstInformationTypeName = 'Republish Information A '.Str::random(6);
        $firstAssetName = 'Republish Asset A '.Str::random(6);
        $firstXml = $this->validBpmnXml($firstLinkedProcessName, $firstInformationTypeName, $firstAssetName);

        $processId = DB::table('processes')->insertGetId([
            'name' => $context['prefix'].' draft',
            'description' => 'Draft before republish',
            'department_id' => $context['department_id'],
            'bpmn' => $firstXml,
            'publishedbpmn' => null,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/processes/'.$processId.'/publish', [
            'bpmn' => $firstXml,
        ])->assertOk();

        $secondInformationTypeName = 'Republish Information B '.Str::random(6);
        $secondAssetName = 'Republish Asset B '.Str::random(6);
        $secondXml = $this->updatedBpmnXml($secondLinkedProcessName, $secondInformationTypeName, $secondAssetName);

        DB::table('processes')->where('id', $processId)->update([
            'bpmn' => $secondXml,
            'updated_at' => now(),
        ]);

        $this->postJson('/api/processes/'.$processId.'/publish', [
            'bpmn' => $secondXml,
        ])->assertOk();

        $activities = DB::table('process_activities')
            ->where('process_id', $processId)
            ->orderBy('ordinal')
            ->pluck('name')
            ->all();

        $this->assertSame(['Approve Request'], $activities);

        $linkedProcessIds = DB::table('process_links')
            ->where('process_id', $processId)
            ->pluck('linked_process_id')
            ->all();

        $expectedLinkedProcessId = (int) DB::table('processes')->where('name', $secondLinkedProcessName)->value('id');
        $this->assertSame([$expectedLinkedProcessId], $linkedProcessIds);

        $currentInformationTypeId = DB::table('information_types')->where('name', $secondInformationTypeName)->value('id');
        $currentAssetId = DB::table('assets')->where('name', $secondAssetName)->value('id');

        $this->assertNotNull($currentInformationTypeId);
        $this->assertNotNull($currentAssetId);

        $assetInformationTypeRows = DB::table('asset_information_type')
            ->where('process_id', $processId)
            ->get(['asset_id', 'information_type_id']);

        $this->assertCount(1, $assetInformationTypeRows);
        $this->assertSame((int) $currentAssetId, (int) $assetInformationTypeRows[0]->asset_id);
        $this->assertSame((int) $currentInformationTypeId, (int) $assetInformationTypeRows[0]->information_type_id);

        $currentProcessActivityIds = DB::table('process_activities')
            ->where('process_id', $processId)
            ->pluck('id');

        $informationTypeProcessActivityRows = DB::table('information_type_process_activity')
            ->whereIn('process_activity_id', $currentProcessActivityIds)
            ->get();

        $this->assertCount(1, $informationTypeProcessActivityRows);
        $this->assertSame((int) $currentInformationTypeId, (int) $informationTypeProcessActivityRows[0]->information_type_id);
    }

    public function test_publish_reuses_existing_information_type_and_asset_without_creating_duplicates(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Reuse', 'process.reuse@example.com'), 'sanctum');

        $context = $this->createProcessContext('Process Reuse');
        $linkedProcessName = 'Reuse Linked Process '.Str::random(8);

        DB::table('processes')->insert([
            'name' => $linkedProcessName,
            'description' => 'Linked process for reuse test',
            'department_id' => $context['department_id'],
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $informationTypeName = 'Reuse Information Type '.Str::random(6);
        $assetName = 'Reuse Asset '.Str::random(6);

        $existingInformationTypeId = DB::table('information_types')->insertGetId([
            'name' => $informationTypeName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $existingAssetId = DB::table('assets')->insertGetId([
            'name' => $assetName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $xml = $this->validBpmnXml($linkedProcessName, $informationTypeName, $assetName);

        $processId = DB::table('processes')->insertGetId([
            'name' => $context['prefix'].' draft',
            'description' => 'Draft for reuse test',
            'department_id' => $context['department_id'],
            'bpmn' => $xml,
            'publishedbpmn' => null,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/processes/'.$processId.'/publish', [
            'bpmn' => $xml,
        ])->assertOk();

        $informationTypeCount = DB::table('information_types')
            ->where('name', $informationTypeName)
            ->count();

        $this->assertSame(1, $informationTypeCount, 'Publishing must reuse the existing InformationType, not create a duplicate');

        $assetCount = DB::table('assets')
            ->where('name', $assetName)
            ->count();

        $this->assertSame(1, $assetCount, 'Publishing must reuse the existing Asset, not create a duplicate');

        $assetInformationTypeLink = DB::table('asset_information_type')
            ->where('process_id', $processId)
            ->where('information_type_id', $existingInformationTypeId)
            ->where('asset_id', $existingAssetId)
            ->first();

        $this->assertNotNull($assetInformationTypeLink, 'An asset_information_type row must link the existing records to the process');
    }

    public function test_republishing_one_process_does_not_remove_another_process_sync_data(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Isolation', 'process.isolation@example.com'), 'sanctum');

        $contextA = $this->createProcessContext('Process Isolation A');
        $contextB = $this->createProcessContext('Process Isolation B');

        $linkedProcessNameA = 'Isolation Linked A '.Str::random(6);
        $linkedProcessNameB = 'Isolation Linked B '.Str::random(6);

        foreach ([$linkedProcessNameA, $linkedProcessNameB] as $linkedName) {
            DB::table('processes')->insert([
                'name' => $linkedName,
                'description' => 'Linked process for isolation test',
                'department_id' => $contextA['department_id'],
                'isstartprocess' => false,
                'dataprocessor' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $infoTypeNameA = 'Isolation Info A '.Str::random(6);
        $assetNameA = 'Isolation Asset A '.Str::random(6);
        $xmlA = $this->validBpmnXml($linkedProcessNameA, $infoTypeNameA, $assetNameA);

        $processIdA = DB::table('processes')->insertGetId([
            'name' => $contextA['prefix'].' draft',
            'description' => 'Draft for isolation test A',
            'department_id' => $contextA['department_id'],
            'bpmn' => $xmlA,
            'publishedbpmn' => null,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/processes/'.$processIdA.'/publish', [
            'bpmn' => $xmlA,
        ])->assertOk();

        $infoTypeNameB = 'Isolation Info B '.Str::random(6);
        $assetNameB = 'Isolation Asset B '.Str::random(6);
        $xmlB = $this->validBpmnXml($linkedProcessNameB, $infoTypeNameB, $assetNameB);

        $processIdB = DB::table('processes')->insertGetId([
            'name' => $contextB['prefix'].' draft',
            'description' => 'Draft for isolation test B',
            'department_id' => $contextB['department_id'],
            'bpmn' => $xmlB,
            'publishedbpmn' => null,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/processes/'.$processIdB.'/publish', [
            'bpmn' => $xmlB,
        ])->assertOk();

        $infoTypeIdA = DB::table('information_types')->where('name', $infoTypeNameA)->value('id');
        $assetIdA = DB::table('assets')->where('name', $assetNameA)->value('id');

        $rowsForProcessA = DB::table('asset_information_type')
            ->where('process_id', $processIdA)
            ->where('information_type_id', $infoTypeIdA)
            ->where('asset_id', $assetIdA)
            ->count();

        $this->assertSame(1, $rowsForProcessA, 'Publishing process B must not remove asset_information_type rows belonging to process A');

        $updatedXmlB = $this->updatedBpmnXml($linkedProcessNameB, 'Isolation Info B Updated '.Str::random(6), 'Isolation Asset B Updated '.Str::random(6));

        DB::table('processes')->where('id', $processIdB)->update([
            'bpmn' => $updatedXmlB,
            'updated_at' => now(),
        ]);

        $this->postJson('/api/processes/'.$processIdB.'/publish', [
            'bpmn' => $updatedXmlB,
        ])->assertOk();

        $rowsForProcessAAfterRepublish = DB::table('asset_information_type')
            ->where('process_id', $processIdA)
            ->where('information_type_id', $infoTypeIdA)
            ->where('asset_id', $assetIdA)
            ->count();

        $this->assertSame(1, $rowsForProcessAAfterRepublish, 'Republishing process B must not remove asset_information_type rows belonging to process A');
    }

    public function test_renaming_asset_updates_data_store_names_in_bpmn_and_published_bpmn(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Asset Rename Sync', 'asset.rename.sync@example.com'), 'sanctum');

        $context = $this->createProcessContext('Asset Rename Sync');
        $linkedProcessName = 'Asset Rename Linked '.Str::random(8);

        DB::table('processes')->insert([
            'name' => $linkedProcessName,
            'description' => 'Linked process for asset rename sync test',
            'department_id' => $context['department_id'],
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $informationTypeName = 'Asset Rename Info '.Str::random(6);
        $assetName = 'Asset Rename Original '.Str::random(6);
        $xml = $this->validBpmnXml($linkedProcessName, $informationTypeName, $assetName);

        $processId = DB::table('processes')->insertGetId([
            'name' => $context['prefix'].' process',
            'description' => 'Process for asset rename sync',
            'department_id' => $context['department_id'],
            'bpmn' => $xml,
            'publishedbpmn' => null,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/processes/'.$processId.'/publish', ['bpmn' => $xml])->assertOk();

        $assetId = (int) DB::table('assets')->where('name', $assetName)->value('id');
        $renamedAsset = 'Asset Rename Updated '.Str::random(6);

        $this->patchJson('/api/crud/assets/'.$assetId, [
            'name' => $renamedAsset,
        ])->assertOk();

        $process = DB::table('processes')->where('id', $processId)->first(['bpmn', 'publishedbpmn']);
        $this->assertNotNull($process);
        $this->assertStringContainsString('name="'.$renamedAsset.'"', (string) $process->bpmn);
        $this->assertStringNotContainsString('name="'.$assetName.'"', (string) $process->bpmn);
        $this->assertStringContainsString('name="'.$renamedAsset.'"', (string) $process->publishedbpmn);
        $this->assertStringNotContainsString('name="'.$assetName.'"', (string) $process->publishedbpmn);
    }

    public function test_renaming_information_type_updates_data_object_names_in_bpmn_and_published_bpmn(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Information Type Rename Sync', 'information.type.rename.sync@example.com'), 'sanctum');

        $context = $this->createProcessContext('Information Type Rename Sync');
        $linkedProcessName = 'Information Rename Linked '.Str::random(8);

        DB::table('processes')->insert([
            'name' => $linkedProcessName,
            'description' => 'Linked process for information type rename sync test',
            'department_id' => $context['department_id'],
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $informationTypeName = 'Information Rename Original '.Str::random(6);
        $assetName = 'Information Rename Asset '.Str::random(6);
        $xml = $this->validBpmnXml($linkedProcessName, $informationTypeName, $assetName);

        $processId = DB::table('processes')->insertGetId([
            'name' => $context['prefix'].' process',
            'description' => 'Process for information type rename sync',
            'department_id' => $context['department_id'],
            'bpmn' => $xml,
            'publishedbpmn' => null,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/processes/'.$processId.'/publish', ['bpmn' => $xml])->assertOk();

        $informationTypeId = (int) DB::table('information_types')->where('name', $informationTypeName)->value('id');
        $renamedInformationType = 'Information Rename Updated '.Str::random(6);

        $this->patchJson('/api/crud/information_types/'.$informationTypeId, [
            'name' => $renamedInformationType,
        ])->assertOk();

        $process = DB::table('processes')->where('id', $processId)->first(['bpmn', 'publishedbpmn']);
        $this->assertNotNull($process);
        $this->assertStringContainsString('name="'.$renamedInformationType.'"', (string) $process->bpmn);
        $this->assertStringNotContainsString('name="'.$informationTypeName.'"', (string) $process->bpmn);
        $this->assertStringContainsString('name="'.$renamedInformationType.'"', (string) $process->publishedbpmn);
        $this->assertStringNotContainsString('name="'.$informationTypeName.'"', (string) $process->publishedbpmn);
    }

    public function test_renaming_process_updates_sub_process_names_in_linked_process_bpmn_and_published_bpmn(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Linked Process Rename Sync', 'linked.process.rename.sync@example.com'), 'sanctum');

        $context = $this->createProcessContext('Linked Process Rename Sync');
        $linkedProcessName = 'Linked Process Original '.Str::random(8);

        $linkedProcessId = DB::table('processes')->insertGetId([
            'name' => $linkedProcessName,
            'description' => 'Process that will be renamed',
            'department_id' => $context['department_id'],
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $informationTypeName = 'Linked Process Rename Info '.Str::random(6);
        $assetName = 'Linked Process Rename Asset '.Str::random(6);
        $xml = $this->validBpmnXml($linkedProcessName, $informationTypeName, $assetName);

        $parentProcessId = DB::table('processes')->insertGetId([
            'name' => $context['prefix'].' parent',
            'description' => 'Process containing sub process reference',
            'department_id' => $context['department_id'],
            'bpmn' => $xml,
            'publishedbpmn' => null,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/processes/'.$parentProcessId.'/publish', ['bpmn' => $xml])->assertOk();

        $renamedLinkedProcess = 'Linked Process Updated '.Str::random(8);

        $this->patchJson('/api/crud/processes/'.$linkedProcessId, [
            'name' => $renamedLinkedProcess,
        ])->assertOk();

        $parent = DB::table('processes')->where('id', $parentProcessId)->first(['bpmn', 'publishedbpmn']);
        $this->assertNotNull($parent);
        $this->assertStringContainsString('name="'.$renamedLinkedProcess.'"', (string) $parent->bpmn);
        $this->assertStringNotContainsString('name="'.$linkedProcessName.'"', (string) $parent->bpmn);
        $this->assertStringContainsString('name="'.$renamedLinkedProcess.'"', (string) $parent->publishedbpmn);
        $this->assertStringNotContainsString('name="'.$linkedProcessName.'"', (string) $parent->publishedbpmn);
    }

    public function test_generic_process_crud_cannot_update_published_bpmn_directly(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Crud Guard', 'process.crud.guard@example.com'), 'sanctum');

        $context = $this->createProcessContext('Process Crud Guard');
        $processId = DB::table('processes')->insertGetId([
            'name' => $context['prefix'].' process',
            'description' => 'Process description',
            'department_id' => $context['department_id'],
            'bpmn' => '<definitions id="draft"></definitions>',
            'publishedbpmn' => '<definitions id="published-before"></definitions>',
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->patchJson('/api/crud/processes/'.$processId, [
            'publishedbpmn' => '<definitions id="published-after"></definitions>',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publishedbpmn'])
            ->assertJsonPath('errors.publishedbpmn.0', 'pages.process_editor.validation.publish_endpoint_required');

        $saved = DB::table('processes')->where('id', $processId)->first();

        $this->assertNotNull($saved);
        $this->assertSame('<definitions id="published-before"></definitions>', $saved->publishedbpmn);
    }

    /**
     * @return array{department_id: int, prefix: string}
     */
    private function createProcessContext(string $prefix): array
    {
        $uniquePrefix = $prefix.' '.Str::lower((string) Str::uuid());

        $departmentId = DB::table('departments')->insertGetId([
            'name' => $uniquePrefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'department_id' => (int) $departmentId,
            'prefix' => $uniquePrefix,
        ];
    }

    private function validBpmnXml(string $subProcessName, string $informationTypeName, string $assetName): string
    {
        $template = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
    <bpmn:task id="Task_1" name="Receive Request" />
    <bpmn:task id="Task_2" name="Review Request" />
    <bpmn:endEvent id="EndEvent_1" />
    <bpmn:dataObjectReference id="DataObject_1" name="__INFORMATION_TYPE_NAME__" />
    <bpmn:dataObjectReference id="DataObject_2" name="__INFORMATION_TYPE_NAME__" />
    <bpmn:dataStoreReference id="DataStore_1" name="__ASSET_NAME__" />
    <bpmn:subProcess id="SubProcess_1" name="__SUB_PROCESS_NAME__" />

    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_1" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_1" targetRef="Task_2" />
    <bpmn:sequenceFlow id="Flow_3" sourceRef="Task_2" targetRef="EndEvent_1" />

    <bpmn:association id="Assoc_1" sourceRef="Task_1" targetRef="DataObject_1" />
    <bpmn:association id="Assoc_2" sourceRef="Task_2" targetRef="DataObject_2" />
    <bpmn:association id="Assoc_3" sourceRef="DataObject_1" targetRef="DataStore_1" />
    <bpmn:association id="Assoc_4" sourceRef="Task_2" targetRef="SubProcess_1" />
  </bpmn:process>
</bpmn:definitions>
XML;

        return str_replace(
            ['__SUB_PROCESS_NAME__', '__INFORMATION_TYPE_NAME__', '__ASSET_NAME__'],
            [$subProcessName, $informationTypeName, $assetName],
            $template,
        );
    }

    private function updatedBpmnXml(string $subProcessName, string $informationTypeName, string $assetName): string
    {
        $template = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="Definitions_2" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_2" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
    <bpmn:task id="Task_3" name="Approve Request" />
    <bpmn:endEvent id="EndEvent_1" />
    <bpmn:dataObjectReference id="DataObject_3" name="__INFORMATION_TYPE_NAME__" />
    <bpmn:dataStoreReference id="DataStore_2" name="__ASSET_NAME__" />
    <bpmn:subProcess id="SubProcess_2" name="__SUB_PROCESS_NAME__" />

    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_3" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_3" targetRef="EndEvent_1" />

    <bpmn:association id="Assoc_1" sourceRef="Task_3" targetRef="DataObject_3" />
    <bpmn:association id="Assoc_2" sourceRef="DataObject_3" targetRef="DataStore_2" />
    <bpmn:association id="Assoc_3" sourceRef="Task_3" targetRef="SubProcess_2" />
  </bpmn:process>
</bpmn:definitions>
XML;

        return str_replace(
            ['__SUB_PROCESS_NAME__', '__INFORMATION_TYPE_NAME__', '__ASSET_NAME__'],
            [$subProcessName, $informationTypeName, $assetName],
            $template,
        );
    }

    private function createUser(string $name, string $email): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}
