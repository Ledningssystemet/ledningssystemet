<?php

namespace App\Models;
use Illuminate\Support\Facades\Cache;
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
use App\Traits\HasMessages;

class Competence extends Model
{
   use HasMessages;
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Competences"); } else { return __("Competence"); }}

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
         
         // Check if this is an evaluation
         if($model->user_id)
         {
            // Ensure the user requesting this have sufficient permissions
            if((null != request()->user()) && 
               ( !request()->user()->hasPermissionTo('subordinateemployeemenagement.edit') &&
                 !request()->user()->hasPermissionTo('employeemanagement.edit')))
               abort(403);
            
            if(!request()->user()->hasPermissionTo('employeemanagement.edit'))
            {
               // Ensure the requested user is a subordinate
               $subordinates = request()->user()->int_reporting_users();
               if(!array_key_exists($model->user_id, $subordinates))
                  abort(403);
            }
            
            $uc = DB::table('user_competence')->where('user_id', $model->user_id)->where('competence_id', $model->id)->first();
            if(null != $uc)
            {
               DB::table('user_competence')->where('id', $uc->id)->update(
                  [
                     'updated_by_name' => (null != request()->user()) ? request()->user()->name : 'SYSTEM',
                     'competence_level_id' =>$model->achieved_level,
                     'note' => $model->note,
                     'updated_at' => date("Y-m-d H:i:s"),
                  ]
               );
            }
            else
            {
               DB::table('user_competence')->insert([
                  [
                     'user_id' => $model->user_id,
                     'competence_id' => $model->id,
                     'updated_by_name' => (null != request()->user()) ? request()->user()->name : 'SYSTEM',
                     'competence_level_id' =>$model->achieved_level,
                     'note' => $model->note,
                     'created_at' => date("Y-m-d H:i:s"),
                     'updated_at' => date("Y-m-d H:i:s"),
                  ]
               ]);
            }
               
            unset($model->user_id);
            unset($model->achieved_level);
            unset($model->note);
         }
         else
         {
            // Ensure the user requesting this have sufficient permissions
            if((null != request()->user()) && !request()->user()->hasPermissionTo('employeemanagement.edit'))
               abort(403);
         }
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'users',
      'roles',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   
   public function getUsersAttribute()
   {
      return Cache::rememberForever('Competence.getUsersAttribute.'.$this->id, function(){
         $retval = [];
         foreach($this->int_users as $obj)
            $retval[] = array('id' => $obj->id, 'name' => $obj->name, 'note' => $obj->pivot->note, 'competence_level_id' => $obj->pivot->competence_level_id, 'updated_by_name' => $obj->pivot->updated_by_name, 'updated_at' => $obj->pivot->updated_at);
     
         return $retval;
      });
   }
   
