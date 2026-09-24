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

class CompetenceLevel extends Model
{
       
   public static function getPrettyName($plural = false){ if($plural) { return __("Competence levels"); } else { return __("Competence level"); }}

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
      'name',
      'description',
      'competence_id',
      'ordinal',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'competence_id',
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
      'reorder',
   ];
   
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      return (__CLASS__)::where('competence_id', request()->input('competence_id', 0))
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->orderBy('ordinal')->get();
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
         'competence_id' => 'required|exists:competences,id',
      ];
   }
   
   public function int_competence() : BelongsTo
   {
      return $this->belongsTo(Competence::class, 'competence_id');
   }

   /**
   * Re-order the elements
   */
   public function reorder()
   {
      // Authorize action
      if(!request()->user()->can('update', $this))
         abort(403);

      // Get after-object
      $after = request()->input('after');
      $afterobj = (null == $after) ? null : CompetenceLevel::findOrFail($after);
    
      if(null == $afterobj)
         $this->ordinal = 0;
      else
         $this->ordinal = $afterobj->ordinal + 1;

      $this->save();
    
      // Re-order all objects, the slow way...
      $ordinal = 2;
      foreach(CompetenceLevel::where('competence_id', $this->competence_id)->orderBy('ordinal')->get() as $obj)
      {
         $obj->ordinal = $ordinal;
         $obj->save();

         $ordinal += 2;
      }

      return [];
   }   
}
