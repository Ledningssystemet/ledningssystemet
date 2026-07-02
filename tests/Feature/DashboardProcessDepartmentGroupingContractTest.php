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

class DashboardProcessDepartmentGroupingContractTest extends TestCase
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

    public function test_dashboard_process_endpoint_exposes_department_id_for_grouped_dropdown(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Dashboard Process Grouping', 'dashboard.process.grouping@example.com'), 'sanctum');

        $prefix = 'Dashboard Process Grouping '.Str::lower((string) Str::uuid());

        $departmentId = DB::table('departments')->insertGetId([
            'name' => $prefix.' department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $processId = DB::table('processes')->insertGetId([
            'name' => $prefix.' process',
            'department_id' => $departmentId,
            'isstartprocess' => false,
            'dataprocessor' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/crud/processes?paginate=0&search='.urlencode($prefix).'&%24select=id,name,department_id');
        $response->assertOk();

        $rows = $response->json();

        $this->assertCount(1, $rows);
        $this->assertSame($processId, $rows[0]['id']);
        $this->assertSame($departmentId, $rows[0]['department_id']);
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

