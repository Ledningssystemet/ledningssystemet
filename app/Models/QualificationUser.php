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

class QualificationUser extends Model
{
      
   protected $table = 'qualification_user';
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Employee qualifications"); } else { return __("Employee qualification"); }}

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
         
         if($model->planned_at && $model->finished_at && (strtotime($model->planned_at) < strtotime($model->finished_at)))
            abort(400, __("If a qualification is achieved, the planned date must be in the future"));
         
         if($model->expires_at && !$model->finished_at)
            abort(400, __("A qualification cannot expire if it has not been achieved"));
         
         if($model->expires_at && $model->finished_at && (strtotime($model->expires_at) < strtotime($model->finished_at)))
            abort(400, __("A qualification cannot expire before it has been finished"));
         
         // Ensure that qualification_id is not changed
         if($model->id && $model->isDirty('qualification_id'))
            abort(400, __("You may not change the qualification id post creation"));
         
         // Ensure not already in list
         if(!$model->id)
         {
            if(QualificationUser::where('user_id', $model->role_id)->where('user_id', $model->user_id)->exists())
               abort(400, __("The qualification is already connected to the same employee"));
         }
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'verificatefile',
      'qualification_expires',
   ];
   

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }
   
   public function setVerificatefileAttribute($uploadedfile)
   {
      $this->contenttype = $uploadedfile->getMimeType();
      $this->filename = $uploadedfile->getClientOriginalName();
      $this->file = $uploadedfile->get();
   }   
   
   public function getQualificationExpiresAttribute()
   {
      return $this->int_qualification->expires;
   }
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'user_id',
      'qualification_id',
      'note',
      'planned_at',
      'finished_at',
      'expires_at',
      'filename',
      'name',
      'qualification_expires',
   ];
   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'user_id',
      'qualification_id',
      'note',
      'planned_at',
      'finished_at',
      'expires_at',
      'verificatefile',
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
      'download',
   ];
    
    
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $returnCollection = (__CLASS__)::rightJoin('qualifications', 'qualifications.id', '=', 'qualification_user.qualification_id')
         ->where('user_id', request()->input('user_id', 0))
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->select('qualification_user.*')
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
         'user_id' => 'required|exists:users,id',
         'qualification_id' => 'required|exists:qualifications,id',
         'planned_at' => 'nullable|date',
         'finished_at' => 'nullable|date',
         'expires_at' => 'nullable|date',
      ];
   }  
   
   public function int_qualification() : BelongsTo
   {
      return $this->belongsTo(Qualification::class, 'qualification_id');
   }

   public function int_user() : BelongsTo
   {
      return $this->belongsTo(User::class, 'user_id');
   }

   public function download(){
      if(auth()->user()->cannot('view', $this))
         abort(403);
      
      if(null == $this->filename)
         abort(404);

     header('Content-Type: '.$this->contenttype);
     header('Content-Disposition: attachment; filename="'.$this->filename.'"');
     header('Content-Length: '.strlen($this->file));
     die($this->file);
   }
}
