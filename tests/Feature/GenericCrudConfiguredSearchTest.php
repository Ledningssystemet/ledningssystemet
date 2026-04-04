<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GenericCrudConfiguredSearchTest extends TestCase
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

        if (! Schema::hasTable('generic_crud_search_resources')) {
            Schema::create('generic_crud_search_resources', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('generic_crud_search_tags')) {
            Schema::create('generic_crud_search_tags', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('generic_crud_search_tag_links')) {
            Schema::create('generic_crud_search_tag_links', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('generic_crud_search_resource_id');
                $table->unsignedBigInteger('generic_crud_search_tag_id');
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('generic_crud_search_tag_links')->truncate();
        DB::table('generic_crud_search_tags')->truncate();
        DB::table('generic_crud_search_resources')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        config()->set('generic_crud.resources.search-resources', GenericCrudSearchResource::class);
    }

    public function test_it_uses_configured_direct_search_fields_instead_of_all_text_fields(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Search Auth', 'search.auth@example.com', true), 'sanctum');

        $matchingId = DB::table('generic_crud_search_resources')->insertGetId([
            'name' => 'Alpha Resource',
            'description' => 'This description should not be searched directly.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('generic_crud_search_resources')->insert([
            'name' => 'Beta Resource',
            'description' => 'needle-in-description',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nameResponse = $this->getJson('/api/crud/search-resources?search=Alpha');
        $nameResponse->assertOk();
        $nameResponse->assertJsonCount(1);
        $nameResponse->assertJsonPath('0.id', $matchingId);

        $descriptionResponse = $this->getJson('/api/crud/search-resources?search=needle-in-description');
        $descriptionResponse->assertOk();
        $descriptionResponse->assertJsonCount(0);
    }

    public function test_it_can_search_through_configured_nested_relations(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Relation Auth', 'relation.auth@example.com', true), 'sanctum');

        $matchingId = DB::table('generic_crud_search_resources')->insertGetId([
            'name' => 'Tagged Resource',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherId = DB::table('generic_crud_search_resources')->insertGetId([
            'name' => 'Other Resource',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $matchingTagId = DB::table('generic_crud_search_tags')->insertGetId([
            'name' => 'GDPR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherTagId = DB::table('generic_crud_search_tags')->insertGetId([
            'name' => 'Finance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('generic_crud_search_tag_links')->insert([
            [
                'generic_crud_search_resource_id' => $matchingId,
                'generic_crud_search_tag_id' => $matchingTagId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'generic_crud_search_resource_id' => $otherId,
                'generic_crud_search_tag_id' => $otherTagId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/crud/search-resources?search=GDPR');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $matchingId);
        $response->assertJsonPath('0.name', 'Tagged Resource');
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

class GenericCrudSearchResource extends EloquentModel
{
    protected $table = 'generic_crud_search_resources';

    protected $guarded = [];

    public static function crudSearch(): array
    {
        return [
            'direct' => ['name'],
            'relations' => [
                'int_tag_links.int_tag' => ['name'],
            ],
        ];
    }

    public function int_tag_links(): HasMany
    {
        return $this->hasMany(GenericCrudSearchTagLink::class, 'generic_crud_search_resource_id', 'id');
    }
}

class GenericCrudSearchTagLink extends EloquentModel
{
    protected $table = 'generic_crud_search_tag_links';

    protected $guarded = [];

    public function int_resource(): BelongsTo
    {
        return $this->belongsTo(GenericCrudSearchResource::class, 'generic_crud_search_resource_id');
    }

    public function int_tag(): BelongsTo
    {
        return $this->belongsTo(GenericCrudSearchTag::class, 'generic_crud_search_tag_id');
    }
}

class GenericCrudSearchTag extends EloquentModel
{
    protected $table = 'generic_crud_search_tags';

    protected $guarded = [];
}

