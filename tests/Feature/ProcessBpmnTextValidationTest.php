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

class ProcessBpmnTextValidationTest extends TestCase
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

    public function test_process_crud_update_rejects_invalid_bpmn_text_content(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Draft Validator', 'process.draft.validator@example.com'), 'sanctum');

        $processId = $this->createProcessId('Process Draft Validator');

        $response = $this->patchJson('/api/crud/processes/'.$processId, [
            'bpmn' => $this->invalidNameBpmnXml(),
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bpmn'])
            ->assertJsonPath('errors.bpmn.0', 'pages.process_editor.validation.invalid_text_content');
    }

    public function test_publish_endpoint_rejects_text_annotations_with_line_breaks(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Publish Validator', 'process.publish.validator@example.com'), 'sanctum');

        $processId = $this->createProcessId('Process Publish Validator');

        $response = $this->postJson('/api/processes/'.$processId.'/publish', [
            'bpmn' => $this->invalidTextAnnotationBpmnXml(),
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publishedbpmn'])
            ->assertJsonPath('errors.publishedbpmn.0', 'pages.process_editor.validation.invalid_text_content');
    }

    private function createProcessId(string $prefix): int
    {
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' '.Str::lower((string) Str::uuid()).' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('processes')->insertGetId([
            'name' => $prefix.' draft',
            'description' => 'Draft description',
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function invalidNameBpmnXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
    <bpmn:task id="Task_1" name="Bad!" />
    <bpmn:endEvent id="EndEvent_1" />
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_1" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_1" targetRef="EndEvent_1" />
  </bpmn:process>
</bpmn:definitions>
XML;
    }

    private function invalidTextAnnotationBpmnXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
    <bpmn:task id="Task_1" name="Valid" />
    <bpmn:endEvent id="EndEvent_1" />
    <bpmn:textAnnotation id="Text_1">
      <bpmn:text>Line one
Line two</bpmn:text>
    </bpmn:textAnnotation>
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_1" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_1" targetRef="EndEvent_1" />
    <bpmn:association id="Assoc_1" sourceRef="Text_1" targetRef="Task_1" />
  </bpmn:process>
</bpmn:definitions>
XML;
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

