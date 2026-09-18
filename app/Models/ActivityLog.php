<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Activity
{
   
   protected $table = 'activity_log';

   protected $appends = [
      'access',
      'created_at_pretty',
      'text',
      'modified',
      'created_by',
      'object_id',
      'object_type',
   ];

   protected $visible = [
      'access',
      'id',
      'modified',
      'created_by',
      'object_id',
      'object_type',
      'created_at',
      'updated_at',
      'created_at_pretty',
      'text',
   ];

   protected $casts = [
      'properties' => 'array',
      'attribute_changes' => 'array',
   ];

   public static function getPrettyName($plural = false)
   {
      return $plural ? __('Object histories') : __('Object history');
   }

   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      return [];
   }

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   public function getUidAttribute()
   {
      return (string) $this->id;
   }

   public function getCreatedAtPrettyAttribute()
   {
      return date('Y-m-d H:i', strtotime($this->created_at));
   }

   public function getObjectIdAttribute()
   {
      return $this->subject_id;
   }

   public function getObjectTypeAttribute()
   {
      return $this->subject_type;
   }

   public function getCreatedByAttribute()
   {
      $properties = $this->properties ?? [];
      if(is_array($properties) && array_key_exists('created_by', $properties))
         return $properties['created_by'];

      if(null != $this->causer && isset($this->causer->name))
         return $this->causer->name;

      return 'SYSTEM';
   }

   public function getActionAttribute()
   {
      $properties = $this->properties ?? [];
      if(is_array($properties) && array_key_exists('action', $properties))
         return $properties['action'];

      return match($this->event) {
         'created' => 'C',
         'updated' => 'U',
         'deleted' => 'D',
         'message' => 'M',
         default => '',
      };
   }

   public function getModifiedAttribute()
   {
      $properties = $this->properties ?? [];
      if(!is_array($properties) || !array_key_exists('modified', $properties))
         return null;

      return json_encode($properties['modified']);
   }

   public function getTextAttribute()
   {
      $historyText = '';
      switch($this->action)
      {
         case 'C':
            $historyText = __('The item was created');
            break;
         case 'U':
            $historyText = __('The item was updated');

            if(null != $this->modified)
            {
               $historyText .= ', '.__('changed').' ';
               $items = array_keys(json_decode($this->modified, true));
               for($i = 0; $i < count($items); $i++)
                  $historyText .= (($i > 0) ? (($i < (count($items) - 1)) ? ', ' : ' '.__('and').' ') : '').__($items[$i]);
            }

            break;
         case 'D':
            $historyText = __('The item was deleted');
            break;
         case 'M':
            $historyText = __(json_decode($this->modified, true));
            break;
         default:
            $historyText = '';
      }

      return $historyText;
   }

   public static function index(User $user)
   {
      $classname = '\\App\\Models\\'.request()->input('object_type', '');
      if(!class_exists($classname))
         abort(404);

      $obj = $classname::findOrFail(request()->input('object_id', 0));
      if(request()->user()->cannot('index', $obj))
         abort(403);

      $returnCollection = static::query()
         ->where('subject_type', $obj::class)
         ->where('subject_id', $obj->id)
         ->where('log_name', 'model-history')
         ->orderBy('updated_at', 'desc');

      if(request()->input('hidechecked', 0) && (new static())->status)
         return $returnCollection->get()->filter(function($item) { return ($item->status['level'] != 'info'); });

      return $returnCollection->paginate();
   }

   public function obj($model)
   {
      return $this->subject();
   }

   public function subject(): MorphTo
   {
      return $this->morphTo(__FUNCTION__, 'subject_type', 'subject_id');
   }

   public static function addMessage($message, $obj)
   {
      $causer = Auth::user();
      $createdBy = ((null != $causer) && (null != $causer->name)) ? $causer->name : 'SYSTEM';

      $builder = activity('model-history')
         ->performedOn($obj)
         ->event('message')
         ->withProperties([
            'action' => 'M',
            'created_by' => $createdBy,
            'modified' => $message,
         ]);

      if(null != $causer)
         $builder->causedBy($causer);

      $builder->log('message');
   }
}

