<?php

namespace App\Models;

use App\Traits\HasCustomProperties;
use Illuminate\Support\Facades\Cache;
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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\DefersRelationAttributeSync;


class User extends Authenticatable
{
   use HasApiTokens, Notifiable, HasCustomProperties, TwoFactorAuthenticatable, HasRoles, DefersRelationAttributeSync;

   protected string $guard_name = 'web';
   
   public static function getPrettyName($plural = false){ if($plural) { return __("Users"); } else { return __("User"); }}

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

      static::saving(function ($model) {
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
         
         // Ensure that we do not create a circular manager structure if manually editing
         if(($model->manager_user_id) && (null != auth()->user()))
         {
            $potentialManagers = $model->int_potential_managers();
            if(!array_key_exists($model->manager_user_id, $potentialManagers))
               abort(400, __("You have selected a manager which is not valid. This would cause a loop in the organizational structure"));
         }
      });
static::creating(function ($model) {
                  $model->password = bcrypt(bcrypt(date("YmdHis")));
      });

   }
  
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'departments',
//      'roles',
      'status',
      'direct_reports',
      'activitiescount',
      'assetscount',
      'controlscount',
      'control_actionscount',
      'findingscount',
      'incidentscount',
      'information_typescount',
      'objectivescount',
      'processescount',
      'process_performance_metricscount',
      'riskscount',
      'supplierscount',
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
         return ['icon' => 'account_circle_off', 'level' => 'warning', 'text' => __('Deactivated user')];

      if($this->external_id)
         return ['icon' => 'cloud', 'level' => 'info', 'text' => __('External user')];
      
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
   }
   
   public function getDepartmentsAttribute()
   {
      $retval = [];
      foreach($this->int_departments as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval;
   }
   
   public function setDepartmentsAttribute($value)
   {
      $this->syncRelationAttribute('departments', fn ($model) => $model->int_departments()->sync($value));
   }
   
//   public function getRolesAttribute()
//   {
//      $retval = [];
//      foreach($this->int_roles as $obj)
//         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
//      return $retval;
//   }
//
//   public function setRolesAttribute($value)
//   {
//      $this->int_roles()->sync($value);
//   }
   
   public function getDirectReportsAttribute()
   {
      $retval = [];
      foreach($this->int_reporting_users(0) as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval;
   }
   
   public function getActivitiescountAttribute()
   {
      return DB::table('activities')->where('responsible_user_id', $this->id)->count();
   }

   public function getAssetscountAttribute()
   {
      return DB::table('assets')->where('responsible_user_id', $this->id)->count();
   }

   public function getControlscountAttribute()
   {
      return DB::table('controls')->where('responsible_user_id', $this->id)->count();
   }

   public function getControlActionscountAttribute()
   {
      return DB::table('control_actions')->where('responsible_id', $this->id)->count();
   }

   public function getIncidentscountAttribute()
   {
      return DB::table('incidents')->where('responsible_user_id', $this->id)->count();
   }

   public function getInformationTypescountAttribute()
   {
      return DB::table('information_types')->where('responsible_user_id', $this->id)->count();
   }

   public function getObjectivescountAttribute()
   {
      return DB::table('objectives')->where('responsible_user_id', $this->id)->count();
   }

   public function getProcessescountAttribute()
   {
      return DB::table('processes')->where('responsible_user_id', $this->id)->count();
   }

   public function getProcessPerformanceMetricscountAttribute()
   {
      return DB::table('process_performance_metrics')->where('responsible_user_id', $this->id)->count();
   }

   public function getRiskscountAttribute()
   {
      return DB::table('risks')->where('riskowner_id', $this->id)->count();
   }

   public function getSupplierscountAttribute()
   {
      return DB::table('suppliers')->where('responsible_user_id', $this->id)->count();
   }

   
   

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'email',
      'enabled',
      'created_at',
      'updated_at',
      'external_id',
      'title',
      'departments',
//      'roles',
      'status',
      'manager_user_id',
      'direct_reports',
      'activitiescount',
      'assetscount',
      'controlscount',
      'control_actionscount',
      'incidentscount',
      'information_typescount',
      'objectivescount',
      'processescount',
      'process_performance_metricscount',
      'riskscount',
      'supplierscount',
      'last_login_at',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'title',
      'email',
      'password',
      'enabled',
//      'roles',
      'departments',
      'manager_user_id',
   ];


   /**
    * The attributes that should be hidden for serialization.
    *
    * @var array<int, string>
    */
   protected $hidden = [
      'password',
      'remember_token',
      'two_factor_secret',
      'two_factor_recovery_codes',
   ];
   
   /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
   protected $casts = [
      'email_verified_at' => 'datetime',
      'two_factor_confirmed_at' => 'datetime',
   ];
   

   /**
    * The public actions available
    */
   public $actions = [
      'issuetoken',
      'reassign',
      'resetpasssword',
   ];
  
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $returnCollection = (__CLASS__)::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('email', 'LIKE', '%'.request()->input('search').'%');
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when(1 == intval(request()->input('hide_disabled', 0)), function (Builder $query) {
            $query->where('enabled', 1);
         })
         ->when(1 == intval(request()->input('hide_enabled', 0)), function (Builder $query) {
            $query->where('enabled', 0);
         })
         ->when(0 < count(array_filter(array_keys(request()->all()), function($var){ return (0 === strpos($var,'customproperty_')); })), function(Builder $query){ (__CLASS__)::getIndexQuery($query); })
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
         'name' => ['required'],
         'email' => ['required', 'email', Rule::unique('users')->ignore($this->id)],
         'manager_user_id' => 'nullable|exists:users,id',
      ];
   }
   

   /**
    * The departments that belong to the user.
    */
   public function int_departments() : BelongsToMany
   {
       return $this->belongsToMany(Department::class);
   }
  
   /**
    * The roles that belong to the user.
    */
   public function int_roles() : BelongsToMany
   {
       return $this->belongsToMany(Role::class);
   }
   
   /**
     * Get notification channels
     */
   public function int_user_notification_channels() : HasMany
   {
      return $this->hasMany(UserNotificationChannel::class);
   }

   public function trustedDevices() : HasMany
   {
      return $this->hasMany(TrustedDevice::class);
   }
   
   /**
     * Get user risk acceptance level
     */
   public function risklevel()
   {
      $risklevel = null;
      foreach($this->int_access_groups()->rightJoin('risk_levels', 'access_groups.risk_level_id', '=', 'risk_levels.id')->select('risk_levels.id', 'risk_levels.ordinal')->get() as $obj)
      {
         if((null == $risklevel) ||
            ($risklevel->ordinal < $obj->ordinal))
            $risklevel = $obj;
      }

      return (null == $risklevel) ? null : RiskLevel::findOrFail($risklevel->id);
   }
   
   /**
    * Get user communication preferences
    */
   public function getUserCommunicationPreferences()
   {
      // Try to get settings
      $usersettings = DB::table('user_status_email_settings')->where('user_id', $this->id)->first();

      return array(
         'monday' => (null == $usersettings) ? false : $usersettings->monday,
         'tuesday' => (null == $usersettings) ? false : $usersettings->tuesday,
         'wednesday' => (null == $usersettings) ? false : $usersettings->wednesday,
         'thursday' => (null == $usersettings) ? false : $usersettings->thursday,
         'friday' => (null == $usersettings) ? false : $usersettings->friday,
         'saturday' => (null == $usersettings) ? false : $usersettings->saturday,
         'sunday' => (null == $usersettings) ? false : $usersettings->sunday,
      );
   }
   

   /**
    * Set user communication preferences
    */
   public function setUserCommunicationPreferences($usersettings)
   {
      DB::table('user_status_email_settings')
         ->updateOrInsert(['user_id' => $this->id], $usersettings);
         
      return $this->getUserCommunicationPreferences();
   }  


   /**
    * Issue a new API token
    */
   public function issuetoken(){
      if(request()->user()->cannot('create', \App\Models\PersonalAccessToken::class))
         abort(403);
      
      return $this->createToken(request()->input('name', date("Y-m-d H:i:s")));

   }      
   
   /**
    * Get a list of all reporting users
    */
   public function int_reporting_users($depth=null) {
      $retval = [];
      foreach(User::where('manager_user_id', $this->id)->get() as $obj)
      {
         $retval[$obj->id] = $obj;
         if((null === $depth) || ($depth > 0))
         {
            foreach($obj->int_reporting_users((null === $depth) ? null : $depth-1) as $subobj)
               $retval[$subobj->id] = $subobj;
         }
      }
      
      return $retval;
   }

   /**
    * Get a list of potential managers (i.e. without causing circular reference)
    */
   public function int_potential_managers() {
      return Cache::rememberForever('User.int_potential_managers.'.$this->id, function(){
         $reports = $this->int_reporting_users();
         $retval = [];
         foreach(User::get() as $obj)
         {
            if(($obj->id != $this->id) && !array_key_exists($obj->id, $reports))
               $retval[$obj->id] = $obj;
         }
         
         return $retval;
      });
   }
   
   /**
    * Get manager object
    */
   public function int_manager() : BelongsTo
   {
      return $this->belongsTo(User::class, 'manager_user_id');
   }
   
   
   
   /**
    * Get all user qualifications necessary for role, including information on evaluation
    */
   public function int_mandatory_qualifications()
   {
      return(Qualification::where('role_user.user_id', $this->id)
         ->join('qualification_role', 'qualification_role.qualification_id', '=', 'qualifications.id')
         ->join('role_user', 'role_user.role_id', '=', 'qualification_role.role_id')
         ->leftJoin('qualification_user', function (\Illuminate\Database\Query\JoinClause $join) {
            $join->on('qualification_user.qualification_id', '=', 'qualifications.id')
                  ->on('qualification_user.user_id', '=', 'role_user.user_id');
         })
         ->select('qualifications.*', 'qualification_user.*')
         ->distinct()
      );
   }
    
   /**
    * Get all user qualifications achieved
    */
   public function int_qualifications()
   {
      return (Qualification::where('qualification_user.user_id', $this->id)
            ->rightJoin('qualification_user', 'qualification_user.qualification_id', '=', 'qualifications.id')
            ->select('qualification_user.*', 'qualifications.name'));      
   }
   

   public static function createFromEmployee($user)
   {
      $destination = new User();
      $sourceReflection = new \ReflectionObject($user);
      $destinationReflection = new \ReflectionObject($destination);
      $sourceProperties = $sourceReflection->getProperties();
      foreach ($sourceProperties as $sourceProperty) {
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

   public function int_access_groups(): MorphToMany
   {
      return $this->morphToMany(AccessGroup::class, 'model', 'access_group_user');
   }


   /**
    * Re-assign objects associated with this department
    */
   public function reassign()
   {
      if(auth()->user()->cannot('update', __CLASS__))
         abort(403);

      if(request()->has('activities') && ($this->id != request()->input('activities')))
         DB::table('activities')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('activities') ]);

      if(request()->has('assets') && ($this->id != request()->input('assets')))
         DB::table('assets')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('assets') ]);

      if(request()->has('controls') && ($this->id != request()->input('controls')))
         DB::table('controls')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('controls') ]);

      if(request()->has('control_actions') && ($this->id != request()->input('control_actions')))
         DB::table('control_actions')->where('responsible_id', $this->id)->update(['responsible_id' => request()->input('control_actions') ]);


      if(request()->has('findings') && ($this->id != request()->input('findings')))
         DB::table('findings')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('findings') ]);


      if(request()->has('incidents') && ($this->id != request()->input('incidents')))
         DB::table('incidents')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('incidents') ]);


      if(request()->has('information_types') && ($this->id != request()->input('information_types')))
         DB::table('information_types')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('information_types') ]);


      if(request()->has('objectives') && ($this->id != request()->input('objectives')))
         DB::table('objectives')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('objectives') ]);


      if(request()->has('processes') && ($this->id != request()->input('processes')))
         DB::table('processes')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('processes') ]);


      if(request()->has('process_performance_metrics') && ($this->id != request()->input('process_performance_metrics')))
         DB::table('process_performance_metrics')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('process_performance_metrics') ]);


      if(request()->has('risks') && ($this->id != request()->input('risks')))
         DB::table('risks')->where('riskowner_id', $this->id)->update(['riskowner_id' => request()->input('risks') ]);


      if(request()->has('suppliers') && ($this->id != request()->input('suppliers')))
         DB::table('suppliers')->where('responsible_user_id', $this->id)->update(['responsible_user_id' => request()->input('suppliers') ]);
      
      return "";
   }
   
   /**
    * Send password reset link
    */
   public function resetpasssword()
   {
      Log::info('Password reset requested for '.$this->email.' using mailer '.config('mail.default'));
      $status = \Illuminate\Support\Facades\Password::broker()->sendResetLink(['email' => $this->email]);

      if('passwords.sent' !== $status) {
         \Illuminate\Support\Facades\Log::warning('Password reset link could not be sent for '.$this->email.': '.$status);
         abort(400, __($status));
      }

      return "";
   }      
}
