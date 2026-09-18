<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class graphsync extends Command
{
   /* Variables */
   protected $token = null;

   /**
    * The name and signature of the console command.
    *
    * @var string
    */
   protected $signature = 'ledningssystemet:graphsync';

   /**
    * The console command description.
    *
    * @var string
    */
   protected $description = 'Synchronize users, department and group membership with Microsoft Graph';

   /**
    * Execute the console command.
    *
    * @return int
    */
   public function handle()
   {
      /* Skip if no url is set */
      if("" == config('ledningssystemet.graph_usersync_path', ""))
      {
         $this->info("No user sync path configured, skipping synchronization");
         return Command::SUCCESS;
      }

      /* Obtain authorization token */
      $this->info("Obtaining authorization token");
      $this->getMSGraphToken();

      /* Synchronize user list */
      $this->info("Synchronizing user list");
      $objs = $this->getMSGraphData(config('ledningssystemet.graph_usersync_path'));

      // Flatten data in objs
      foreach (array_keys($objs) as $key) {
         foreach (array_keys($objs[$key]) as $attrname) {
            if (is_array($objs[$key][$attrname])) {
               foreach (array_keys($objs[$key][$attrname]) as $subname)
                  $objs[$key][$attrname . '.' . $subname] = $objs[$key][$attrname][$subname];

               unset($objs[$key][$attrname]);
            }
         }
      }

      // Check if any users are to be disabled or updated
      foreach (\App\Models\User::get() as $existinguser) {
         if (null != $existinguser->external_id) {
            // Update if user is found
            if (array_key_exists($existinguser->external_id, $objs)) {
               if (array_key_exists(config('ledningssystemet.graph_usersync_namefield', 'displayName'), $objs[$existinguser->external_id]))
                  $existinguser->name = $objs[$existinguser->external_id][config('ledningssystemet.graph_usersync_namefield', 'displayName')];

               if (array_key_exists(config('ledningssystemet.graph_usersync_titlefield', 'jobTitle'), $objs[$existinguser->external_id]))
                  $existinguser->title = $objs[$existinguser->external_id][config('ledningssystemet.graph_usersync_titlefield', 'jobTitle')];

               if (array_key_exists(config('ledningssystemet.graph_usersync_enabledfield', 'accountEnabled'), $objs[$existinguser->external_id]))
                  $existinguser->enabled = $objs[$existinguser->external_id][config('ledningssystemet.graph_usersync_enabledfield', 'accountEnabled')];

               if (array_key_exists(config('ledningssystemet.graph_usersync_emailfield', 'userPrincipalName'), $objs[$existinguser->external_id]))
                  $existinguser->email = $objs[$existinguser->external_id][config('ledningssystemet.graph_usersync_emailfield', 'userPrincipalName')];

               $existinguser->save();

               // Mark as handled
               $objs[$existinguser->external_id]['checked'] = true;
            } else {
               // Disable a user, and  that does not exist
               $existinguser->enabled = false;
               $existinguser->external_id = null;
               $existinguser->save();

               // Mark as handled
               $objs[$existinguser->external_id]['checked'] = true;
            }
         } else {
            // Check if the user is in fact part of the external provider but has not yet been attached
            foreach ($objs as $userobj) {

               // Skip if no userPrincipalName or displayName or accountEnabled
               if (!array_key_exists(config('ledningssystemet.graph_usersync_namefield', 'displayName'), $userobj) ||
                  !array_key_exists(config('ledningssystemet.graph_usersync_enabledfield', 'accountEnabled'), $userobj) ||
                  !array_key_exists(config('ledningssystemet.graph_usersync_emailfield', 'userPrincipalName'), $userobj))
                  continue;

               if (strtolower($userobj[config('ledningssystemet.graph_usersync_emailfield', 'userPrincipalName')]) == strtolower($existinguser->email)) {
                  $existinguser->external_id = $userobj['id'];
                  $existinguser->name = $userobj[config('ledningssystemet.graph_usersync_namefield', 'displayName')];
                  $existinguser->title = array_key_exists(config('ledningssystemet.graph_usersync_titlefield', 'jobTitle'), $userobj) ? $userobj[config('ledningssystemet.graph_usersync_titlefield', 'jobTitle')] : "";
                  $existinguser->enabled = $userobj[config('ledningssystemet.graph_usersync_enabledfield', 'accountEnabled')];
                  $existinguser->email = $userobj[config('ledningssystemet.graph_usersync_emailfield', 'userPrincipalName')];
                  $existinguser->save();

                  // Mark as handled
                  $objs[$existinguser->external_id]['checked'] = true;
                  break;
               }
            }
         }
      }

      // Check if any users shall be added
      foreach ($objs as $userobj) {
         if (array_key_exists('checked', $userobj))
            continue;

         if (!array_key_exists(config('ledningssystemet.graph_usersync_namefield', 'displayName'), $userobj) ||
            !array_key_exists(config('ledningssystemet.graph_usersync_enabledfield', 'accountEnabled'), $userobj) ||
            !array_key_exists(config('ledningssystemet.graph_usersync_emailfield', 'userPrincipalName'), $userobj))
            continue;

         // Create a new user
         $newUser = new \App\Models\User();
         $newUser->external_id = $userobj['id'];
         $newUser->name = $userobj[config('ledningssystemet.graph_usersync_namefield', 'displayName')];
         $newUser->title = array_key_exists(config('ledningssystemet.graph_usersync_titlefield', 'jobTitle'), $userobj) ? $userobj[config('ledningssystemet.graph_usersync_titlefield', 'jobTitle')] : "";
         $newUser->enabled = $userobj[config('ledningssystemet.graph_usersync_enabledfield', 'accountEnabled')];
         $newUser->email = $userobj[config('ledningssystemet.graph_usersync_emailfield', 'userPrincipalName')];
         $newUser->password = bcrypt(\Illuminate\Support\Str::password());
         $newUser->save();
      }

      // Create department mappings
      if (config('ledningssystemet.graph_departments_pattern_level1', '')) {
         $departments = [];
         $ignoreDepartments = ("" == config('ledningssystemet.graph_ignore_departments', "")) ? [] : explode(',', config('ledningssystemet.graph_ignore_departments', ""));

         foreach ($objs as $userobj) {
            // Skip if no userPrincipalName or displayName or accountEnabled
            if (!array_key_exists(config('ledningssystemet.graph_usersync_namefield', 'displayName'), $userobj))
               continue;

            $departmentArray = $this->deriveDepartmentName($userobj);
            $departmentname = implode(config('ledningssystemet.graph_departments_name_delimiter', "-"), $departmentArray);

            if ("" == trim($departmentname)) {
               $this->warn("User " . $userobj[config('ledningssystemet.graph_usersync_namefield', 'displayName')] . " does not belong to any department");
               continue;
            }

            // Check if department shall be ignored
            if (in_array($departmentname, $ignoreDepartments))
               continue;

            // Check if already in array
            $found = false;
            foreach (array_keys($departments) as $depkey) {
               if (0 === strcasecmp($departments[$depkey]['name'], $departmentname)) {
                  $departments[$depkey]['users'][] = $userobj['id'];
                  $found = true;
                  break;
               }
            }

            if (!$found)
               $departments[] = array('name' => $departmentname, 'users' => array($userobj['id']));
         }

         // Create new departments, or update existing
         foreach ($departments as $dep) {
            // Calculate members
            $depusers = [];
            foreach ($dep['users'] as $extid) {
               $dbUser = \App\Models\User::where('external_id', $extid)->first();
               if (null != $dbUser)
                  $depusers[] = $dbUser->id;
            }

            // Fetch or create department
            $dbDep = \App\Models\Department::where('name', 'LIKE', $dep['name'])->first();
            if (null == $dbDep) {
               $this->info("Creating new department " . $dep['name']);
               $dbDep = new \App\Models\Department;
               $dbDep->name = $dep['name'];
               $dbDep->created_at = date("Y-m-d H:i:s");
               $dbDep->updated_at = $dbDep->created_at;
               $dbDep->uid = \Illuminate\Support\Str::uuid();
               $dbDep->save();
            }
            if (config('ledningssystemet.graph_departments_assignusers'))
               $dbDep->int_users()->sync($depusers);
         }

         // If there are departments that are no longer used, users shall be removed from them
         if (config('ledningssystemet.graph_departments_assignusers')) {
            foreach (\App\Models\Department::get() as $depObj) {
               $found = false;

               foreach ($departments as $dep) {
                  if (0 === strcasecmp($dep['name'], $depObj->name))
                     $found = true;
               }

               if (!$found)
                  $depObj->int_users()->sync([]);

            }
         }
      }

      // Sync manager relations
      $envpath = config('ledningssystemet.graph_management_path');
      if (null != $envpath) {
         $this->info("Synchronizing manager hierarchy");
         $objs = $this->getMSGraphData(config('ledningssystemet.graph_management_path'));
         foreach ($objs as $obj) {
            $userobj = \App\Models\User::where('external_id', $obj['id'])->first();
            if (null == $userobj)
               continue;

            $manager = null;
            if (array_key_exists('manager', $obj)) {
               $manager = \App\Models\User::where('external_id', $obj['manager']['id'])->first();
            }

            $userobj->manager_user_id = (null == $manager) ? null : $manager->id;
            if ($userobj->isDirty()) {
               $this->info("Updated " . $userobj->name);
               $userobj->save();
            }
         }
      }


      /* Synchronize groups */
      $this->info("Synchronizing groups");
      $objs = $this->getMSGraphData(config('ledningssystemet.graph_groupsync_path'));

      // Add/update groups
      foreach ($objs as $graphGroup) {
         $found = false;
         foreach (\Illuminate\Support\Facades\DB::table('external_provider_groups')->get() as $dbGroup) {
            if ($graphGroup['id'] == $dbGroup->external_id) {
               // Found
               $found = true;

               // Update if name has changed
               if ($graphGroup['displayName'] != $dbGroup->name) {
                  \Illuminate\Support\Facades\DB::table('external_provider_groups')->where('id', $dbGroup->id)->update(['name' => $graphGroup['displayName']]);
                  $this->info('Updated name for group ' . $dbGroup->external_id . ' to ' . $dbGroup->name);
               }
            }
         }

         if (!$found) {
            // Create new group
            if (\Illuminate\Support\Facades\DB::table('external_provider_groups')->insert([
               'external_id' => $graphGroup['id'],
               'name' => $graphGroup['displayName']
            ]))
               $this->info('Created new group ' . $graphGroup['displayName']);
            else
               $this->error('Failed to create group ' . $graphGroup['displayName']);
         }
      }

      // Remove groups no longer provided
      foreach (\Illuminate\Support\Facades\DB::table('external_provider_groups')->get() as $dbGroup) {
         $found = false;
         foreach ($objs as $graphGroup) {
            if ($graphGroup['id'] == $dbGroup->external_id) {
               $found = true;
               break;
            }
         }

         // Remove group
         if (!$found) {
            $this->info('Deleting group ' . $dbGroup->name);
            \Illuminate\Support\Facades\DB::table('external_provider_groups')->
            where('external_id', $dbGroup->external_id)->
            delete();
         }
      }

      /* Synchronize group memberships */
      $this->info("Synchronizing group memberships");
      foreach (\Illuminate\Support\Facades\DB::table('external_provider_groups')->get() as $dbGroup) {
         $existingGroupUsers = \Illuminate\Support\Facades\DB::table('external_provider_group_user')
            ->leftJoin('users', 'users.id', '=', 'external_provider_group_user.user_id')
            ->where('external_provider_group_user.external_provider_group_id', $dbGroup->id)
            ->whereNotNull('users.external_id')
            ->get();


         $path = config('ledningssystemet.graph_groupusers_path', '');
         if ("" == $path) {
            $this->info('No configurated group users path');
            break;
         }

         // Insert ID into path
         $path = str_replace('{group-id}', $dbGroup->external_id, $path);

         // Call graph api
         $groupusers = $this->getMSGraphData($path);

         // Check if there are new users to connect
         foreach ($groupusers as $graphgroupuser) {
            $found = false;
            foreach ($existingGroupUsers as $dbgroupuser) {
               if ($dbgroupuser->external_id == $graphgroupuser['id']) {
                  $found = true;
                  break;
               }
            }

            if (!$found) {
               // Look up user id
               $userobj = \App\Models\User::where('external_id', $graphgroupuser['id'])->first();
               if (null != $userobj) {
                  if (\Illuminate\Support\Facades\DB::table('external_provider_group_user')->insert([
                     'external_provider_group_id' => $dbGroup->id,
                     'user_id' => $userobj->id
                  ]))
                     $this->info('Associated user ' . $userobj->name . ' with group ' . $dbGroup->name);
                  else
                     $this->error('Failed to associate user ' . $userobj->name . ' with group ' . $dbGroup->name);
               } else
                  $this->warn('Could not find user ' . $graphgroupuser['id'] . ' to associate with group ' . $dbGroup->name);
            }
         }

         // Check if there are users to remove
         foreach ($existingGroupUsers as $dbgroupuser) {
            $found = false;
            foreach ($groupusers as $graphgroupuser) {
               if ($dbgroupuser->external_id == $graphgroupuser['id']) {
                  $found = true;
                  break;
               }
            }

            if (!$found) {
               \Illuminate\Support\Facades\DB::table('external_provider_group_user')->
               where('user_id', $dbgroupuser->id)->
               where('external_provider_group_id', $dbGroup->id)->
               delete();

               $this->info('Removed user from group ' . $dbGroup->name);
            }
         }
      }

      // Perform synchronization of access roles
      foreach (\App\Models\AccessGroup::whereNotNull('external_provider_group_id')->get() as $dbObj)
         $dbObj->int_users()->sync(\Illuminate\Support\Facades\DB::table('external_provider_group_user')->where('external_provider_group_id', $dbObj->external_provider_group_id)->pluck('user_id'));

      // Perform synchronization of roles
      foreach (\App\Models\Role::whereNotNull('external_provider_group_id')->get() as $dbObj)
         $dbObj->int_users()->sync(\Illuminate\Support\Facades\DB::table('external_provider_group_user')->where('external_provider_group_id', $dbObj->external_provider_group_id)->pluck('user_id'));

      // Perform synchronization of departments
      if (!config('ledningssystemet.graph_departments_assignusers')) {
         foreach (\App\Models\Department::whereNotNull('external_provider_group_id')->get() as $dbObj)
            $dbObj->int_users()->sync(\Illuminate\Support\Facades\DB::table('external_provider_group_user')->where('external_provider_group_id', $dbObj->external_provider_group_id)->pluck('user_id'));
      }

      // Perform synchronization of sites
      foreach (\App\Models\Site::whereNotNull('external_provider_group_id')->get() as $dbObj) {
         $userids = \Illuminate\Support\Facades\DB::table('external_provider_group_user')->where('external_provider_group_id', $dbObj->external_provider_group_id)->pluck('user_id');

         \App\Models\User::whereIn('id', $userids)->update(['site_id' => $dbObj->id]);
         \App\Models\User::where('site_id', $dbObj->id)->whereNotIn('id', $userids)->update(['site_id' => null]);
      }

      // Return ok
      $this->info("Synchronization complete");
      return Command::SUCCESS;
   }

   /********************** Support functions ************/
   function deriveDepartmentName($userobj, $parentDeps = [])
   {
      // Get pattern
      $department = config('ledningssystemet.graph_departments_pattern_level' . (count($parentDeps) + 1), "");

      // If no pattern, then we have no more depths to evaluate
      if ("" == $department)
         return $parentDeps;

      // Perform replacement
      foreach (array_keys($userobj) as $key) {
         if (null == $userobj[$key])
            $userobj[$key] = "";

         $department = str_replace('[' . $key . ']', $userobj[$key], $department);
      }

      // Check if extraction was successful
      if ((false !== strpos($department, '[')) ||
         (false !== strpos($department, ']')))
         return [];

      // If empty, then we return
      if ("" == trim($department))
         return $parentDeps;

      $parentDeps[] = $department;

      return $this->deriveDepartmentName($userobj, $parentDeps);
   }


   /********************** Graph API ********************/

   function getMSGraphToken()
   {
      $response = $this->graphHttpRequest()
         ->asForm()
         ->post(config('ledningssystemet.oauth_server_token_url'), [
            'grant_type' => 'client_credentials',
            'client_id' => config('ledningssystemet.oauth_client_id'),
            'client_secret' => config('ledningssystemet.oauth_client_secret'),
            'scope' => 'https://graph.microsoft.com/.default',
         ]);

      if (!$response->successful())
         throw new \Exception('Failed to perform request to GRAPH provider: status '.$response->status().' body '.$response->body());

      $reply = $response->json();

      if (null === $reply)
         throw new \Exception('Failed to decode response from GRAPH provider');

      // Check if there are an error, like invalid credentials
      if (array_key_exists('error', $reply) && $reply['error'])
         throw new \Exception('Error thrown by GRAPH provider: ' . print_r($reply['error'], true));

      // Ensure all variables exist
      if (!array_key_exists('access_token', $reply) ||
         !array_key_exists('token_type', $reply) ||
         !array_key_exists('expires_in', $reply))
         throw new \Exception('Response from GRAPH provider was not complete');

      // Validate token type
      if ('Bearer' != $reply['token_type'])
         throw new \Exception('Invalid token type from GRAPH provider (' . $reply['token_type'] . ')');

      $this->token = $reply['access_token'];
      return true;
   }

   function getMSGraphData($request)
   {
      $retval = [];

      $url = $request;
      while ("" != $url) {
         $response = $this->graphHttpRequest()
            ->withToken($this->token)
            ->withHeaders([
               'ConsistencyLevel' => 'eventual',
            ])
            ->get($url);

         if (!$response->successful())
            throw new \Exception('Failed to perform request to GRAPH provider: status '.$response->status().' body '.$response->body());

         $reply = $response->json();

         if (null === $reply)
            throw new \Exception('Failed to decode response from GRAPH provider');

         // Check if there are an error, like invalid credentials
         if (array_key_exists('error', $reply) && $reply['error'])
            throw new \Exception('Error thrown by GRAPH provider: ' . print_r($reply['error'], true));

         // Ensure all variables exist
         if (!array_key_exists('value', $reply))
            throw new \Exception('Response from GRAPH provider did not contain any value');

         // Append to retval
         foreach ($reply['value'] as $obj) {
            if (array_key_exists('id', $obj))
               $retval[$obj['id']] = $obj;
            else
               $retval[] = $obj;
         }

         // Set next url
         $url = array_key_exists('@odata.nextLink', $reply) ? $reply['@odata.nextLink'] : "";

         // Print info
         if ("" != $url)
            $this->info('Fetching new round with ' . $url . '(' . print_r($reply, true) . ')');
      }

      return $retval;
   }

   protected function graphHttpRequest(): PendingRequest
   {
      return Http::acceptJson()
         ->timeout(30)
         ->retry(2, 250);
   }
}