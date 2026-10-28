<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Ledningssystemet\Ledningssystemet\Traits\HasTags;
use Ledningssystemet\Ledningssystemet\Traits\HasMessages;
use Ledningssystemet\Ledningssystemet\Traits\HasNotifications;
use Ledningssystemet\Ledningssystemet\Http\Controllers\UserNotificationController;

class ProjectTypeRiskTemplate extends Model
{
   
   public static function getPrettyName($plural = false){ if($plural) { return __("Risk templates for project types"); } else { return __("Risk template for project type"); }}

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
      'name',
      'scenariodescription',
      'consequencedescription',
      'updated_at',
      'created_by',
      'probability_id',
      'consequence_id',
      'status',
      'project_type_id',
   ];

   
   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'scenariodescription',
      'consequencedescription',
      'probability_id',
      'consequence_id',
      'project_type_id',
   ];


   /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
   protected $casts = [
   ];
   
   /**
    * The public actions available
    */
   public $actions = [
   ];
   
   
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      return (__CLASS__)::where('project_type_id', request()->input('project_type_id'))
         ->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                   ->orWhere('scenariodescription', 'LIKE', '%'.request()->input('search').'%')
                   ->orWhere('consequencedescription', 'LIKE', '%'.request()->input('search').'%');
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('name')->get();

   }
   
    
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'name' => 'required',
         'probability_id' => 'nullable|exists:Ledningssystemet\Ledningssystemet\Models\ProbabilityLevel,id',
         'consequence_id' => 'nullable|exists:Ledningssystemet\Ledningssystemet\Models\ConsequenceLevel,id',
         'project_type_id' => 'nullable|exists:Ledningssystemet\Ledningssystemet\Models\ProjectType,id',
      ];
   }


    /**
     * Get the probability object
     */
    public function int_probability() : BelongsTo
    {
        return $this->belongsTo(ProbabilityLevel::class, 'probability_id');
    }   
    
    /**
     * Get the consequence object
     */
    public function int_consequence() : BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class, 'consequence_id');
    }   
    
    /**
     * Get the project type
     */
    public function int_project_type() : BelongsTo
    {
       return $this->belongsTo(ProjectType::class, 'project_type_id');
    }
   
   /**
     * Get the risk level
     */
    public function int_risklevel()
    {
      return RiskLevel::getRisklevel($this->int_probability, $this->int_consequence);
    }

}
