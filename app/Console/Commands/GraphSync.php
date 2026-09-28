<?php

namespace Ledningssystemet\Ledningssystemet\Console\Commands;

use Ledningssystemet\Ledningssystemet\Models\AccessGroup;
use Ledningssystemet\Ledningssystemet\Models\Department;
use Ledningssystemet\Ledningssystemet\Models\Role;
use Ledningssystemet\Ledningssystemet\Models\Site;
use Ledningssystemet\Ledningssystemet\Models\User as AppUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Microsoft\Graph\Core\Tasks\PageIterator;
use Microsoft\Graph\Generated\Groups\GroupsRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Groups\Item\Members\MembersRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Models\DirectoryObject;
use Microsoft\Graph\Generated\Models\Group;
use Microsoft\Graph\Generated\Models\User;
use Microsoft\Graph\Generated\Users\UsersRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Abstractions\ApiException;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;

/**
 * Synchronizes users, departments and group membership with Microsoft Graph using the
 * official microsoft/microsoft-graph SDK (typed request builders + PageIterator).
 */
class GraphSync extends Command
{
   protected $signature = 'ledningssystemet:graphsync';

   protected $description = 'Synchronize users, department and group membership with Microsoft Graph';

   protected ?GraphServiceClient $graphClient = null;

   public function handle(): int
   {
      if (!config('ledningssystemet.graph_sync_enabled', false)) {
         $this->info('Microsoft Graph synchronization is not enabled, skipping');
         return Command::SUCCESS;
      }

      $this->info('Connecting to Microsoft Graph');
      $this->graphClient = $this->createGraphClient();

      try {
         $this->info('Synchronizing user list');
         $users = $this->fetchUsers();
         $this->syncUsers($users);

         if (config('ledningssystemet.graph_management_sync', false)) {
            $this->info('Synchronizing manager hierarchy');
            $this->syncManagers();
         }

         $this->info('Synchronizing groups');
         $groups = $this->fetchGroups();
         $this->syncGroups($groups);

         $this->info('Synchronizing group memberships');
         $this->syncGroupMemberships();

         $this->syncRoleAssignments();
      } catch (ApiException $e) {
         $this->error('Failed to communicate with Microsoft Graph: ' . $e->getMessage());
         return Command::FAILURE;
      }

      $this->info('Synchronization complete');
      return Command::SUCCESS;
   }

   /********************** Graph client ********************/

   protected function createGraphClient(): GraphServiceClient
   {
      // Reuses the tenant already resolved for Microsoft Entra ID sign-in (services.azure.tenant),
      // which falls back to OAUTH_TENANT_ID / the authorize url / "common" automatically.
      $tenantId = (string) config('services.azure.tenant');

      $tokenRequestContext = new ClientCredentialContext(
         $tenantId,
         (string) config('ledningssystemet.oauth_client_id'),
         (string) config('ledningssystemet.oauth_client_secret')
      );

      // The SDK obtains and refreshes the access token automatically
      return new GraphServiceClient($tokenRequestContext);
   }

   /**
    * Iterates over every page of a Graph collection response and invokes $callback for every item.
    *
    * @param mixed $response The (typed) collection response returned by a request builder's get() call
    * @param callable $callback function(mixed $item): bool, return false to stop iterating
    */
   protected function iteratePages($response, callable $callback): void
   {
      if (null === $response)
         return;

      (new PageIterator($response, $this->graphClient->getRequestAdapter()))->iterate($callback);
   }

   /********************** Users ********************/

   /**
    * @return array<string, User> Graph users indexed by their object id
    */
   protected function fetchUsers(): array
   {
      $filter = trim((string) config('ledningssystemet.graph_usersync_filter', ''));

      // If external users shall not be synced, add a filter
      if(!config('ledningssystemet.graph_sync_external_users', false)) {
         $filter .= ($filter == '') ? 'userType eq \'Member\'' : ' and userType eq \'Member\'';
      }

      $requestConfiguration = new UsersRequestBuilderGetRequestConfiguration(
         headers: ['ConsistencyLevel' => 'eventual'],
         queryParameters: UsersRequestBuilderGetRequestConfiguration::createQueryParameters(
            filter: ('' !== $filter) ? $filter : null,
            select: ['id', 'displayName', 'jobTitle', 'mail', 'userPrincipalName', 'accountEnabled'],
         ),
      );

      $users = [];
      $this->iteratePages(
         $this->graphClient->users()->get($requestConfiguration)->wait(),
         function (User $user) use (&$users) {
            if (null !== $user->getId())
               $users[$user->getId()] = $user;
            return true;
         }
      );

      return $users;
   }

