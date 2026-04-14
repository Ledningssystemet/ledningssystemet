<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChemicalsCrudDangerPropertiesTest extends TestCase
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

        if (! Schema::hasTable('chemicals')) {
            Schema::create('chemicals', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('manufacturer')->nullable();
                $table->mediumText('description')->nullable();
                $table->mediumText('usagedescription')->nullable();
                $table->mediumText('storagedescription')->nullable();
                $table->mediumText('consumptiondescription')->nullable();
                $table->mediumText('riskdescription')->nullable();
                $table->mediumText('handlingguidance')->nullable();
                $table->unsignedBigInteger('ohs_danger_properties')->default(0);
                $table->string('sdbfilename')->nullable();
                $table->string('sdbcontenttype')->nullable();
                $table->unsignedBigInteger('sdbcontentlength')->nullable();
                $table->binary('sdbfilecontent')->nullable();
                $table->timestamps();
            });
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('chemicals')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_chemical_create_update_and_index_handle_danger_property_array(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Chemicals Manager', 'chemicals.manager@example.com'), 'sanctum');

        $namePrefix = 'Chemical '.Str::lower((string) Str::uuid());

        $createResponse = $this->postJson('/api/crud/chemicals', [
            'name' => $namePrefix.' created',
            'danger' => ['ghs02', 'ghs05'],
        ]);

        $createResponse->assertCreated();

        $chemicalId = (int) $createResponse->json('id');
        $this->assertGreaterThan(0, $chemicalId);

        $this->assertDatabaseHas('chemicals', [
            'id' => $chemicalId,
            'ohs_danger_properties' => 18,
        ]);

        $createdDanger = $createResponse->json('danger');
        $this->assertSame(['ghs02', 'ghs05'], $createdDanger);

        $updateResponse = $this->patchJson('/api/crud/chemicals/'.$chemicalId, [
            'danger' => ['ghs01', 'ghs09'],
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('danger.0', 'ghs01');
        $updateResponse->assertJsonPath('danger.1', 'ghs09');

        $this->assertDatabaseHas('chemicals', [
            'id' => $chemicalId,
            'ohs_danger_properties' => 257,
        ]);

        $indexResponse = $this->getJson('/api/crud/chemicals?paginate=0&search='.urlencode($namePrefix).'&%24select=id,name,ohs_danger_properties');

        $indexResponse->assertOk();
        $rows = $indexResponse->json();

        $this->assertCount(1, $rows);
        $this->assertSame(['ghs01', 'ghs09'], $rows[0]['danger']);
        $this->assertSame(257, $rows[0]['ohs_danger_properties']);
    }

    public function test_chemical_can_upload_and_replace_pdf_safety_datasheet(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Chemicals Uploader', 'chemicals.uploader@example.com'), 'sanctum');

        $namePrefix = 'Chemical '.Str::lower((string) Str::uuid());

        $createResponse = $this->post('/api/crud/chemicals', [
            'name' => $namePrefix.' with file',
            'sdbfile' => UploadedFile::fake()->create('sdb-create.pdf', 32, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $createResponse->assertCreated();

        $chemicalId = (int) $createResponse->json('id');
        $this->assertGreaterThan(0, $chemicalId);

        $created = DB::table('chemicals')->where('id', $chemicalId)->first();
        $this->assertNotNull($created);
        $this->assertSame('sdb-create.pdf', $created->sdbfilename);
        $this->assertSame('application/pdf', $created->sdbcontenttype);
        $this->assertNotNull($created->sdbfilecontent);

        $updateResponse = $this->post('/api/crud/chemicals/'.$chemicalId, [
            '_method' => 'PUT',
            'sdbfile' => UploadedFile::fake()->create('sdb-update.pdf', 48, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('sdbfilename', 'sdb-update.pdf');

        $updated = DB::table('chemicals')->where('id', $chemicalId)->first();
        $this->assertNotNull($updated);
        $this->assertSame('sdb-update.pdf', $updated->sdbfilename);
        $this->assertSame('application/pdf', $updated->sdbcontenttype);
        $this->assertNotNull($updated->sdbfilecontent);
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

