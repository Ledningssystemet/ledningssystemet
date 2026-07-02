<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GenericCrudCustomPropertiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('custom_property_object')->truncate();
        DB::table('custom_properties')->truncate();
        DB::table('department_user')->truncate();
        DB::table('departments')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_index_and_show_include_custom_properties_as_regular_columns(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'custom-props-index@example.com'), 'sanctum');

        $customProperty = $this->createCustomProperty('external_code', 'string');
        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Custom Props Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('custom_property_object')->insert([
            'custom_property_id' => $customProperty,
            'object_id' => $departmentId,
            'object_type' => Department::class,
            'value' => 'DP-42',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $indexResponse = $this->getJson('/api/crud/departments?paginate=0&search=Custom%20Props&%24select=id,name,external_code');
        $indexResponse->assertOk();
        $indexResponse->assertJsonPath('0.external_code', 'DP-42');

        $showResponse = $this->getJson('/api/crud/departments/'.$departmentId.'?%24select=id,name,external_code');
        $showResponse->assertOk();
        $showResponse->assertJsonPath('external_code', 'DP-42');
    }

    public function test_store_and_update_persist_custom_properties_including_associated_model_values(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'custom-props-edit@example.com'), 'sanctum');

        $externalCodePropertyId = $this->createCustomProperty('external_code', 'string', true, 1);
        $backupDepartmentPropertyId = $this->createCustomProperty('backup_department_id', 'department', false, 2);

        $backupDepartmentId = DB::table('departments')->insertGetId([
            'name' => 'Backup Department',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $createResponse = $this->postJson('/api/crud/departments', [
            'name' => 'Created With Custom Props',
            'external_code' => 'INIT-100',
            'backup_department_id' => $backupDepartmentId,
        ]);

        $createResponse->assertCreated();
        $createdDepartmentId = (int) $createResponse->json('id');
        $createResponse->assertJsonPath('external_code', 'INIT-100');
        $createResponse->assertJsonPath('backup_department_id', $backupDepartmentId);

        $this->assertDatabaseHas('custom_property_object', [
            'custom_property_id' => $externalCodePropertyId,
            'object_id' => $createdDepartmentId,
            'object_type' => Department::class,
            'value' => 'INIT-100',
        ]);

        $this->assertDatabaseHas('custom_property_object', [
            'custom_property_id' => $backupDepartmentPropertyId,
            'object_id' => $createdDepartmentId,
            'object_type' => Department::class,
            'value' => (string) $backupDepartmentId,
        ]);

        $updateResponse = $this->patchJson('/api/crud/departments/'.$createdDepartmentId, [
            'external_code' => 'UPDATED-200',
            'backup_department_id' => null,
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('external_code', 'UPDATED-200');
        $this->assertNull($updateResponse->json('backup_department_id'));

        $this->assertDatabaseHas('custom_property_object', [
            'custom_property_id' => $externalCodePropertyId,
            'object_id' => $createdDepartmentId,
            'object_type' => Department::class,
            'value' => 'UPDATED-200',
        ]);

        $this->assertDatabaseMissing('custom_property_object', [
            'custom_property_id' => $backupDepartmentPropertyId,
            'object_id' => $createdDepartmentId,
            'object_type' => Department::class,
        ]);
    }

    public function test_index_can_filter_and_sort_by_custom_property_and_expose_metadata(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'custom-props-filter-sort@example.com'), 'sanctum');

        $customPropertyId = $this->createCustomProperty('external_code', 'string', false, 3);

        $firstDepartmentId = DB::table('departments')->insertGetId([
            'name' => 'Sort Department B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondDepartmentId = DB::table('departments')->insertGetId([
            'name' => 'Sort Department A',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('custom_property_object')->insert([
            [
                'custom_property_id' => $customPropertyId,
                'object_id' => $firstDepartmentId,
                'object_type' => Department::class,
                'value' => 'B-200',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'custom_property_id' => $customPropertyId,
                'object_id' => $secondDepartmentId,
                'object_type' => Department::class,
                'value' => 'A-100',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $filterResponse = $this->getJson('/api/crud/departments?paginate=0&filter[external_code]=A-100&%24select=id,name,external_code');
        $filterResponse->assertOk();
        $filterResponse->assertJsonCount(1);
        $filterResponse->assertJsonPath('0.id', $secondDepartmentId);

        $sortResponse = $this->getJson('/api/crud/departments?paginate=0&sort=external_code&%24select=id,name,external_code');
        $sortResponse->assertOk();
        $sortResponse->assertJsonPath('0.external_code', 'A-100');
        $sortResponse->assertJsonPath('1.external_code', 'B-200');

        $metadataResponse = $this->getJson('/api/crud/departments/metadata');
        $metadataResponse->assertOk();
        $metadataResponse->assertJsonPath('custom_properties.0.key', 'external_code');
        $metadataResponse->assertJsonPath('custom_properties.0.type', 'string');
        $metadataResponse->assertJsonPath('custom_properties.0.ordinal', 3);
    }

    public function test_index_search_matches_custom_property_values(): void
    {
        Gate::before(static fn (): bool => true);

        $this->actingAs($this->createUser('Auth User', 'custom-props-search@example.com'), 'sanctum');

        $customPropertyId = $this->createCustomProperty('external_code', 'string');

        $matchingDepartmentId = DB::table('departments')->insertGetId([
            'name' => 'Alpha Team',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nonMatchingDepartmentId = DB::table('departments')->insertGetId([
            'name' => 'Beta Team',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('custom_property_object')->insert([
            [
                'custom_property_id' => $customPropertyId,
                'object_id' => $matchingDepartmentId,
                'object_type' => Department::class,
                'value' => 'needle-123',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'custom_property_id' => $customPropertyId,
                'object_id' => $nonMatchingDepartmentId,
                'object_type' => Department::class,
                'value' => 'haystack-999',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->getJson('/api/crud/departments?paginate=0&search=needle-123&%24select=id,name,external_code');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.id', $matchingDepartmentId);
        $response->assertJsonPath('0.external_code', 'needle-123');
    }

    private function createCustomProperty(string $name, string $type, bool $required = false, int $ordinal = 1): int
    {
        return DB::table('custom_properties')->insertGetId([
            'name' => $name,
            'description' => 'Test custom property',
            'context' => Department::class,
            'type' => $type,
            'options' => null,
            'ordinal' => $ordinal,
            'display_on_card' => 0,
            'user_editable' => 1,
            'required' => $required ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(string $name, string $email): User
    {
        $uniqueEmail = str_replace('@', '+'.uniqid().'@', $email);

        $id = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $uniqueEmail,
            'password' => Hash::make('password'),
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->findOrFail($id);
    }
}