   /**
    * @param array<string, User> $users
    */
   protected function syncUsers(array $users): void
   {
      // Track which Graph users have already been matched to a local user
      $handled = [];

      // Check if any users are to be disabled or updated
      foreach (AppUser::get() as $existingUser) {
         if (null !== $existingUser->external_id) {
            if (array_key_exists($existingUser->external_id, $users)) {
               $graphUser = $users[$existingUser->external_id];

               if (null !== ($value = $graphUser->getDisplayName()))
                  $existingUser->name = $value;

               if (null !== ($value = $graphUser->getJobTitle()))
                  $existingUser->title = $value;

               if (null !== ($value = $graphUser->getAccountEnabled()))
                  $existingUser->enabled = $value;

               if (null !== ($value = $graphUser->getMail() ?? $graphUser->getUserPrincipalName()))
                  $existingUser->email = $value;

               $existingUser->save();

               $handled[$existingUser->external_id] = true;
            } else {
               // Disable a user that does not exist anymore
               $existingUser->enabled = false;
               $existingUser->external_id = null;
               $existingUser->save();
            }
         } else {
            // Check if the user is in fact part of the external provider but has not yet been attached
            foreach ($users as $graphUser) {
               $name = $graphUser->getDisplayName();
               $enabled = $graphUser->getAccountEnabled();
               $email = $graphUser->getMail()?? $graphUser->getUserPrincipalName();

               if (null === $name || null === $enabled || null === $email)
                  continue;

               if (strtolower($email) === strtolower((string) $existingUser->email)) {
                  $existingUser->external_id = $graphUser->getId();
                  $existingUser->name = $name;
                  $existingUser->title = $graphUser->getJobTitle() ?? '';
                  $existingUser->enabled = $enabled;
                  $existingUser->email = $email;
                  $existingUser->save();

                  $handled[$graphUser->getId()] = true;
                  break;
               }
            }
         }
      }

      // Check if any users shall be added
      foreach ($users as $id => $graphUser) {
         if (array_key_exists($id, $handled))
            continue;

         $name = $graphUser->getDisplayName();
         $enabled = $graphUser->getAccountEnabled();
         $email = $graphUser->getUserPrincipalName();

         if (null === $name || null === $enabled || null === $email)
            continue;

         $newUser = new AppUser();
         $newUser->external_id = $id;
         $newUser->name = $name;
         $newUser->title = $graphUser->getJobTitle() ?? '';
         $newUser->enabled = $enabled;
         $newUser->email = $email;
         $newUser->password = bcrypt(Str::password());
         $newUser->save();
      }
   }

   /********************** Manager hierarchy ********************/

   protected function syncManagers(): void
   {
      $requestConfiguration = new UsersRequestBuilderGetRequestConfiguration(
         headers: ['ConsistencyLevel' => 'eventual'],
         queryParameters: UsersRequestBuilderGetRequestConfiguration::createQueryParameters(
            select: ['id'],
            expand: ['manager($select=id)'],
         ),
      );

      $this->iteratePages(
         $this->graphClient->users()->get($requestConfiguration)->wait(),
         function (User $graphUser) {
            $localUser = AppUser::where('external_id', $graphUser->getId())->first();
            if (null === $localUser)
               return true;

            $manager = null;
            $graphManager = $graphUser->getManager();
            if (null !== $graphManager && null !== $graphManager->getId())
               $manager = AppUser::where('external_id', $graphManager->getId())->first();

            $localUser->manager_user_id = $manager?->id;
            if ($localUser->isDirty()) {
               $this->info('Updated ' . $localUser->name);
               $localUser->save();
            }

            return true;
         }
      );
   }

   /********************** Groups ********************/

   /**
    * @return array<string, Group> Graph groups indexed by their object id
    */
   protected function fetchGroups(): array
   {
      $filter = trim((string) config('ledningssystemet.graph_groupsync_filter', ''));

      $requestConfiguration = new GroupsRequestBuilderGetRequestConfiguration(
         headers: ['ConsistencyLevel' => 'eventual'],
         queryParameters: GroupsRequestBuilderGetRequestConfiguration::createQueryParameters(
            filter: ('' !== $filter) ? $filter : null,
            select: ['id', 'displayName'],
         ),
      );

      $groups = [];
      $this->iteratePages(
         $this->graphClient->groups()->get($requestConfiguration)->wait(),
         function (Group $group) use (&$groups) {
            if (null !== $group->getId())
               $groups[$group->getId()] = $group;
            return true;
         }
      );

      return $groups;
   }

