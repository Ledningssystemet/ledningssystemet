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
use App\Models\Concerns\DefersRelationAttributeSync;

class Role extends Model
{
   use DefersRelationAttributeSync;
       
   public static function getPrettyName($plural = false){ if($plural) { return __("Roles"); } else { return __("Role"); }}

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
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'role_users',
      'status',
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
      if($this->external_provider_group_id)
         return ['icon' => 'cloud', 'level' => 'info', 'text' => ''];
      
      return ['icon' => '', 'level' => '', 'text' => ''];
   }
   
   public function getRoleUsersAttribute()
   {
      $retval = [];
      foreach($this->int_users as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval;
   }
   
   public function setRoleUsersAttribute($value)
   {
      $this->syncRelationAttribute('roleusers', fn ($model) => $model->int_users()->sync($value));
   }

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'created_at',
      'updated_at',
      'external_provider_group_id',
      'role_users',
      'status',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'role_users',
      'external_provider_group_id',
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
      $returnCollection = (__CLASS__)::when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%');
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
         'name' => ['required', Rule::unique('roles')->ignore($this->id)],
         'external_provider_group_id' => 'nullable|exists:external_provider_groups,id',
      ];
   }  
   
   /**
    * The users that belong to the role.
    */
   public function int_users() : BelongsToMany
   {
       return $this->belongsToMany(User::class);
   }
   
   /**
    * Qualifications
    */
    public function int_qualification_roles() : BelongsToMany
    {
       return $this->belongsToMany(Qualification::class, 'qualification_role')->withPivot(['mandatory']);
    }   
   
   /**
    * Competences
    */
    public function int_role_competences() : BelongsToMany
    {
       return $this->belongsToMany(RoleCompetence::class, 'role_competence');
    }   
   
   /**
    * Process activities
    */
    public function int_process_activities_accountable() : HasMany
    {
       return $this->hasMany(ProcessActivity::class, 'accountable_role_id');
    }   
    
   /**
    * Process activities
    */
    public function int_process_activities_responsible() : HasMany
    {
       return $this->hasMany(ProcessActivity::class, 'responsible_role_id');
    }   
}
