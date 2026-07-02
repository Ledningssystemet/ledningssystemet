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

class DashboardProcessBpmnContractTest extends TestCase
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

    public function test_dashboard_process_endpoint_exposes_bpmn_payload_and_start_process_filter(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Dashboard BPMN', 'dashboard.bpmn@example.com'), 'sanctum');

        $prefix = 'Dashboard BPMN '.Str::lower((string) Str::uuid());
        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $startProcessId = DB::table('processes')->insertGetId([
            'name' => $prefix.' start',
            'department_id' => $departmentId,
            'publishedbpmn' => '<definitions id="dashboard-start"></definitions>',
            'isstartprocess' => true,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('processes')->insert([
            'name' => $prefix.' secondary',
            'department_id' => $departmentId,
            'publishedbpmn' => '<definitions id="dashboard-secondary"></definitions>',
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/crud/processes?paginate=0&search='.urlencode($prefix).'&%24select=id,name,isstartprocess,publishedbpmn');
        $response->assertOk();

        $rows = $response->json();

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'isstartprocess', 'publishedbpmn', 'status'],
            array_keys($rows[0])
        );

        $startResponse = $this->getJson('/api/crud/processes?paginate=0&search='.urlencode($prefix).'&filter[isstartprocess]=true&%24select=id,name,isstartprocess,publishedbpmn');
        $startResponse->assertOk();
        $startRows = $startResponse->json();

        $this->assertCount(1, $startRows);
        $this->assertSame($startProcessId, $startRows[0]['id']);
        $this->assertTrue($startRows[0]['isstartprocess']);
        $this->assertSame('<definitions id="dashboard-start"></definitions>', $startRows[0]['publishedbpmn']);
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
