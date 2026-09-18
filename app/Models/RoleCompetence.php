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
use Illuminate\Support\Facades\DB;

class RoleCompetence extends Model
{
   
   protected $table = "role_competence";

   public static function getPrettyName($plural = false){ if($plural) { return __("Role competences"); } else { return __("Role competence"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if(config('ledningssystemet.disable_staff'))
            return [];
      
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

      static::saving(function ($model) {
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
         
         // Ensure that the competence level is unique
         if(!$model->id)
         {
            if(DB::table('role_competence')->where('role_id', $model->role_id)->where('competence_id', $model->competence_id)->exists())
               abort(400, __("This competence is already specified for this role"));
         }
         
         // Ensure that competence levels are for the specified competence
         $acceptableLevel = DB::table('competence_levels')->where('competence_id', $model->competence_id)->where('id', $model->acceptable_competence_level_id)->firstOrFail();
         if(null == $acceptableLevel)
            abort(400, __("The acceptable competence level is not a level within the specified competence"));

         $desiredLevel = DB::table('competence_levels')->where('competence_id', $model->competence_id)->where('id', $model->desired_competence_level_id)->firstOrFail();
         if(null == $desiredLevel)
            abort(400, __("The desired competence level is not a level within the specified competence"));
         
         // Ensure that ordinals are relevant
         if($acceptableLevel->ordinal < $desiredLevel->ordinal)
            abort(400, __("The desired competence level cannot be lower than the acceptable competence level"));
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
      'role_id',
      'competence_id',
      'acceptable_competence_level_id',
      'desired_competence_level_id',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'role_id',
      'competence_id',
      'acceptable_competence_level_id',
      'desired_competence_level_id',
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
      return (__CLASS__)::where('role_id', request()->input('role_id', 0))
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
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
         'role_id' => 'required|exists:roles,id',
         'competence_id' => 'required|exists:competences,id',
      ];
   }
}
