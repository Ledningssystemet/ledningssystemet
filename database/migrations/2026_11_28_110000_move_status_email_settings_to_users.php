<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'email_notification_settings')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('email_notification_settings')->nullable();
            });
        }

        $hasOldTable = Schema::hasTable('user_status_email_settings');

        DB::table('users')->select('id')->orderBy('id')->chunkById(500, function ($users) use ($hasOldTable) {
            $oldSettings = $hasOldTable
                ? DB::table('user_status_email_settings')->whereIn('user_id', $users->pluck('id'))->get()->keyBy('user_id')
                : collect();

            foreach ($users as $user) {
                $old = $oldSettings->get($user->id);
                $settings = [];
                foreach (self::DAYS as $day) {
                    $settings[$day] = null === $old ? 'monday' === $day : (bool) $old->{$day};
                }

                DB::table('users')->where('id', $user->id)->update([
                    'email_notification_settings' => json_encode(['status_email' => $settings]),
                ]);
            }
        });

        Schema::dropIfExists('user_status_email_settings');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('user_status_email_settings')) {
            Schema::create('user_status_email_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
                $table->boolean('monday')->default(true);
                $table->boolean('tuesday')->default(true);
                $table->boolean('wednesday')->default(true);
                $table->boolean('thursday')->default(true);
                $table->boolean('friday')->default(true);
                $table->boolean('saturday')->default(false);
                $table->boolean('sunday')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('users', 'email_notification_settings')) {
            return;
        }

        DB::table('users')->select('id', 'email_notification_settings')->orderBy('id')->chunkById(500, function ($users) {
            foreach ($users as $user) {
                $decoded = json_decode((string) $user->email_notification_settings, true);
                $settings = is_array($decoded['status_email'] ?? null) ? $decoded['status_email'] : [];
                $row = ['user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()];
                foreach (self::DAYS as $day) {
                    $row[$day] = array_key_exists($day, $settings) ? (bool) $settings[$day] : 'monday' === $day;
                }

                DB::table('user_status_email_settings')->insert($row);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_notification_settings');
        });
    }
};
