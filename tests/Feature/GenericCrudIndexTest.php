<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenericCrudIndexTest extends TestCase
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
                $table->string('external_id')->nullable();
                $table->string('title')->nullable();
                $table->unsignedBigInteger('manager_user_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_it_forbids_index_when_view_any_is_denied(): void
    {
        $this->actingAs($this->createUser('Auth User', 'auth@example.com', true), 'sanctum');

        $response = $this->getJson('/api/crud/users');

        $response->assertForbidden();
    }

    public function test_it_can_filter_search_and_select_without_pagination(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'auth2@example.com', true), 'sanctum');
        $this->createUser('Alice Filter', 'alice@example.com', true);
        $this->createUser('Alice Disabled', 'alice.disabled@example.com', false);
        $this->createUser('Bob Example', 'bob@example.com', true);

        $response = $this->getJson('/api/crud/users?paginate=0&filter[enabled]=1&search=Alice&%24select=id,name,enabled');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', 'Alice Filter');

        $firstRow = $response->json()[0];
        $this->assertSame(['id', 'name', 'enabled'], array_keys($firstRow));
    }

    public function test_it_can_paginate_when_requested(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'auth3@example.com', true), 'sanctum');
        $this->createUser('Paginate One', 'paginate.one@example.com', true);
        $this->createUser('Paginate Two', 'paginate.two@example.com', true);

        $response = $this->getJson('/api/crud/users?paginate=1&per_page=1&%24select=id,name');

        $response->assertOk();
        $response->assertJsonPath('per_page', 1);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_it_can_store_model_via_generic_endpoint(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'auth4@example.com', true), 'sanctum');

        $response = $this->postJson('/api/crud/users', [
            'name' => 'Created User',
            'email' => 'created.user@example.com',
            'password' => 'secret123',
            'enabled' => true,
            'title' => 'Tester',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'Created User');
        $this->assertDatabaseHas('users', [
            'email' => 'created.user@example.com',
            'name' => 'Created User',
        ]);
    }

    public function test_it_can_show_model_with_selected_columns(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'auth5@example.com', true), 'sanctum');
        $created = $this->createUser('Shown User', 'shown.user@example.com', true);

        $response = $this->getJson('/api/crud/users/'.$created->id.'?%24select=id,name,email');

        $response->assertOk();
        $response->assertJsonPath('id', $created->id);
        $response->assertJsonPath('email', 'shown.user@example.com');
        $this->assertSame(['id', 'name', 'email'], array_keys($response->json()));
    }

    public function test_it_can_update_model_via_generic_endpoint(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'auth6@example.com', true), 'sanctum');
        $created = $this->createUser('Update User', 'update.user@example.com', true);

        $response = $this->patchJson('/api/crud/users/'.$created->id, [
            'title' => 'Updated Title',
            'enabled' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('title', 'Updated Title');
        $response->assertJsonPath('enabled', false);
        $this->assertDatabaseHas('users', [
            'id' => $created->id,
            'title' => 'Updated Title',
            'enabled' => 0,
        ]);
    }

    public function test_it_can_destroy_model_via_generic_endpoint(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'auth7@example.com', true), 'sanctum');
        $created = $this->createUser('Delete User', 'delete.user@example.com', true);

        $response = $this->deleteJson('/api/crud/users/'.$created->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $created->id]);
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

