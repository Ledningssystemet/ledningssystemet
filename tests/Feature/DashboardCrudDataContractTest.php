<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardCrudDataContractTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $dashboardResources = [
        'activities',
        'control-actions',
        'objectives',
        'risks',
        'processes',
        'process-activities',
        'probability-levels',
        'consequence-levels',
        'risk-levels',
        'risk-level-mappings',
    ];

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

    public function test_dashboard_crud_endpoints_return_arrays_for_authorized_user(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Dashboard Auth', 'dashboard.auth@example.com'), 'sanctum');

        foreach ($this->dashboardResources as $resource) {
            $response = $this->getJson('/api/crud/'.$resource.'?paginate=0&%24select=id');
            $response->assertOk();

            $payload = $response->json();
            $rows = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;

            $this->assertIsArray($rows, "Expected array payload from /api/crud/{$resource}");
        }
    }

    public function test_dashboard_crud_endpoints_require_authentication(): void
    {
        foreach ($this->dashboardResources as $resource) {
            $this->getJson('/api/crud/'.$resource.'?paginate=0&%24select=id')->assertUnauthorized();
        }
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

