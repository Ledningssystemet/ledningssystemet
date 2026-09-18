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

class ActivityFlowTemplateItem extends Model
{
   public static function getPrettyName($plural = false){ if($plural) { return __("Activity flow template items"); } else { return __("Activity flow template item"); }}

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

      static::saving(function ($model) {
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });

      static::deleting(function ($model) {
      });
      
      // Update timestamp on parent object
      static::updated(function ($model) {
         $model->int_activity_flow_template->touch();
      });
   }

   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'activity_flow_template_id',
      'name',
      'ordinal',
      'description',
      'created_at',
      'updated_at',
      'type',
      'waitforpreceeding',
      'dueoffsetdays',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'ordinal',
      'activity_flow_template_id',
      'type',
      'waitforpreceeding',
      'dueoffsetdays',
   ];


   /**
    * The public actions available
    */
   public $actions = [
      'reorder',
   ];
   
   /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
   protected $casts = [
   ];

   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      return (__CLASS__)::where('activity_flow_template_id', request()->input('activity_flow_template_id'))
         ->where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
            });
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('ordinal')
         ->get();
   }   
    
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'name' => ['required', Rule::unique('activity_flow_template_items')->where(fn ($query) => $query->where('activity_flow_template_id', $this->activity_flow_id))->ignore($this->id)],
         'waitforpreceeding' => 'numeric|required|min:0|max:1',
         'dueoffsetdays' => 'numeric|required|min:0|max:365',
      ];
   }
   
   /**
     * Get parent
     */
   public function int_activity_flow_template() : BelongsTo
   {
      return $this->belongsTo(ActivityFlowTemplate::class, 'activity_flow_template_id');
   }
   
   /**
   * Re-order the elements
   */
   public function reorder()
   {
      // Get after-object
      $after = request()->input('after');
      $afterobj = (null == $after) ? null : ActivityFlowTemplateItem::findOrFail($after);
    
      if(null == $afterobj)
         $this->ordinal = 0;
      else
         $this->ordinal = $afterobj->ordinal + 1;

      $this->save();
    
      // Re-order all objects, the slow way...
      $ordinal = 2;
      foreach(ActivityFlowTemplateItem::where('activity_flow_template_id', $this->activity_flow_template_id)->orderBy('ordinal')->get() as $req)
      {
         $req->ordinal = $ordinal;
         $req->save();

         $ordinal += 2;
      }

      return [];
   }
    
}


