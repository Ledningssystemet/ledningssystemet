<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenericCrudPaginateBooleanStringTest extends TestCase
{
    protected $seed = true;

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
        if (Schema::hasTable('access_group_user')) {
            DB::table('access_group_user')->truncate();
        }
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_it_accepts_paginate_true_string_for_index_requests(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'auth-paginate@example.com', true), 'sanctum');
        $this->createUser('Paginate One', 'paginate.bool.one@example.com', true);
        $this->createUser('Paginate Two', 'paginate.bool.two@example.com', true);

        $response = $this->getJson('/api/crud/users?paginate=true&per_page=1&%24select=id,name');

        $response->assertOk();
        $response->assertJsonPath('per_page', 1);
        $response->assertJsonCount(1, 'data');
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

