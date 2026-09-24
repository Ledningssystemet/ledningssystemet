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

class ObjectMessage extends Model
{
      public static function getPrettyName($plural = false){ if($plural) { return __("Object messages"); } else { return __("Object message"); }}

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
         // Set model connection
         $classname = '\\App\\Models\\'.$model->object_type;
         if(!class_exists($classname))
            abort(404);
         
         $obj = $classname::findOrFail(request()->input('object_id', 0));

         $model->object_type = $obj::class;
         $model->object_id = $obj->id;
         $model->created_by = request()->user()->name;
         
         if(request()->user()->cannot('view', $obj))
            abort(403);
         
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'created_at_pretty',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   
   public function getCreatedAtPrettyAttribute()
   {
      return date("Y-m-d H:i", strtotime($this->created_at));
   }

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'comment',
      'created_by',
      'object_id',
      'object_type',
      'created_at',
      'updated_at',
      'created_at_pretty',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'comment',
      'object_type',
      'object_id',
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
      $classname = '\\App\\Models\\'.request()->input('object_type', '');
      if(!class_exists($classname))
         abort(404);
      
      $obj = $classname::findOrFail(request()->input('object_id', 0));
      
      if(request()->user()->cannot('index', $obj))
         abort(403);
      
      $returnCollection = (__CLASS__)::where('object_type', $obj::class)
         ->where('object_id', $obj->id)
         ->orderBy('updated_at', 'desc');
         
      
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
         'comment' => 'required',
      ];
   }
   
   /**
    * Get the Objects of certain type associated with the ObjectMessage
    */
    public function obj($model)
    {
       return $this->morphOne($model::class, 'object');
    }   
}
