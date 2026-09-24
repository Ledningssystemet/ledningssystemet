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

class FormRelation extends Model
{
      
   protected $table = 'form_relations';
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Form relations"); } else { return __("Form relation"); }}

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
      'form_id',
      'relation_id',
      'created_at',
      'updated_at',
   ];
   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'form_id',
      'relation_id',
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
      $returnCollection = (__CLASS__)::rightJoin('relations', 'relations.id', '=', 'form_relations.relation_id')
         ->where('form_id', request()->input('form_id', 0))
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->select('form_relations.*')
         ->orderBy('relations.name');
         
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
         'form_id' => 'required|exists:forms,id',
         'relation_id' => 'required|exists:relations,id',
      ];
   }

   public function int_form() : BelongsTo
   {
      return $this->belongsTo(Form::class, 'form_id');
   }

   public function int_relation() : BelongsTo
   {
      return $this->belongsTo(Relation::class, 'relation_id');
   }

}
