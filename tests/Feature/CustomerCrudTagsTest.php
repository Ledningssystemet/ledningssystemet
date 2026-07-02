<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerCrudTagsTest extends TestCase
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

        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('legal_reg')->nullable();
                $table->string('ext_id')->nullable();
                $table->string('dpo_name')->nullable();
                $table->string('dpo_email')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 25);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('object_tags')) {
            Schema::create('object_tags', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tag_id');
                $table->unsignedBigInteger('object_tags_id');
                $table->string('object_tags_type');
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('object_tags')->truncate();
        DB::table('tags')->truncate();
        DB::table('customers')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_it_updates_customer_tags_and_persists_object_tag_links(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Tag Auth', 'tag.auth@example.com', true), 'sanctum');

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Tag Test Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->patchJson('/api/crud/customers/'.$customerId, [
            'tags' => ['ISO 9001', 'GDPR'],
        ]);

        $response->assertOk();
        $response->assertJsonPath('id', $customerId);

        $indexResponse = $this->getJson('/api/crud/customers?%24select=id,name');
        $indexResponse->assertOk();

        $rows = collect($indexResponse->json('data') ?? $indexResponse->json());
        $customer = $rows->firstWhere('id', $customerId);

        $this->assertNotNull($customer);
        $this->assertSame(['GDPR', 'ISO 9001'], collect($customer['tags'])->sort()->values()->all());

        $this->assertDatabaseHas('tags', ['name' => 'ISO 9001']);
        $this->assertDatabaseHas('tags', ['name' => 'GDPR']);

        $this->assertSame(
            2,
            DB::table('object_tags')
                ->where('object_tags_type', 'App\\Models\\Customer')
                ->where('object_tags_id', $customerId)
                ->count()
        );
    }

    public function test_it_can_clear_customer_tags_via_generic_crud_update(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Clear Auth', 'clear.auth@example.com', true), 'sanctum');

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Clear Tag Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson('/api/crud/customers/'.$customerId, [
            'tags' => ['ToClear'],
        ])->assertOk();

        $this->patchJson('/api/crud/customers/'.$customerId, [
            'tags' => [],
        ])->assertOk();

        $this->assertSame(
            0,
            DB::table('object_tags')
                ->where('object_tags_type', 'App\\Models\\Customer')
                ->where('object_tags_id', $customerId)
                ->count()
        );
    }

    public function test_it_supports_tag_options_and_creation_via_generic_crud_api(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Api Auth', 'api.auth@example.com', true), 'sanctum');

        DB::table('tags')->insert([
            'name' => 'ExistingTag',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indexResponse = $this->getJson('/api/crud/tags?%24select=id,name&sort=name');
        $indexResponse->assertOk();

        $createResponse = $this->postJson('/api/crud/tags', [
            'name' => 'CreatedViaApi',
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('name', 'CreatedViaApi');

        $this->assertDatabaseHas('tags', ['name' => 'CreatedViaApi']);
    }

    private function createUser(string $name, string $email, bool $enabled): User
    {
        $id = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'enabled' => $enabled,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}

