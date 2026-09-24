<?php

namespace App\Models;

use App\Models\User;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Traits\HasTags;
use App\Traits\HasCustomProperties;


class Agreement extends Model
{
   use HasTags, HasCustomProperties;
   
   public static function getPrettyName($plural = false){ if($plural) { return __("Agreement"); } else { return __("Agreements"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      if($department)
         return [];

      $count = Agreement::whereNull('archived_at')->whereNull('responsible_user_id')->count();
      if(!$personalOnly && $count)
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => Agreement::getPrettyName($count > 1).' '.__("without assignment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/agreements') : null];

      $count = $user ? Agreement::whereNull('archived_at')->where('responsible_user_id', $user->id)->whereRaw('(startdate IS NULL OR enddate IS NULL)')->count() : Agreement::whereNull('archived_at')->whereNull('startdate')->orWhereNull('enddate')->count();
      if($count > 0)
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => Agreement::getPrettyName($count > 1).' '.__("without provided start- and/or end-dates"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index',  get_called_class())))) ? url()->query('/inventory/agreements') : null];
      
      $count = $user ? Agreement::whereNull('archived_at')->where('responsible_user_id', $user->id)->whereNotNull('enddate')->where('enddate', '<', date("Y-m-d"))->count() : Agreement::whereNull('archived_at')->whereNotNull('enddate')->where('enddate', '<', date("Y-m-d"))->count();
      if($count > 0)
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => Agreement::getPrettyName($count > 1).' '.__("that are no longer valid"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/agreements') : null];
      
      $count = $user ? Agreement::whereNull('archived_at')->where('responsible_user_id', $user->id)->whereNotNull('enddate')->where('enddate', '>', date("Y-m-d"))->whereNotNull('reminderdate')->where('reminderdate', '<=', 'CURRENT_DATE()')->count() : Agreement::whereNull('archived_at')->whereNotNull('enddate')->where('enddate', '>', date("Y-m-d"))->whereNotNull('reminderdate')->where('reminderdate', '<=', 'CURRENT_DATE()')->count();
      if($count > 0)
         $retval[] = ['level' => 'warning', 'count' => $count, 'text' => Agreement::getPrettyName($count > 1).' '.__("that are about to expire"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/agreements') : null];
      
      $count = $user ? Agreement::whereNull('archived_at')->where('responsible_user_id', $user->id)->whereNotNull('startdate')->where('startdate', '>', date("Y-m-d"))->count() : Agreement::whereNull('archived_at')->whereNotNull('startdate')->where('startdate', '>', date("Y-m-d"))->count();
      if($count > 0)
         $retval[] = ['level' => 'info', 'count' => $count, 'text' => Agreement::getPrettyName($count > 1).' '.__("that has not yet entered into force"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/agreements') : null];

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

      // Validate before save
      static::saving(function ($model) {
         Validator::make($model->toArray(), $model->getValidationRules())->validate();

         if($model->archived_at)
            abort(400, __("This agreement is archived and cannot be modified"));

      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'status',
      'files',
      'context',
      'tags',
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
      if($this->archived_at)
         return ['icon' => 'inventory_2', 'level' => 'info', 'text' => __('This agreement is archived')];

      if(!$this->responsible_user_id)
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("A responsible user has not been assigned")];
      
      if(!$this->startdate | !$this->enddate)
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("The agreement is not provided with startdate and enddate")];
      
      if(strtotime($this->enddate) < time())
         return ['icon' => 'partner_exchange', 'level' => 'danger', 'text' => __("The agreement is no longer valid")];
      
      if($this->reminderdate && (strtotime($this->reminderdate) < time()))
         return ['icon' => 'partner_exchange', 'level' => 'warning', 'text' => __("The agreement is about to expire")];
      
      if(strtotime($this->startdate) > time())
         return ['icon' => 'schedule', 'level' => 'warning', 'text' => __("The agreement has not yet entered into force")];
      
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
   }
   
   public function getFilesAttribute()
   {
      return DB::table('files')->where('object_type', $this::class)->where('object_id', $this->id)->select(['id', 'filename', 'name', 'description', 'contenttype', 'contentlength'])->get();
   }
   

   public function getContextAttribute()
   {
      if($this->supplier_id)
         return 'Supplier_'.$this->supplier_id;
      else if($this->customer_id)
         return 'Customer_'.$this->customer_id;
      
      return null;   
   }
      
   public function setContextAttribute($value)
   {
      if(null == $value)
         abort(400, __("Invalid context type"));

      // Validate class
      $contextParts=explode('_', $value);
      if(null == $contextParts)
      if(2 !== count($contextParts))
         abort(400, __("Invalid context"));
      
      switch($contextParts[0])
      {
         case 'Supplier':
            $this->supplier_id = $contextParts[1];
            $this->customer_id = null;
            break;
         case 'Customer':
            $this->supplier_id = null;
            $this->customer_id = $contextParts[1];
            break;
         default:
            abort(400, __("Invalid context type"));
      }
   }   
   
   public function getTagsAttribute()
   {
      return $this->tags()->get();
   }
   
  /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'created_at',
      'updated_at',
      'status',
      'name',
      'description',
      'responsible_user_id',
      'startdate',
      'enddate',
      'reminderdate',
      'supplier_id',
      'customer_id',
      'files',
      'context',
      'tags',
      'archived_at',
   ];


   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'responsible_user_id',
      'startdate',
      'enddate',
      'reminderdate',
      'supplier_id',
      'customer_id',
      'context',
   ];


   /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
   protected $casts = [
   ];
    
   /**
    * Custom actions
    */
   public $actions = [
      'archive',
   ];
   
   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $returnCollection = (__CLASS__)::
         where(function (Builder $query) {
           $query->when((request()->has('search') && ("" != trim(request()->input('search')))), function (Builder $query) {
            
               $query->where('name', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhere('description', 'LIKE', '%'.request()->input('search').'%')
                  ->orWhereHas('tags', function (Builder $query) {
                     $query->where('name', 'LIKE', '%'.request()->input('search').'%');
                  });
            });
         })
         ->when((1 == intval(request()->input('showmyonly', 0))), function (Builder $query) {
            $query->where('responsible_user_id',auth()->user()->id);
         })
         ->when((0 < intval(request()->input('supplier_id', 0))), function (Builder $query) {
            $query->where('supplier_id',request()->input('supplier_id', 0));
         })
         ->when((0 < intval(request()->input('customer_id', 0))), function (Builder $query) {
            $query->where('customer_id',request()->input('customer_id', 0));
         })
         ->when(0 < intval(request()->input('responsible_user_id', 0)), function (Builder $query) {
            $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
         })
         ->when((0 < intval(request()->input('tag_id', 0))), function (Builder $query) {
            $query->whereHas('tags', function (Builder $query) {
                  $query->where('tags.id', intval(request()->input('tag_id', 0)));
            });
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when(0 < count(array_filter(array_keys(request()->all()), function($var){ return (0 === strpos($var,'customproperty_')); })), function(Builder $query){ (__CLASS__)::getIndexQuery($query); })
         ->when((0 == intval(request()->input('showarchived', 0))), function (Builder $query) {
            $query->whereNull('archived_at');
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
         'name' => 'required|max:255',
         'responsible_user_id' => 'nullable|exists:App\Models\User,id',
         'supplier_id' => 'nullable|exists:App\Models\Supplier,id',
         'customer_id' => 'nullable|exists:App\Models\Customer,id',
         'startdate' => 'nullable|date',
         'enddate' => 'nullable|date|after:today|after:startdate',
         'reminderdate' => 'nullable|date|before:enddate|after:startdate',
         
      ];
   }

   public function int_supplier() : BelongsTo
   {
      return $this->belongsTo(Supplier::class, 'supplier_id');
   }

   public function int_customer() : BelongsTo
   {
      return $this->belongsTo(Customer::class, 'customer_id');
   }
   
   public function obj()
   {
      return $this->supplier_id ? $this->int_supplier : $this->int_customer;
   }

   /**
    * Archive the agreement
    */
   public function archive()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);

      // Ensure current user is the risk owner
      if(auth()->user()->id != $this->responsible_user_id)
         abort(400, __('Only the responsible user can archive an agreement'));;

      // Ensure a agreement has not been archived already
      if(null != $this->archived_at)
         abort(400, __('The agreement has already been archived'));

      DB::table('agreements')->where('id', $this->id)->update(['archived_at' => date("Y-m-d H:i:s")]);
   }
}
