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

class ProcessesCrudEditorContractTest extends TestCase
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

    public function test_processes_index_supports_published_bpmn_preview_payload(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Processes Preview', 'processes.preview@example.com'), 'sanctum');

        $prefix = 'Processes Preview '.Str::lower((string) Str::uuid());
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('processes')->insert([
            'name' => $prefix.' process',
            'description' => 'Process description',
            'department_id' => $departmentId,
            'publishedbpmn' => '<definitions id="process-preview"></definitions>',
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/crud/processes?paginate=0&search='.urlencode($prefix).'&%24select=id,name,description,publishedbpmn');

        $response->assertOk();
        $rows = $response->json();

        $this->assertCount(1, $rows);
        $this->assertSame('<definitions id="process-preview"></definitions>', $rows[0]['publishedbpmn']);
        $this->assertSame('Process description', $rows[0]['description']);
    }

    public function test_processes_update_allows_editor_to_save_name_description_and_bpmn(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Processes Editor', 'processes.editor@example.com'), 'sanctum');

        $prefix = 'Processes Editor '.Str::lower((string) Str::uuid());
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = DB::table('processes')->insertGetId([
            'name' => $prefix.' before',
            'description' => 'Before update',
            'bpmn' => '<definitions id="before"></definitions>',
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'name' => $prefix.' after',
            'description' => 'After update',
            'bpmn' => '<definitions id="after"></definitions>',
        ];

        $response = $this->patchJson('/api/crud/processes/'.$processId, $payload);

        $response->assertOk()->assertJsonFragment($payload);

        $saved = DB::table('processes')->where('id', $processId)->first();

        $this->assertNotNull($saved);
        $this->assertSame($payload['name'], $saved->name);
        $this->assertSame($payload['description'], $saved->description);
        $this->assertSame($payload['bpmn'], $saved->bpmn);
    }

    public function test_processes_update_rejects_direct_changes_to_published_bpmn(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Processes Publisher', 'processes.publisher@example.com'), 'sanctum');

        $prefix = 'Processes Publisher '.Str::lower((string) Str::uuid());
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = DB::table('processes')->insertGetId([
            'name' => $prefix.' before',
            'description' => 'Before publish',
            'bpmn' => '<definitions id="draft-before"></definitions>',
            'publishedbpmn' => '<definitions id="published-before"></definitions>',
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'name' => $prefix.' after',
            'description' => 'After publish',
            'bpmn' => '<definitions id="after-publish"></definitions>',
            'publishedbpmn' => '<definitions id="after-publish"></definitions>',
        ];

        $response = $this->patchJson('/api/crud/processes/'.$processId, $payload);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['publishedbpmn'])
            ->assertJsonPath('errors.publishedbpmn.0', 'pages.process_editor.validation.publish_endpoint_required');

        $saved = DB::table('processes')->where('id', $processId)->first();

        $this->assertNotNull($saved);
        $this->assertSame($prefix.' before', $saved->name);
        $this->assertSame('Before publish', $saved->description);
        $this->assertSame('<definitions id="draft-before"></definitions>', $saved->bpmn);
        $this->assertSame('<definitions id="published-before"></definitions>', $saved->publishedbpmn);
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

