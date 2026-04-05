<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerCrudInlineTagsPatchTest extends TestCase
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

    public function test_partial_patch_for_inline_tags_keeps_other_customer_columns_unchanged(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Inline Auth', 'inline.auth@example.com', true), 'sanctum');

        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Inline Tag Customer',
            'legal_reg' => 'ORG-556677',
            'description' => 'Original description',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson('/api/crud/customers/'.$customerId, [
            'tags' => ['InlineTagOne', 'InlineTagTwo'],
        ])->assertOk();

        $customer = DB::table('customers')->where('id', $customerId)->first();

        $this->assertNotNull($customer);
        $this->assertSame('Inline Tag Customer', $customer->name);
        $this->assertSame('ORG-556677', $customer->legal_reg);
        $this->assertSame('Original description', $customer->description);

        $indexResponse = $this->getJson('/api/crud/customers?%24select=id,name');
        $indexResponse->assertOk();

        $rows = collect($indexResponse->json('data') ?? $indexResponse->json());
        $indexedCustomer = $rows->firstWhere('id', $customerId);

        $this->assertNotNull($indexedCustomer);
        $this->assertSame(
            ['InlineTagOne', 'InlineTagTwo'],
            collect($indexedCustomer['tags'])->sort()->values()->all()
        );
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