   /**
    * @param array<string, Group> $groups
    */
   protected function syncGroups(array $groups): void
   {
      $dbGroups = DB::table('external_provider_groups')->get();

      // Add/update groups
      foreach ($groups as $graphGroup) {
         $dbGroup = $dbGroups->first(fn ($row) => $row->external_id === $graphGroup->getId());

         if (null !== $dbGroup) {
            if ($graphGroup->getDisplayName() !== $dbGroup->name) {
               DB::table('external_provider_groups')->where('id', $dbGroup->id)->update(['name' => $graphGroup->getDisplayName(), 'updated_at' => date("Y-m-d H:i:s")]);
               $this->info('Updated name for group ' . $dbGroup->external_id . ' to ' . $graphGroup->getDisplayName());
            }
            continue;
         }

         // If the groupname already exist, then skip insert
         if(DB::table('external_provider_groups')->where('name', $graphGroup->getDisplayName())->exists())
            continue;

         if (DB::table('external_provider_groups')->insert([
            'external_id' => $graphGroup->getId(),
            'name' => $graphGroup->getDisplayName(),
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s"),
         ]))
            $this->info('Created new group ' . $graphGroup->getDisplayName());
         else
            $this->error('Failed to create group ' . $graphGroup->getDisplayName());
      }

      // Remove groups no longer provided
      foreach ($dbGroups as $dbGroup) {
         if (!array_key_exists($dbGroup->external_id, $groups)) {
            $this->info('Deleting group ' . $dbGroup->name);
            DB::table('external_provider_groups')->where('external_id', $dbGroup->external_id)->delete();
         }
      }
   }

   /**
    * @return array<int, string>
    */
   protected function fetchGroupMemberIds(string $groupId): array
   {
      $requestConfiguration = new MembersRequestBuilderGetRequestConfiguration(
         headers: ['ConsistencyLevel' => 'eventual'],
         queryParameters: MembersRequestBuilderGetRequestConfiguration::createQueryParameters(
            select: ['id'],
         ),
      );

      $memberIds = [];
      $this->iteratePages(
         $this->graphClient->groups()->byGroupId($groupId)->members()->get($requestConfiguration)->wait(),
         function (DirectoryObject $member) use (&$memberIds) {
            if (null !== $member->getId())
               $memberIds[] = $member->getId();
            return true;
         }
      );

      return $memberIds;
   }

   protected function syncGroupMemberships(): void
   {
      foreach (DB::table('external_provider_groups')->get() as $dbGroup) {
         $existingGroupUsers = DB::table('external_provider_group_user')
            ->join('users', 'users.id', '=', 'external_provider_group_user.user_id')
            ->where('external_provider_group_user.external_provider_group_id', $dbGroup->id)
            ->whereNotNull('users.external_id')
            ->select('users.id as user_id', 'users.external_id as external_id')
            ->get();

         $memberIds = $this->fetchGroupMemberIds($dbGroup->external_id);

         // Check if there are new users to connect
         foreach ($memberIds as $memberId) {
            if ($existingGroupUsers->contains(fn ($row) => $row->external_id === $memberId))
               continue;

            $localUser = AppUser::where('external_id', $memberId)->first();
            if (null === $localUser) {
               $this->warn('Could not find user ' . $memberId . ' to associate with group ' . $dbGroup->name);
               continue;
            }

            if (DB::table('external_provider_group_user')->insert([
               'external_provider_group_id' => $dbGroup->id,
               'user_id' => $localUser->id,
            ]))
               $this->info('Associated user ' . $localUser->name . ' with group ' . $dbGroup->name);
            else
               $this->error('Failed to associate user ' . $localUser->name . ' with group ' . $dbGroup->name);
         }

         // Check if there are users to remove
         foreach ($existingGroupUsers as $existingGroupUser) {
            if (!in_array($existingGroupUser->external_id, $memberIds, true)) {
               DB::table('external_provider_group_user')
                  ->where('user_id', $existingGroupUser->user_id)
                  ->where('external_provider_group_id', $dbGroup->id)
                  ->delete();

               $this->info('Removed user from group ' . $dbGroup->name);
            }
         }
      }
   }

   /********************** Role/access assignments ********************/

   protected function syncRoleAssignments(): void
   {
      // Perform synchronization of access roles
      foreach (AccessGroup::whereNotNull('external_provider_group_id')->get() as $dbObj)
         $dbObj->int_users()->sync(DB::table('external_provider_group_user')->where('external_provider_group_id', $dbObj->external_provider_group_id)->pluck('user_id'));

      // Perform synchronization of roles
      foreach (Role::whereNotNull('external_provider_group_id')->get() as $dbObj)
         $dbObj->int_users()->sync(DB::table('external_provider_group_user')->where('external_provider_group_id', $dbObj->external_provider_group_id)->pluck('user_id'));

      // Perform synchronization of departments
      foreach (Department::whereNotNull('external_provider_group_id')->get() as $dbObj)
         $dbObj->int_users()->sync(DB::table('external_provider_group_user')->where('external_provider_group_id', $dbObj->external_provider_group_id)->pluck('user_id'));

      // Perform synchronization of sites
      foreach (Site::whereNotNull('external_provider_group_id')->get() as $dbObj) {
         $userIds = DB::table('external_provider_group_user')->where('external_provider_group_id', $dbObj->external_provider_group_id)->pluck('user_id');

         AppUser::whereIn('id', $userIds)->update(['site_id' => $dbObj->id]);
         AppUser::where('site_id', $dbObj->id)->whereNotIn('id', $userIds)->update(['site_id' => null]);
      }
   }
}
