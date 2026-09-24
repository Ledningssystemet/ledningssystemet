<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

use App\Traits\HasNotifications;
use App\Models\Concerns\DefersRelationAttributeSync;

class Requirement extends Model
{
   use DefersRelationAttributeSync, HasNotifications;

   public static function getPrettyName($plural = false){ if($plural) { return __("Requirements"); } else { return __("Requirement"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      return $retval;
   }

   /**
    * Bootstrap any application services.
    *
    * @return void
    */
   public static function boot()
   {
      parent::boot();
      
      // Cannot update some fields if this is a partner provided list
      static::updating(function ($model) {
         if((null != auth()->user()) && $model->int_requirement_source->partner)
         {
            if($model->isDirty('name') ||
               $model->isDirty('ordinal') ||
               $model->isDirty('reference') ||
               $model->isDirty('description'))
               abort(400, __('This is a partner controlled requirement where only limited modifications are allowed'));
         }
      });
      
      // Update timestamp on parent requirement source
      static::updated(function ($model) {
         $model->int_requirement_source->touch();
      });
         
      // Cannot delete if this is part of a partner provided list
      static::deleting(function ($model) {
         if((null != auth()->user()) && $model->int_requirement_source->partner)
            abort(400, __('This is a partner controlled requirement where modifications are not allowed'));
      });
      
      // Update timestamp on parent requirement source
      static::deleted(function ($model) {
         $model->int_requirement_source->touch();
      });
      
   }

   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'controls',
      'needsapproval',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   
   public function getControlsAttribute()
   {
      $retval = [];
      foreach($this->int_controls as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval;
   }
   
   public function setControlsAttribute($value)
   {
      $this->syncRelationAttribute('controls', fn ($model) => $model->int_controls()->sync($value));
   }

   public function getNeedsapprovalAttribute()
   {
      $reqsourceapproved = Cache::rememberForever('requirement_source.approved.'.$this->requirement_source_id, function() {
         return DB::table('requirement_sources')->select(['approved_at'])->where('id', $this->requirement_source_id)->first()->approved_at;
      });

      if(null == $reqsourceapproved)
         return true;

      return strtotime($reqsourceapproved) < strtotime($this->updated_at);
   }


   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'requirement_source_id',
      'iscontrol',
      'applicable',
      'name',
      'reference',
      'ordinal',
      'description',
      'governance',
      'created_at',
      'updated_at',
      'controls',
      'needsapproval',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'reference',
      'description',
      'applicable',
      'governance',
      'controls',
      'ordinal',
      'requirement_source_id',

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
      $returnCollection = (__CLASS__)::where('requirement_source_id', request()->input('requirement_source_id'))
         ->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                   ->orWhere('reference', 'LIKE', '%'.request()->input('search').'%')
                   ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('ordinal');

      if(request()->input('hidechecked', 0) && (new (__CLASS__))->status)  { return $returnCollection->get()->filter(function($item) { return ($item->status['level'] != 'info'); }); }

      return $returnCollection->paginate(); 
   }   
    
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'name' => ['required'],
         'reference' => ['required', Rule::unique('requirements')->where(fn ($query) => $query->where('requirement_source_id', $this->requirement_source_id))->ignore($this->id)],
      ];
   }
   
   /**
     * Get requirement_source
     */
   public function int_requirement_source() : BelongsTo
   {
      return $this->belongsTo(RequirementSource::class, 'requirement_source_id');
   }
   
   /**
    * Get the controls associated with the requirement
    */
   public function int_controls() : BelongsToMany
   {
      return $this->belongsToMany(Control::class, 'control_requirements', 'requirement_id', 'control_id');
   }   
  
   /**
   * Re-order the elements
   */
   public function reorder()
   {
      // Authorize action
      if(!request()->user()->can('update', $this))
         abort(403);

      // Ensure this is not a partner-controlled list
      if($this->int_requirement_source->partner)
         abort(400, __("This is a partner-controlled requirement source which may not be altered"));
         
      // Get after-object
      $after = request()->input('after');
      $afterobj = (null == $after) ? null : Requirement::findOrFail($after);
    
      if(null == $afterobj)
         $this->ordinal = 0;
      else
         $this->ordinal = $afterobj->ordinal + 1;

      $this->save();
    
      // Re-order all objects, the slow way...
      $ordinal = 2;
      foreach(Requirement::where('requirement_source_id', $this->requirement_source_id)->orderBy('ordinal')->get() as $req)
      {
         $req->ordinal = $ordinal;
         $req->save();

         $ordinal += 2;
      }

      return [];
   }
}


