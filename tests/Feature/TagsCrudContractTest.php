<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TagsCrudContractTest extends TestCase
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
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_tags_index_and_create_are_available_for_authenticated_users(): void
    {
        $this->actingAs($this->createUser('Tag Reader', 'tag.reader@example.com'), 'sanctum');

        DB::table('tags')->insert([
            'name' => 'Existing tag',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indexResponse = $this->getJson('/api/crud/tags?paginate=0&%24select=id,name,created_at&sort=name');

        $indexResponse->assertOk();
        $indexResponse->assertJsonCount(1);
        $indexResponse->assertJsonPath('0.name', 'Existing tag');

        $createResponse = $this->postJson('/api/crud/tags', [
            'name' => 'Created tag',
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('name', 'Created tag');
        $this->assertDatabaseHas('tags', ['name' => 'Created tag']);
    }

    public function test_tags_can_be_updated_and_deleted(): void
    {
        $this->actingAs($this->createUser('Tag Manager', 'tag.manager@example.com'), 'sanctum');

        $tagId = DB::table('tags')->insertGetId([
            'name' => 'Editable tag',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patchJson('/api/crud/tags/'.$tagId, [
            'name' => 'Updated tag',
        ])->assertOk()->assertJsonPath('name', 'Updated tag');

        $this->deleteJson('/api/crud/tags/'.$tagId)->assertNoContent();

        $this->assertDatabaseMissing('tags', [
            'id' => $tagId,
        ]);
    }

    public function test_tags_index_returns_grouped_usage_information(): void
    {
        $this->actingAs($this->createUser('Tag Usage', 'tag.usage@example.com'), 'sanctum');

        $tagId = DB::table('tags')->insertGetId([
            'name' => 'Usage tag',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('object_tags')->insert([
            ['tag_id' => $tagId, 'object_tags_id' => 10, 'object_tags_type' => 'App\\Models\\Process'],
            ['tag_id' => $tagId, 'object_tags_id' => 11, 'object_tags_type' => 'App\\Models\\Process'],
            ['tag_id' => $tagId, 'object_tags_id' => 21, 'object_tags_type' => 'App\\Models\\InformationType'],
        ]);

        $response = $this->getJson('/api/crud/tags?paginate=0&%24select=id,name&search=Usage%20tag');

        $response->assertOk();
        $response->assertJsonCount(1);

        $usageInformation = (string) ($response->json('0.usageinformation') ?? '');
        $this->assertStringContainsString('2', $usageInformation);
        $this->assertStringContainsString('Process', $usageInformation);
        $this->assertStringContainsString('1', $usageInformation);
        $this->assertStringContainsString('Information', $usageInformation);

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
