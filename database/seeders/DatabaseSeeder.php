<?php

namespace Database\Seeders;

use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'enabled' => true,
        ]);

        $accessGroup = AccessGroup::query()->create([
            'name' => 'E2E Management Access',
            'claims' => ['managementtools.edit', 'systemadministrator.edit'],
        ]);

        $accessGroup->int_users()->syncWithoutDetaching([$user->id]);
    }
}
