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

class QualificationRole extends Model
{
      
   protected $table = 'qualification_role';
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Role Qualifications"); } else { return __("Role Qualification"); }}

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
         
         // Ensure that qualification_id is not changed
         if($model->id && $model->isDirty('qualification_id'))
            abort(400, __("You may not change the qualification id post creation"));
         
         // Ensure not already in list
         if(!$model->id)
         {
            if(QualificationRole::where('role_id', $model->role_id)->where('qualification_id', $model->qualification_id)->exists())
               abort(400, __("The qualification is already connected to the same role"));
         }
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
      'qualification_id',
      'mandatory', 
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'role_id',
      'qualification_id',
      'mandatory', 
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
      $returnCollection = (__CLASS__)::rightJoin('qualifications', 'qualifications.id', '=', 'qualification_role.qualification_id')
         ->where('role_id', request()->input('role_id', 0))
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->select('qualification_role.*')
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
         'role_id' => 'required|exists:roles,id',
         'qualification_id' => 'required|exists:qualifications,id',
         'mandatory' => 'required|boolean',
      ];
   }  
}
