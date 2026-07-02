<?php

namespace Database\Seeders;

use App\Models\AccessGroup;
use App\Models\User;
use App\Models\AccessGroupUser;
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
        if ((User::query()->count() > 0) || (AccessGroup::query()->count() > 0)) {
            // Output a message if the database is not empty
            echo "Database is not empty. Skipping seeding.\n";
            return;
        }

        // Create an access group with all privileges
        $accessGroup = AccessGroup::query()->create([
            'name' => 'System administrators',
            'claims' => '["superadmin.edit","systemadministrator.edit"]',
        ]);

        // Create a user with the access group
        $user = User::query()->create([
            'name' => 'System administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create a user/access group pivot entry
        $userAccessGroup = AccessGroupUser::query()->create([
            'access_group_id' => $accessGroup->id,
            'user_id' => $user->id,
        ]);

        // Print a message to the console
        echo "To login, visit the application URL and use the following credentials:\n";
        echo "Username: admin@example.com\n";
        echo "Password: password\n";
    }
}
