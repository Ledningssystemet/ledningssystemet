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

class ProcessPublishEndpointTest extends TestCase
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
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_publish_endpoint_updates_bpmn_and_publishedbpmn(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Publisher', 'process.publisher@example.com'), 'sanctum');

        $prefix = 'Process Publisher '.Str::lower((string) Str::uuid());
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $referencedProcessName = 'ReferencedProcess'.Str::random(8, 'abcdefghijklmnopqrstuvwxyz');

        DB::table('processes')->insert([
            'name' => $referencedProcessName,
            'description' => 'Process used by subProcess link',
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $xml = $this->validBpmnXml($referencedProcessName);

        $processId = DB::table('processes')->insertGetId([
            'name' => $prefix.' draft',
            'description' => 'Draft description',
            'department_id' => $departmentId,
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

        $saved = DB::table('processes')->where('id', $processId)->first();

        $this->assertNotNull($saved);
        $this->assertSame($xml, $saved->bpmn);
        $this->assertSame($xml, $saved->publishedbpmn);
        $this->assertSame($prefix.' draft', $saved->name);
        $this->assertSame('Draft description', $saved->description);
    }

    public function test_publish_endpoint_rejects_invalid_start_event_connection(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Validator', 'process.validator@example.com'), 'sanctum');

        $prefix = 'Process Validator '.Str::lower((string) Str::uuid());
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invalidXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
    <bpmn:endEvent id="EndEvent_1" />
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="EndEvent_1" />
  </bpmn:process>
</bpmn:definitions>
XML;

        $processId = DB::table('processes')->insertGetId([
            'name' => $prefix.' draft',
            'description' => 'Draft description',
            'department_id' => $departmentId,
            'bpmn' => $invalidXml,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/processes/'.$processId.'/publish', [
            'bpmn' => $invalidXml,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publishedbpmn'])
            ->assertJsonPath('errors.publishedbpmn.0', 'pages.process_editor.validation.invalid_sequence_connection');
    }

    public function test_publish_endpoint_rejects_unknown_subprocess_name(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Sub Process', 'process.subprocess@example.com'), 'sanctum');

        $prefix = 'Process Sub Process '.Str::lower((string) Str::uuid());
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

         $processId = DB::table('processes')->insertGetId([
             'name' => $prefix.' draft',
             'description' => 'Draft description',
             'department_id' => $departmentId,
             'isstartprocess' => false,
             'dataprocessor' => false,
             'created_at' => now(),
             'updated_at' => now(),
         ]);

         $xml = $this->validBpmnXml('Missing Linked Process');

         DB::table('processes')->where('id', $processId)->update(['bpmn' => $xml]);

         $response = $this->postJson('/api/processes/'.$processId.'/publish', [
             'bpmn' => $xml,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publishedbpmn'])
            ->assertJsonPath('errors.publishedbpmn.0', 'pages.process_editor.validation.sub_process_name_not_found');
    }

    public function test_publish_endpoint_rejects_unsaved_dirty_bpmn(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Dirty Draft', 'process.dirty-draft@example.com'), 'sanctum');

        $prefix = 'Process Dirty Draft '.Str::lower((string) Str::uuid());
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $referencedProcessName = 'ReferencedDraftProcess'.Str::random(8, 'abcdefghijklmnopqrstuvwxyz');

        DB::table('processes')->insert([
            'name' => $referencedProcessName,
            'description' => 'Process used by subProcess link',
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $savedXml = $this->validBpmnXml($referencedProcessName);
        $dirtyXml = str_replace('name="Work"', 'name="Review"', $savedXml);

        $processId = DB::table('processes')->insertGetId([
            'name' => $prefix.' draft',
            'description' => 'Draft description',
            'department_id' => $departmentId,
            'bpmn' => $savedXml,
            'publishedbpmn' => null,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/processes/'.$processId.'/publish', [
            'bpmn' => $dirtyXml,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publishedbpmn'])
            ->assertJsonPath('errors.publishedbpmn.0', 'pages.process_editor.validation.save_before_publish');

        $saved = DB::table('processes')->where('id', $processId)->first();

        $this->assertNotNull($saved);
        $this->assertSame($savedXml, $saved->bpmn);
        $this->assertNull($saved->publishedbpmn);
    }

    private function validBpmnXml(string $subProcessName): string
    {
        return str_replace('__SUB_PROCESS_NAME__', $subProcessName, <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
    <bpmn:task id="Task_1" name="Work" />
    <bpmn:endEvent id="EndEvent_1" />
    <bpmn:exclusiveGateway id="Gateway_1" />
    <bpmn:task id="Task_2" name="Decide" />
    <bpmn:dataObjectReference id="DataObject_1" name="Order" />
    <bpmn:dataStoreReference id="DataStore_1" name="Archive" />
    <bpmn:subProcess id="SubProcess_1" name="__SUB_PROCESS_NAME__" />
    <bpmn:textAnnotation id="Text_1" />

    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_1" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_1" targetRef="Gateway_1" />
    <bpmn:sequenceFlow id="Flow_3" sourceRef="Gateway_1" targetRef="Task_2" />
    <bpmn:sequenceFlow id="Flow_4" sourceRef="Task_2" targetRef="EndEvent_1" />

    <bpmn:association id="Assoc_1" sourceRef="Task_1" targetRef="DataObject_1" />
    <bpmn:association id="Assoc_2" sourceRef="DataObject_1" targetRef="DataStore_1" />
    <bpmn:association id="Assoc_3" sourceRef="Task_2" targetRef="SubProcess_1" />
    <bpmn:association id="Assoc_4" sourceRef="Text_1" targetRef="Task_1" />
  </bpmn:process>
</bpmn:definitions>
XML
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

