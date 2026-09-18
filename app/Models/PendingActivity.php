<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PendingActivity extends Model
{
      
   public static function getPrettyName($plural = false){ if($plural) { return __("Pending activities"); } else { return __("Pending activity"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      return [];
   }

   /**
    * Bootstrap any application services.
    *
    * @return void
    */
   public static function boot()
   {
      parent::boot();
   }

   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'name',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   
   public function getNameAttribute()
   {
      return $this->int_activity_flow_template_item->name;
   }
   
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'dependant_activity_id',
      'activity_flow_template_item_id',
      'responsible_user_id',
      'activity_flow_id',
      'created_at',
      'updated_at',
   ];
   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'description',
      'dependant_activity_id',
      'activity_flow_id',
      'activity_flow_template_item_id',
      'responsible_user_id',
   ];


   /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
   protected $casts = [
   ];
    
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'responsible_user_id' => 'exists:users,id',
         'activity_flow_template_item_id' => 'exists:activity_flow_template_items,id',
         'dependant_activity_id' => 'exists:activities,id',
         'activity_flow_id' => 'exists:activity_flows,id',
      ];
   }
   
   /**
    * Get the dependant activity
    */
   public function int_dependant_activity() : ?BelongsTo
   {
      if(null != $this->dependant_activity_id)
         return $this->belongsTo(Activity::class, 'dependant_activity_id');
      else if(null != $this->dependant_pending_activity_id)
         return $this->belongsTo(PendingActivity::class, 'dependant_pending_activity_id');
      else
         return null;
   }   
   
   /**
    * Get the activity flow
    */
   public function int_activity_flow() : BelongsTo
   {
      return $this->belongsTo(ActivityFlow::class, 'activity_flow_id');
   }   
   
   /**
    * Get the activity flow template item
    */
   public function int_activity_flow_template_item() : BelongsTo
   {
      return $this->belongsTo(ActivityFlowTemplateItem::class, 'activity_flow_template_item_id');
   }   
}
