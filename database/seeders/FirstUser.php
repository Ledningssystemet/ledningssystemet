<?php

namespace Database\Seeders;

use App\Models\AccessGroup;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FirstUser extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $command = $this->command;
        if (!$command) {
            return;
        }

        $groupName = $this->askRequired($command, 'Super administrator group name', 'Group name is required.');
        $userName = $this->askRequired($command, "User's name", 'User name is required.');
        $email = $this->askValidEmail($command, "User's email address");
        $generatedPassword = Str::password(20, true, true, false, false);

        $group = AccessGroup::firstOrCreate(
            ['name' => $groupName],
            ['claims' => ['superadmin.edit']]
        );

        $claims = is_array($group->claims) ? $group->claims : [];
        if (!in_array('superadmin.edit', $claims, true)) {
            $claims[] = 'superadmin.edit';
            $group->claims = $claims;
            $group->save();
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $userName,
                'enabled' => true,
                'password' => Hash::make($generatedPassword),
            ]
        );

        $user->int_access_groups()->syncWithoutDetaching([$group->id]);

        $command->newLine();
        $command->info('Super administrator created/updated.');
        $command->line('Group: '.$group->name);
        $command->line('User: '.$user->name.' <'.$user->email.'>');
        $command->line('Perform a password reset for the user in order to set a new password.');
    }

    private function askRequired(\Illuminate\Database\Console\Seeds\SeedCommand $command, string $question, string $errorMessage): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $value = trim((string) $command->ask($question));
            if ($value !== '') {
                return $value;
            }

            $command->error($errorMessage);
        }

        throw new \RuntimeException($errorMessage);
    }

    private function askValidEmail(\Illuminate\Database\Console\Seeds\SeedCommand $command, string $question): string
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $value = trim((string) $command->ask($question));
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }

            $command->error('Please provide a valid email address.');
        }

        throw new \RuntimeException('Please provide a valid email address.');
    }
}
