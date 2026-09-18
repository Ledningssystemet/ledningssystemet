<?php

namespace App\Models;

use App\Traits\HasCustomProperties;
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
use App\Models\Concerns\DefersRelationAttributeSync;


class Department extends Model
{
   use DefersRelationAttributeSync, HasCustomProperties;
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Departments"); } else { return __("Department"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      if ($personalOnly)
         return [];

      if ((null != $user) && $user->cannot('update', Department::class))
         return [];

      $retval = [];
      $url = ((($user != null) && $user->can('index', get_called_class())) ||
         (($user == null) && (null != auth()->user()) && auth()->user()->can('index', get_called_class())))
         ? url()->query('/systemadmin/departments')
         : null;

      if (null == $department) {
         $table = (new self())->getTable();

         // Count departments with no users assigned
         $missingUsersCount = Department::doesntHave('int_users')->count();

         if ($missingUsersCount)
            $retval[] = ['level' => 'warning', 'count' => $missingUsersCount, 'text' => Department::getPrettyName($missingUsersCount > 1).' '.__("without any assigned users"), 'url' => $url];

      }

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
         
         // Loop prevention for parent departments
         $deps = [];
         $curobj = $model;
         for($depth = 0; $depth < 100; $depth++)
         {
            $deps[] = $curobj->id;
            
            if(null == $curobj->parent_department_id)
               break;
            
            if(in_array($curobj->parent_department_id, $deps))
               abort(400, __("Cannot have the assigned department as parent department because it would not generate i viable organization structure"));
            
            $curobj = $curobj->int_parent_department;
         }               
      });

      
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'department_users',
      'status',
      'riskcount',
      'departmentriskcount',
      'processcount',
      'findingcount',
      'departmentfindingcount',
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
      if(!$this->int_users()->exists())
         return ['icon' => 'warning', 'level' => 'warning', 'text' => __("The department has no assigned users")];
      
      if($this->external_provider_group_id)
         return ['icon' => 'cloud', 'level' => 'info', 'text' => ''];
      
      return ['icon' => '', 'level' => '', 'text' => ''];
   }
   
   public function getDepartmentUsersAttribute()
   {
      return Cache::rememberForever('Department.getDepartmentUsersAttribute.'.$this->id, function(){
         $retval = [];
         foreach($this->int_users as $obj)
            $retval[] = array('id' => $obj->id, 'name' => $obj->name);
            
         return $retval;
      });
   }
   
   public function setDepartmentUsersAttribute($value)
   {
      $this->syncRelationAttribute('departmentusers', fn ($model) => $model->int_users()->sync($value));
   }

   public function getRiskcountAttribute()
   {
      return $this->int_risks()->count();
   }

   public function getDepartmentriskcountAttribute()
   {
      return $this->int_department_risks()->count();
   }
   
   public function getProcesscountAttribute()
   {
      return $this->int_processes()->count();
   }

   public function getFindingcountAttribute()
   {
      return $this->int_findings()->count();
   }

   public function getDepartmentfindingcountAttribute()
   {
      return $this->int_department_findings()->count();
   }

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'created_at',
      'updated_at',
      'external_provider_group_id',
      'department_users',
      'status',
      'riskcount',
      'departmentriskcount',
      'processcount',
      'findingcount',
      'departmentfindingcount',
      'site_id',
      'parent_department_id',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'external_provider_group_id',
      'department_users',
      'site_id',
      'parent_department_id',
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
      'reassign',
   ];
   
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $returnCollection = (__CLASS__)::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%');
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
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
         'name' => [
            'required',
            Rule::unique('departments')->ignore($this->id),
         ],
         'site_id' => 'nullable|exists:App\Models\Site,id',
         'parent_department_id' => 'nullable|exists:App\Models\Department,id',
      ];
   }

    
   /**
    * Get the Users associated with the object
    */
    public function int_users() : BelongsToMany
    {
       return $this->belongsToMany(User::class);
    }   
    
   /**
    * Is current user member?
    */
   public function currentUserIsMember()
   {
      return (0 < DB::table('department_user')
         ->where('department_id', $this->id)
         ->where('user_id', auth()->user()->id)
         ->count());
   }

   /**
     * Get risks associated with this object
     */
   public function int_risks() : MorphMany
   {
     return $this->morphMany(Risk::class, 'context');
   }

   /**
     * Get risks which are owned by this department
     */
   public function int_department_risks() : HasMany
   {
     return $this->hasMany(Risk::class, 'department_id');
   }

   /**
     * Get processes associated with this object
     */
   public function int_processes() : HasMany
   {
     return $this->hasMany(Process::class, 'department_id');
   }
   
   /**
     * Get findings associated with this process
     */
   public function int_findings() : MorphMany
   {
     return $this->morphMany(Finding::class, 'context');
   }

   /**
    * Get findings which are owned by this department
    */
   public function int_department_findings() : HasMany
   {
      return $this->hasMany(Finding::class, 'department_id');
   }

   public function int_site() : BelongsTo
   {
      return $this->belongsTo(Site::class, 'site_id');
   }
   
   public function int_parent_department() : BelongsTo
   {
      return $this->belongsTo(Department::class, 'parent_department_id');
   }
   
   /**
    * Re-assign objects associated with this department
    */
   public function reassign()
   {
      if(auth()->user()->cannot('update', __CLASS__))
         abort(403);
      
      if(request()->has('processes') && ($this->id != request()->input('processes')))
         DB::table('processes')->where('department_id', $this->id)->update(['department_id' => request()->input('processes') ]);
      
      if(request()->has('risks') && ($this->id != request()->input('risks')))
         DB::table('risks')->where('department_id', $this->id)->update(['department_id' => request()->input('risks') ]);

      if(request()->has('findings') && ($this->id != request()->input('findings')))
         DB::table('findings')->where('department_id', $this->id)->update(['department_id' => request()->input('findings') ]);
      
      return "";
   }
}
