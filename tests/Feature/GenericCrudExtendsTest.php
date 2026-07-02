<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenericCrudExtendsTest extends TestCase
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

        if (! Schema::hasTable('object_messages')) {
            Schema::create('object_messages', function (Blueprint $table): void {
                $table->id();
                $table->text('comment');
                $table->string('created_by');
                $table->unsignedBigInteger('object_id');
                $table->string('object_type');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('object_histories')) {
            Schema::create('object_histories', function (Blueprint $table): void {
                $table->id();
                $table->string('action', 1);
                $table->json('modified')->nullable();
                $table->string('created_by')->nullable();
                $table->unsignedBigInteger('object_id');
                $table->string('object_type');
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('object_histories')->truncate();
        DB::table('object_messages')->truncate();
        DB::table('object_tags')->truncate();
        DB::table('tags')->truncate();
        DB::table('customers')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_it_can_extend_messages_tags_history_and_counts_on_index(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Extends Auth', 'extends.auth@example.com', true), 'sanctum');

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Extends Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tagId = DB::table('tags')->insertGetId([
            'name' => 'ISO 27001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('object_tags')->insert([
            'tag_id' => $tagId,
            'object_tags_id' => $customerId,
            'object_tags_type' => Customer::class,
        ]);

        DB::table('object_messages')->insert([
            [
                'comment' => 'First message',
                'created_by' => 'tester',
                'object_id' => $customerId,
                'object_type' => Customer::class,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'comment' => 'Second message',
                'created_by' => 'tester',
                'object_id' => $customerId,
                'object_type' => Customer::class,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('object_histories')->insert([
            'action' => 'U',
            'modified' => json_encode(['field' => 'name']),
            'created_by' => 'tester',
            'object_id' => $customerId,
            'object_type' => Customer::class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/crud/customers?paginate=0&%24select=id,name&extends=messages,messages_count,tags,tags_count,history,history_count');

        $response->assertOk();

        $rows = collect($response->json('data') ?? $response->json());
        $customer = $rows->firstWhere('id', $customerId);

        $this->assertNotNull($customer);
        $this->assertCount(2, $customer['messages']);
        $this->assertCount(1, $customer['int_object_tags_as_object_tags']);
        $this->assertCount(1, $customer['history']);
        $this->assertSame(2, $customer['messages_count']);
        $this->assertSame(1, $customer['tags_count']);
        $this->assertSame(1, $customer['history_count']);
    }

    public function test_it_can_extend_counts_on_show(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Show Extends Auth', 'show.extends.auth@example.com', true), 'sanctum');

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Show Extends Customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('object_messages')->insert([
            'comment' => 'Show message',
            'created_by' => 'tester',
            'object_id' => $customerId,
            'object_type' => Customer::class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/crud/customers/'.$customerId.'?%24select=id,name&extends=messages_count');

        $response->assertOk();
        $response->assertJsonPath('id', $customerId);
        $response->assertJsonPath('messages_count', 1);
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

