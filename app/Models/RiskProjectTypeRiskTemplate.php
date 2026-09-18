<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\HasTags;
use App\Traits\HasMessages;
use App\Traits\HasNotifications;
use App\Http\Controllers\UserNotificationController;
use App\Models\Concerns\DefersRelationAttributeSync;

class RiskProjectTypeRiskTemplate extends Model
{
   use DefersRelationAttributeSync;

   
   public static function getPrettyName($plural = false){ if($plural) { return __("Risk project type risk templates"); } else { return __("Risk project type risk template"); }}

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
      'controls',
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
      return Cache::rememberForever('RiskProjectTypeRiskTemplate.getRiskControlsAttribute.'.$this->id, function(){
         $retval = [];
         foreach($this->int_controls as $obj)
            $retval[] = array('id' => $obj->id, 'name' => $obj->name);
         return $retval;
      });
   }

   public function setControlsAttribute($value)
   {
      $this->syncRelationAttribute('controls', fn ($model) => $model->int_controls()->sync($value));
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
      'risk_project_type_id',
      'controls',
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
      'risk_project_type_id',
      'controls',
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
      return (__CLASS__)::where('risk_project_type_id', request()->input('risk_project_type_id'))
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
         'probability_id' => 'nullable|exists:App\Models\ProbabilityLevel,id',
         'consequence_id' => 'nullable|exists:App\Models\ConsequenceLevel,id',
         'risk_project_type_id' => 'nullable|exists:App\Models\RiskProjectType,id',
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
     * Get the risk project
     */
    public function int_risk_project_type() : BelongsTo
    {
       return $this->belongsTo(RiskProjectType::class, 'risk_project_type_id');
    }
   
   /**
     * Get the risk level
     */
    public function int_risklevel()
    {
      return RiskLevel::getRisklevel($this->int_probability, $this->int_consequence);
    }

   /**
    * Get the controls associated with the risk
    */
   public function int_controls() : BelongsToMany
   {
      return $this->belongsToMany(Control::class, 'control_risk_project_type_risk_template', 'risk_project_type_risk_template_id', 'control_id');
   }

}
