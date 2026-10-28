<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
   /**
    * Run the migrations.
    */
   public function up(): void
   {
      // Create new permission
      $permissionId = DB::table('permissions')->insertGetId([
         'name' => 'projects.create',
         'guard_name' => 'web',
         'created_at' => now(),
         'updated_at' => now(),
      ]);

      // Update existing access groups
      DB::table('access_groups')->get()->each(function ($group) use ($permissionId) {
         // Add permission to access group if the access_group has any of the following permissions
         // 'riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit

         if (
            DB::table('access_group_permissions')
               ->where('access_group_id', $group->id)
               ->whereIn('permission_id', function ($query) {
                  $query->select('id')
                     ->from('permissions')
                     ->whereIn('name', ['riskdepartment.edit', 'riskall.edit', 'riskadministrator.edit']);
               })
               ->exists()
         ) {
            DB::table('access_group_permissions')->insert([
               'access_group_id' => $group->id,
               'permission_id' => $permissionId,
               'created_at' => now(),
               'updated_at' => now(),
            ]);
         }
      });
    }

   /**
    * Reverse the migrations.
    */
   public function down(): void
   {
      // This can not be undone
      throw new \Exception("This migration cannot be undone.");
   }
};
