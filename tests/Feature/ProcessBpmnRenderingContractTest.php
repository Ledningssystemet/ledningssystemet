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

class ProcessBpmnRenderingContractTest extends TestCase
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

    public function test_process_crud_endpoint_returns_custom_bpmn_rendering_extensions_unchanged(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Process Renderer', 'process.renderer@example.com'), 'sanctum');

        $prefix = 'Process Renderer '.Str::lower((string) Str::uuid());
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $xml = $this->renderedBpmnXml();

        DB::table('processes')->insert([
            'name' => $prefix.' process',
            'department_id' => $departmentId,
            'publishedbpmn' => $xml,
            'isstartprocess' => true,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/crud/processes?paginate=0&search='.urlencode($prefix).'&%24select=id,publishedbpmn');
        $response->assertOk();

        $rows = $response->json();
        $this->assertCount(1, $rows);
        $this->assertSame($xml, $rows[0]['publishedbpmn']);
    }

    private function renderedBpmnXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:ledning="https://ledningssystemet.se/schema/bpmn-style/1.0"
                  id="Definitions_1"
                  targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
    <bpmn:task id="Task_1" name="Styled task">
      <bpmn:extensionElements>
        <ledning:style textColor="#0f172a" fontSize="18" taskBackgroundImage="data:image/png;base64,iVBORw0KGgo=" taskBackgroundImageFit="contain" taskBackgroundImagePadding="8" />
      </bpmn:extensionElements>
    </bpmn:task>
    <bpmn:endEvent id="EndEvent_1" />
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_1" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_1" targetRef="EndEvent_1" />
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

