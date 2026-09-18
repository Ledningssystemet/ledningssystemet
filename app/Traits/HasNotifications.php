<?php
namespace App\Traits;

use App\Models\User;
use App\Notifications\ModelLifecycleNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

trait HasNotifications
{
   /**
    * Bootstrap any application services.
    */
   public static function bootHasNotifications()
   {
      static::created(function ($model) {
         $scope = self::deriveScope($model);
         $sender = request()->user();
         $senderId = $sender?->id;
         $prettyName = self::prettyName($model, $scope);
         $name = self::modelName($model);
         $url = method_exists($model, 'getDirectUrl') ? (string) $model->getDirectUrl() : '';
         $notifiedUsers = [];

         $responsibleUser = self::deriveResponsibleUser($model);
         if (null !== $responsibleUser) {
            self::dispatchEventToResponsibleChannels(
               $responsibleUser,
               $scope,
               'assignedtome',
               ucfirst($prettyName).' '.$name.' '.__('was assigned to you'),
               __('A new').' '.strtolower($prettyName).' '.$name.' '.__('was assigned to you'),
               $url,
               $sender,
               $senderId,
               $model,
               $notifiedUsers
            );
         }

         self::dispatchEventToGlobalChannels(
            $scope,
            'created',
            __('A new').' '.strtolower($prettyName).' '.__('was created'),
            __('The new item is named').' '.$name,
            $url,
            $sender,
            $senderId,
            $model,
            $notifiedUsers
         );
      });

      static::updated(function ($model) {
         $scope = self::deriveScope($model);
         $sender = request()->user();
         $senderId = $sender?->id;
         $prettyName = self::prettyName($model, $scope);
         $name = self::modelName($model);
         $url = method_exists($model, 'getDirectUrl') ? (string) $model->getDirectUrl() : '';
         $notifiedUsers = [];
         $title = ucfirst($prettyName).' '.__('was updated');

         $responsibleUser = self::deriveResponsibleUser($model);
         if ((null !== $responsibleUser) && ($model->isDirty('responsible_id') || $model->isDirty('responsible_user_id'))) {
            self::dispatchEventToResponsibleChannels(
               $responsibleUser,
               $scope,
               'assignedtome',
               ucfirst($prettyName).' '.$name.' '.__('was assigned to you'),
               ucfirst($prettyName).' '.$name.' '.__('was assigned to you'),
               $url,
               $sender,
               $senderId,
               $model,
               $notifiedUsers
            );
         }

         if (null !== $responsibleUser) {
            self::dispatchEventToResponsibleChannels(
               $responsibleUser,
               $scope,
               'myupdated',
               $title,
               __('Your').' '.strtolower($prettyName).' '.$name.' '.__('was updated'),
               $url,
               $sender,
               $senderId,
               $model,
               $notifiedUsers
            );
         }

         self::dispatchEventToGlobalChannels(
            $scope,
            'updated',
            $title,
            ucfirst($prettyName).' '.$name.' '.__('was updated'),
            $url,
            $sender,
            $senderId,
            $model,
            $notifiedUsers
         );
      });

      static::deleted(function ($model) {
         $scope = self::deriveScope($model);
         $sender = request()->user();
         $senderId = $sender?->id;
         $prettyName = self::prettyName($model, $scope);
         $name = self::modelName($model);

         self::dispatchEventToGlobalChannels(
            $scope,
            'deleted',
            ucfirst($prettyName).' '.__('was deleted'),
            ucfirst($prettyName).' '.$name.' '.__('was deleted').' '.__('and you have requested to be informed according to user preferences'),
            '',
            $sender,
            $senderId,
            $model
         );
      });
   }

   protected static function deriveScope($model): string
   {
      $classNameParts = explode('\\', $model::class);
      return (string) end($classNameParts);
   }

   protected static function prettyName($model, string $scope): string
   {
      return (string) (method_exists($model, 'getPrettyName') ? $model->getPrettyName() : __($scope));
   }

   protected static function modelName($model): string
   {
      if (isset($model->name) && is_string($model->name) && '' !== trim($model->name)) {
         return trim($model->name);
      }

      return '#'.(string) $model->getKey();
   }

   protected static function deriveResponsibleUser($model): ?User
   {
      if (null != $model->responsible_user_id) {
         $responsible = User::find($model->responsible_user_id);
         if (null !== $responsible) {
            return $responsible;
         }
      }

      if (null != $model->responsible_id) {
         $responsible = User::find($model->responsible_id);
         if (null !== $responsible) {
            return $responsible;
         }
      }

      return null;
   }

   protected static function dispatchEventToResponsibleChannels(
      User $responsibleUser,
      string $scope,
      string $event,
      string $title,
      string $message,
      string $url,
      ?User $sender,
      ?int $senderId,
      $model,
      array &$notifiedUsers = []
   ): void {
      foreach (
         $responsibleUser->int_user_notification_channels()
            ->whereNotNull(DB::raw('JSON_SEARCH(scopes, "one", "'.$scope.'")'))
            ->whereNotNull(DB::raw('JSON_SEARCH(events, "one", "'.$event.'")'))
            ->get() as $channel
      ) {
         self::dispatchNotificationForChannel($channel->user_id, $title, $message, $url, $sender, $senderId, $model, $notifiedUsers);
      }
   }

   protected static function dispatchEventToGlobalChannels(
      string $scope,
      string $event,
      string $title,
      string $message,
      string $url,
      ?User $sender,
      ?int $senderId,
      $model,
      array &$notifiedUsers = []
   ): void {
      foreach (
         DB::table('user_notification_channels')
            ->whereNotNull(DB::raw('JSON_SEARCH(scopes, "one", "'.$scope.'")'))
            ->whereNotNull(DB::raw('JSON_SEARCH(events, "one", "'.$event.'")'))
            ->get() as $channel
      ) {
         self::dispatchNotificationForChannel(intval($channel->user_id), $title, $message, $url, $sender, $senderId, $model, $notifiedUsers, boolval($channel->ignoremine));
      }
   }

   protected static function dispatchNotificationForChannel(
      int $targetUserId,
      string $title,
      string $message,
      string $url,
      ?User $sender,
      ?int $senderId,
      $model,
      array &$notifiedUsers,
      bool $ignoreMine = false
   ): void {
      if (array_key_exists($targetUserId, $notifiedUsers)) {
         return;
      }

      $targetUser = User::find($targetUserId);
      if (null === $targetUser) {
         return;
      }

      if ($targetUser->cannot('view', $model)) {
         return;
      }

      if ((null !== $senderId) && ($senderId === $targetUserId) && $ignoreMine) {
         return;
      }

      $notifiedUsers[$targetUserId] = $targetUserId;
      Notification::send($targetUser, new ModelLifecycleNotification($title, $message, $url, $sender));
   }
}

