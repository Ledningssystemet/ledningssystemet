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

class Employee extends Model
{
   protected $table = "users";
   
       
   public static function getPrettyName($plural = false){ if($plural) { return __("Employees"); } else { return __("Employee"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if (config('ledningssystemet.disable_staff'))
         return [];

      $retval = [];
      $url = ((($user != null) && $user->can('index', get_called_class())) ||
         (($user == null) && (null != auth()->user()) && auth()->user()->can('index', get_called_class())))
         ? url()->query('/staff/employees')
         : null;

      // Base scope: enabled employees, filtered by manager and/or department
      $scope = User::query()
         ->where('enabled', true)
         ->when($user, fn (Builder $q) => $q->where('manager_user_id', $user->id))
         ->when($department, function (Builder $q) use ($department) {
            $q->whereExists(function ($sub) use ($department) {
               $sub->selectRaw('1')
                  ->from('department_user')
                  ->whereColumn('department_user.user_id', 'users.id')
                  ->where('department_user.department_id', $department->id);
            });
         });

      // Missing mandatory qualifications:
      // Employee has a role with a mandatory qualification but no finished_at in qualification_user
      $missing_mandatory = (clone $scope)
         ->whereExists(function ($q) {
            $q->selectRaw('1')
               ->from('role_user as ru')
               ->join('qualification_role as qr', 'qr.role_id', '=', 'ru.role_id')
               ->leftJoin('qualification_user as qu', function ($j) {
                  $j->on('qu.user_id', '=', 'ru.user_id')
                     ->on('qu.qualification_id', '=', 'qr.qualification_id');
               })
               ->whereColumn('ru.user_id', 'users.id')
               ->where('qr.mandatory', true)
               ->whereNull('qu.finished_at');
         })
         ->count();

      // Expired mandatory qualifications:
      // Employee has a mandatory qualification where expires_at is set and has passed
      $expired = (clone $scope)
         ->whereExists(function ($q) {
            $q->selectRaw('1')
               ->from('role_user as ru')
               ->join('qualification_role as qr', 'qr.role_id', '=', 'ru.role_id')
               ->join('qualification_user as qu', function ($j) {
                  $j->on('qu.user_id', '=', 'ru.user_id')
                     ->on('qu.qualification_id', '=', 'qr.qualification_id');
               })
               ->whereColumn('ru.user_id', 'users.id')
               ->where('qr.mandatory', true)
               ->whereNotNull('qu.expires_at')
               ->whereRaw('qu.expires_at < NOW()');
         })
         ->count();

      // Soon expiring mandatory qualifications:
      // Employee has a mandatory qualification expiring within 1 month
      $soon_expired = (clone $scope)
         ->whereExists(function ($q) {
            $q->selectRaw('1')
               ->from('role_user as ru')
               ->join('qualification_role as qr', 'qr.role_id', '=', 'ru.role_id')
               ->join('qualification_user as qu', function ($j) {
                  $j->on('qu.user_id', '=', 'ru.user_id')
                     ->on('qu.qualification_id', '=', 'qr.qualification_id');
               })
               ->whereColumn('ru.user_id', 'users.id')
               ->where('qr.mandatory', true)
               ->whereNotNull('qu.expires_at')
               ->whereRaw('qu.expires_at < DATE_ADD(NOW(), INTERVAL 1 MONTH)');
         })
         ->count();

      // Competence not evaluated:
      // Employee has a role with a required competence area but no entry in user_competence
      $not_evaluated = (clone $scope)
         ->whereExists(function ($q) {
            $q->selectRaw('1')
               ->from('role_user as ru')
               ->join('role_competence as rc', 'rc.role_id', '=', 'ru.role_id')
               ->leftJoin('user_competence as uc', function ($j) {
                  $j->on('uc.user_id', '=', 'ru.user_id')
                     ->on('uc.competence_id', '=', 'rc.competence_id');
               })
               ->whereColumn('ru.user_id', 'users.id')
               ->whereNull('uc.competence_id');
         })
         ->count();

      // Competence not sufficient:
      // Employee is evaluated but achieved level ordinal > acceptable level ordinal
      // (lower ordinal = higher competence in this system)
      $not_sufficient = (clone $scope)
         ->whereExists(function ($q) {
            $q->selectRaw('1')
               ->from('role_user as ru')
               ->join('role_competence as rc', 'rc.role_id', '=', 'ru.role_id')
               ->join('user_competence as uc', function ($j) {
                  $j->on('uc.user_id', '=', 'ru.user_id')
                     ->on('uc.competence_id', '=', 'rc.competence_id');
               })
               ->join('competence_levels as cl_achieved', 'cl_achieved.id', '=', 'uc.competence_level_id')
               ->join('competence_levels as cl_acceptable', 'cl_acceptable.id', '=', 'rc.acceptable_competence_level_id')
               ->whereColumn('ru.user_id', 'users.id')
               ->whereRaw('cl_achieved.ordinal > cl_acceptable.ordinal');
         })
         ->count();

      if ($missing_mandatory)
         $retval[] = ['level' => 'danger', 'count' => $missing_mandatory, 'text' => __("Employees without mandatory qualifications"), 'url' => $url];

      if ($expired)
         $retval[] = ['level' => 'danger', 'count' => $expired, 'text' => __("Employees with expired, mandatory, qualifications"), 'url' => $url];

      if ($soon_expired)
         $retval[] = ['level' => 'danger', 'count' => $soon_expired, 'text' => __("Employees with mandatory qualifications that soon expires"), 'url' => $url];

      if ($not_evaluated)
         $retval[] = ['level' => 'danger', 'count' => $not_evaluated, 'text' => __("Employees without competence evaluation"), 'url' => $url];

      if ($not_sufficient)
         $retval[] = ['level' => 'warning', 'count' => $not_sufficient, 'text' => __("Employees with insufficient competence"), 'url' => $url];

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
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'status',
      'roles',
      'departments',
      'processaccountabilities',
      'processresponsibilities',
      'processes',
      'informationtypes',
      'assets',
      'suppliers',
      'controls',
      'qualifications',
      'competences',
      'manager',
      'directreports',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   
   public function getStatusAttribute()
   {
      if(!$this->enabled)
         return ['icon' => 'cancel', 'level' => 'danger', 'text' => __("The employee is no longer active")];
      
      $level = 'info';
      $texts = [];
      
      foreach($this->int_qualifications() as $qualrole)
      {
         foreach($qualrole['qualifications'] as $qual)
         {
            if($qual['mandatory'] && !$qual['finished_at'])
            {
               $level = 'danger';
               $texts[] = __("Qualification").' '.$qual['name'].' '.__("is missing");
            }
            
            if($qual['mandatory'] && $qual['expires_at'] && (strtotime($qual['expires_at']) < time()))
            {
               $level = 'danger';
               $texts[] = __("Mandatory qualification").' '.$qual['name'].' '.__("has expired");
            }
            
            if($qual['mandatory'] && $qual['expires_at'] && (strtotime($qual['expires_at']) < strtotime("+1 MONTHS")))
            {            
               if($level != 'danger')
                  $level = 'warning';
               
               $texts[] = __("Mandatory qualification").' '.$qual['name'].' '.__("is about to expire");
            }
         }
      }
      
      foreach($this->int_competences() as $comprole)
      {
         foreach($comprole['competences'] as $comp)
         {
            if(null == $comp['achieved_level'])
            {
               $level = 'danger';
               $texts[] = __("Employee has not been evaluated for competence area").' '.$comp['name'];
            }
            else if(CompetenceLevel::findOrFail($comp['achieved_level']->competence_level_id)->ordinal > $comp['acceptable_level']->ordinal)
            {
               if($level != 'danger')
                  $level = 'warning';
               
               $texts[] = __("Employee does not fulfil minimum competence requirements for competence area").' '.$comp['name'];
            }
         }
      }      
      
      $texts = array_unique($texts);
      sort($texts);
      
      return ['icon' => ('info' == $level) ? 'check' : 'warning', 'level' => $level, 'text' => $texts];
   }

   public function getRolesAttribute()
   {
      $retval = [];
      foreach($this->int_employeeroles as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name, 'authorities' => $obj->authorities);
  
      return $retval;
   }
  
   public function getDepartmentsAttribute()
   {
      $retval = [];
      foreach($this->int_departments as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
  
      return $retval;
   }
  
   public function getProcessaccountabilitiesAttribute()
   {
      return $this->int_processacountabilities();
   }
  
   public function getProcessresponsibilitiesAttribute()
   {
      return $this->int_processresponsibilities();
   }
  
   public function getQualificationsAttribute()
   {
      return $this->int_qualifications();
   }
   
   public function getCompetencesAttribute()
   {
      return $this->int_competences();
   }
   
   
   public function getProcessesAttribute()
   {
      $retval = [];
      foreach($this->int_processes as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
  
      return $retval;
   }

   public function getInformationtypesAttribute()
   {
      $retval = [];
      foreach($this->int_information_types as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
  
      return $retval;
   }

   public function getAssetsAttribute()
   {
      $retval = [];
      foreach($this->int_assets as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
  
      return $retval;
   }

   public function getSuppliersAttribute()
   {
      $retval = [];
      foreach($this->int_suppliers as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
  
      return $retval;
   }

   public function getControlsAttribute()
   {
      $retval = [];
      foreach($this->int_controls as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name.($obj->not_applicable_at  ? ' ('.__("Not applicable").')' : ''));
  
      return $retval;
   }

   public function getManagerAttribute()
   {
      $userobj = DB::table('users')->where('id', $this->id)->first();
      if($userobj->manager_user_id)
         return DB::table('users')->where('id', $userobj->manager_user_id)->first()->name;
      
      return null;
   }
   
   public function getDirectreportsAttribute()
   {
      return DB::table('users')->where('manager_user_id', $this->id)->select('id', 'name')->get();
   }

   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'status',
      'name',
      'enabled',
      'title',
      'departments',
      'manager',
      'directreports',
      'roles',
      'processaccountabilities',
      'processresponsibilities',
      'authorities',
      'processes',
      'informationtypes',
      'assets',
      'suppliers',
      'controls',
      'qualifications',
      'competences',
];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
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
      $returnCollection = (__CLASS__)::where('enabled', true)
         ->when(!$user->hasPermissionTo('employeemanagement.edit'), function (Builder $query) use ($user) {
            $subordinates = [];
            foreach($user->int_reporting_users() as $obj)
               $subordinates[] = $obj->id;
            $query->whereIn('id', $subordinates);
         })
         ->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%');
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('name');
         
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
      ];
   }  
   
   public function int_employeeroles() : BelongsToMany
   {
      return $this->belongsToMany(EmployeeRole::class, 'role_user', 'user_id', 'role_id')->orderBy('name');
   }

   public function int_departments() : BelongsToMany
   {
      return $this->belongsToMany(Department::class, 'department_user', 'user_id')->orderBy('name');
   }

   public function int_processes() : HasMany
   {
      return $this->hasMany(Process::class, 'responsible_user_id')->orderBy('name');
   }
   
   public function int_information_types() : HasMany
   {
      return $this->hasMany(InformationType::class, 'responsible_user_id')->orderBy('name');
   }

   public function int_assets() : HasMany
   {
      return $this->hasMany(Asset::class, 'responsible_user_id')->orderBy('name');
   }

   public function int_suppliers() : HasMany
   {
      return $this->hasMany(Supplier::class, 'responsible_user_id')->orderBy('name');
   }

   public function int_controls() : HasMany
   {
      return $this->hasMany(Control::class, 'responsible_user_id')->orderBy('name');
   }

   public function int_qualifications()
   {
      return Cache::rememberForever('Employee.int_qualifications.'.$this->id, function(){
         $retval = [];
         $performedQuals = [];
         foreach(QualificationUser::where('user_id', $this->id)->get() as $qual)
            $performedQuals[$qual->qualification_id] = $qual;
         
         foreach($this->int_employeeroles()->orderBy('name')->get() as $role)
         {
            foreach($role->int_qualifications()->rightJoin('qualifications', 'qualifications.id', '=', 'qualification_role.qualification_id')->orderBy('qualifications.name')->select('qualification_role.*', 'qualifications.name as name', 'qualifications.description as description')->get() as $qualification)
            {
               if(!array_key_exists($role->id, $retval))
                  $retval[$role->id] = array('id' => $role->id, 'name' => $role->name, 'qualifications' => array());

               $finished_at = null;
               $planned_at = null;
               $expires_at = null;
               
               if(array_key_exists($qualification->qualification_id, $performedQuals))
               {
                  $finished_at = $performedQuals[$qualification->qualification_id]->finished_at;
                  $planned_at = $performedQuals[$qualification->qualification_id]->planned_at;
                  $expires_at = $performedQuals[$qualification->qualification_id]->expires_at;
               }

               $retval[$role->id]['qualifications'][] = array('id' => $qualification->id, 'name' => $qualification->name, 'description' => $qualification->description, 'mandatory' => $qualification->mandatory, 'finished_at' => $finished_at, 'planned_at' => $planned_at, 'expires_at' => $expires_at);
            }
         }
         
         return $retval;
      });
   }

   
   public function int_competences()
   {
      return Cache::rememberForever('Employee.int_competences.'.$this->id, function(){
         $retval = [];
         $performedCompetenceEvals = [];
         foreach(DB::table('user_competence')->rightJoin('competence_levels', 'competence_levels.competence_id', '=', 'user_competence.competence_id')->where('user_id', $this->id)->select('competence_levels.ordinal as ordinal', 'user_competence.*')->get() as $eval)
            $performedCompetenceEvals[$eval->competence_id] = $eval;
         
         foreach($this->int_employeeroles()->orderBy('name')->get() as $role)
         {
            foreach($role->int_competences()->rightJoin('competences', 'competences.id', '=', 'role_competence.competence_id')->orderBy('competences.name')->select('role_competence.*', 'competences.name as name', 'competences.description as description')->get() as $competence)
            {
               if(!array_key_exists($role->id, $retval))
                  $retval[$role->id] = array('id' => $role->id, 'name' => $role->name, 'competences' => array());

               $acceptableLevel = CompetenceLevel::where('id', $competence->acceptable_competence_level_id)->select('id', 'name', 'ordinal')->first();
               $desiredLevel = CompetenceLevel::where('id', $competence->desired_competence_level_id)->select('id', 'name', 'ordinal')->first();
               $achievedLevel = null;
               if(array_key_exists($competence->competence_id, $performedCompetenceEvals))
                  $achievedLevel = $performedCompetenceEvals[$competence->competence_id];

               $retval[$role->id]['competences'][] = array('id' => $competence->id, 'name' => $competence->name, 'description' => $competence->description, 'acceptable_level' => $acceptableLevel, 'desired_level' => $desiredLevel, 'achieved_level' => (null == $achievedLevel) ? null : $achievedLevel);
            }
         }
         
         return $retval;
      });
   }
   
   public function int_processacountabilities()
   {
      return Cache::rememberForever('Employee.int_processacountabilities.'.$this->id, function(){
         $retval = [];
         foreach($this->int_employeeroles as $role)
         {
            foreach(ProcessActivity::where('accountable_role_id', $role->id)->orderBy('name')->get() as $pa)
            {
               if(!array_key_exists($pa->int_process->id, $retval))
                  $retval[$pa->int_process->id] = array('id' => $pa->int_process->id, 'name' => $pa->int_process->name, 'activities' => array());
               
               $retval[$pa->int_process->id]['activities'][] = array('id' => $pa->id, 'name' => $pa->name, 'role' => array('id' => $role->id, 'name' => $role->name));
            }
         }
         
         return $retval;
      });
   }

   public function int_processresponsibilities()
   {
      return Cache::rememberForever('Employee.int_processresponsibilities.'.$this->id, function(){
         $retval = [];
         foreach($this->int_employeeroles as $role)
         {
            foreach(ProcessActivity::where('responsible_role_id', $role->id)->orderBy('name')->get() as $pa)
            {
               if(!array_key_exists($pa->int_process->id, $retval))
                  $retval[$pa->int_process->id] = array('id' => $pa->int_process->id, 'name' => $pa->int_process->name, 'activities' => array());
               
               $retval[$pa->int_process->id]['activities'][] = array('id' => $pa->id, 'name' => $pa->name, 'role' => array('id' => $role->id, 'name' => $role->name));
            }
         }
      
         return $retval;
      });
   }
   
   public static function createFromUser($user)
   {
      $destination = new Employee();
      $sourceReflection = new \ReflectionObject($user);
      $destinationReflection = new \ReflectionObject($destination);
      $sourceProperties = $sourceReflection->getProperties();
      foreach ($sourceProperties as $sourceProperty) {
         if ($sourceProperty->isStatic()) {
            continue;
         }

         if (!$sourceProperty->isInitialized($user)) {
            continue;
         }

         $sourceProperty->setAccessible(true);
         $name = $sourceProperty->getName();
         $value = $sourceProperty->getValue($user);
 
         if ($destinationReflection->hasProperty($name)) {
             $propDest = $destinationReflection->getProperty($name);
             $propDest->setAccessible(true);
             $propDest->setValue($destination,$value);
         } else {
             $destination->$name = $value;
         }
      }
      return $destination;
   }
}