   public function getRolesAttribute()
   {
      return Cache::rememberForever('Competence.getRolesAttribute.'.$this->id, function(){
         $retval = [];
         foreach($this->int_roles as $obj)
            $retval[] = array('id' => $obj->id, 'name' => $obj->name, 'acceptable_competence_level_id' => $obj->pivot->acceptable_competence_level_id, 'desired_competence_level_id' => $obj->pivot->desired_competence_level_id);
     
         return $retval;
      });
   }
  
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'users',
      'roles',
      'acceptable_level',
      'desired_level',
      'evaluation_required',
      'user_id',
      'achieved_level',
      'note',
      'evaluated',
      'competence_acceptable',
      'competence_asdesired',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'users',
      'roles',
      'user_id',
      'note',
      'achieved_level',
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
      'clearevaluation',
   ];
    
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      // Everyone can fetch all competences
      if(!request()->has('user_id'))
      {
         $returnCollection = (__CLASS__)::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
               $query->where('name', 'LIKE', '%'.request()->input('search').'%');
            })
            ->orderBy('name');

         if(request()->input('hidechecked', 0) && (new (__CLASS__))->status)  { return $returnCollection->get()->filter(function($item) { return ($item->status['level'] != 'info'); }); }

         return $returnCollection->paginate(); 
      }
         
      if((request()->user()->id != request()->input('user_id')) && !request()->user()->hasPermissionTo('employeemanagement.edit'))
      {
         $found = false;
         foreach(request()->user()->int_reporting_users() as $subuser)
         {
            if($subuser->id == request()->input('user_id'))
            {
               $found = true;
               break;
            }
         }
         if(!$found)
            abort(403);
      }

      $retval = Competence::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%');
         })
         ->when(request()->input('user_id', false) && request()->input('usermandatoryonly', false), function (Builder $query) {
            $query->leftJoin('role_competence', 'role_competence.competence_id', '=', 'competences.id')
                  ->leftJoin('role_user', 'role_user.role_id', '=', 'role_competence.role_id')
                  ->where('role_user.user_id', request()->input('user_id'))
                  ->select(['competences.*']);
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('name')
         ->paginate();
            
      $retval->each(function($competence) {
         $competence->user_id = request()->input('user_id');
         
         // Check if user has already had compliance evaluations
         $eval = DB::table('user_competence')->where('competence_id', $competence->id)->where('user_id', request()->input('user_id', 0))->first();
         if($eval != null)
         {
            $competence->achieved_level = $eval->competence_level_id;
            $competence->evaluated = date("Y-m-d", strtotime($eval->updated_at)).' ('.$eval->updated_by_name.')';
            $competence->note = $eval->note;
         }
         else
         {
            $competence->achieved_level = null;
            $competence->evaluated = null;
            $competence->note = null;
         }
         
         $acceptableLevel = CompetenceLevel
            ::leftJoin('role_competence', 'role_competence.acceptable_competence_level_id','=','competence_levels.id')
            ->leftJoin('role_user', 'role_user.role_id', '=', 'role_competence.role_id')
            ->leftJoin('roles', 'role_user.role_id', '=', 'roles.id')
            ->where('competence_levels.competence_id', $competence->id)
            ->where('role_user.user_id', request()->input('user_id', 0))
            ->when(null != request()->has('role_id'), function(Builder $query){
               $query->where('role_user.role_id', request()->input('role_id')); 
            })
            ->orderBy('competence_levels.ordinal')
            ->select('competence_levels.name as competence_level_name', 'roles.name as role_name', 'competence_levels.ordinal as competence_level_ordinal')
            ->first();
         
         $competence->acceptable_level = (null == $acceptableLevel) ? __("None") : $acceptableLevel->competence_level_name.' ('.__("Role").': '.$acceptableLevel->role_name.')';
           
         $desiredLevel = CompetenceLevel
            ::leftJoin('role_competence', 'role_competence.desired_competence_level_id','=','competence_levels.id')
            ->leftJoin('role_user', 'role_user.role_id', '=', 'role_competence.role_id')
            ->leftJoin('roles', 'role_user.role_id', '=', 'roles.id')
            ->where('competence_levels.competence_id', $competence->id)
            ->where('role_user.user_id', request()->input('user_id', 0))
            ->when(null != request()->has('role_id'), function(Builder $query){
               $query->where('role_user.role_id', request()->input('role_id')); 
            })
            ->orderBy('competence_levels.ordinal')
            ->select('competence_levels.name as competence_level_name', 'roles.name as role_name', 'competence_levels.ordinal as competence_level_ordinal')
            ->first();
         
         $competence->desired_level = (null == $desiredLevel) ? __("None") : $desiredLevel->competence_level_name.' ('.__("Role").': '.$desiredLevel->role_name.')';
         $competence->evaluation_required = (null != $acceptableLevel);
         
         $competence->competence_acceptable = (null == $acceptableLevel) || ((null != $eval) && (DB::table('competence_levels')->where('id', $eval->competence_level_id)->first()->ordinal <= $acceptableLevel->competence_level_ordinal));
         $competence->competence_asdesired = (null == $desiredLevel) || ((null != $eval) && (DB::table('competence_levels')->where('id', $eval->competence_level_id)->first()->ordinal <= $desiredLevel->competence_level_ordinal));
      });
      
      
      return $retval;
         
         
   }
   
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'name' => ['sometimes',Rule::unique('qualifications')->ignore($this->id) ],
         'expires' => 'sometimes|boolean',
         'user_id' => 'sometimes|exists:users,id',
      ];
   }

   /**
    * Get the Roles associated with the object
    */
    public function int_roles() : BelongsToMany
    {
       return $this->belongsToMany(Role::class, 'role_competence')->withPivot(['acceptable_competence_level_id', 'desired_competence_level_id']);
    }   
    
   /**
    * Get the Users associated with the object
    */
    public function int_users() : BelongsToMany
    {
       return $this->belongsToMany(User::class, 'user_competence')->withPivot(['note', 'competence_level_id', 'updated_by_name', 'updated_at']);
    }   
    
    /**
     * Clear performed evaluation
     */
    public function clearevaluation() {
       // Ensure user is authorized
       if(!auth()->user()->can('update', $this::class))
          abort(401);
       
       DB::table('user_competence')->where('competence_id', $this->id)->where('user_id', request()->input('user_id', 0))->delete();
       
       return null;
    }
}
