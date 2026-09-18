<?php

namespace App\Models;

use App\Traits\HasCustomProperties;
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
use App\Models\ProvidedObject;
use Illuminate\Support\Facades\DB; 
use App\Traits\HasNotifications;
use App\Traits\HasTags;
use App\Traits\HasMessages;

class RequirementSource extends Model
{
   use HasNotifications, HasTags, HasMessages, HasCustomProperties;

   public static function getPrettyName($plural = false){ if($plural) { return __("Requirement sources"); } else { return __("Requirement source"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];
      
      if(null != $department)
         return [];
            
      // Don't report if user cannot perform any changes anyway
      if((null != $user) && $user->cannot('update', RequirementSource::class))
         return [];
      
      $count = (null == $department) ? RequirementSource::whereNull('not_applicable_at')->whereNull('responsible_user_id')->count() : 0;
      if(!$personalOnly && ($count > 0))
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => RequirementSource::getPrettyName($count > 1).' '.__("without assignment"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/requirements') : null];

      $baseQuery = RequirementSource::query()
         ->whereNull('not_applicable_at');

      if ($user) {
         $baseQuery->where('responsible_user_id', $user->id);
      }

      $count = (clone $baseQuery)
         ->whereHas('int_requirements', function ($q) {
            $q->whereNull('applicable');
         })
         ->count();

      $needsApprovalCount = (clone $baseQuery)
         ->where(function ($q) {
            // Motsvarar: approved_at är null OCH det finns minst ett requirement
            $q->where(function ($q2) {
               $q2->whereNull('approved_at')
                  ->whereHas('int_requirements');
            })
               // Motsvarar: minst ett requirement uppdaterat efter source-approved_at
               ->orWhereHas('int_requirements', function ($rq) {
                  $rq->whereColumn('requirements.updated_at', '>', 'requirement_sources.approved_at');
               });
         })
         ->count();

      if($count > 0)
         $retval[] = ['level' => $user ? 'danger' : 'warning', 'count' => $count, 'text' => RequirementSource::getPrettyName($count > 1).' '. __("pending applicability determination"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/requirements') : null];

      if($needsApprovalCount > 0)
         $retval[] = ['level' => 'warning', 'count' => $needsApprovalCount, 'text' => RequirementSource::getPrettyName($needsApprovalCount > 1).' '. __("needs approval"), 'url' => ((($user != null) && $user->can('index',  get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/inventory/requirements') : null];

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
      
      // No legacy partner-specific restrictions; normal validation is enforced elsewhere.
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'applicabilitymissingcount',
      'requirements',
      'status',
      'tags',
      'needsapproval',
      'messagecount',
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
      if($this->not_applicable_at)
         return ['icon' => 'visibility_off', 'level' => 'info', 'text' => __("This requirement source is set as not applicable")];

      if(!$this->responsible_user_id)
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("A responsible user has not been assigned")];
      
      if($this->getApplicabilitymissingcountAttribute())
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("There are requirements which has not been assessed regarding applicability")];

      if($this->getNeedsapprovalAttribute())
         return ['icon' => 'warning', 'level' => 'warning', 'text' => __("This requirement source needs approval")];

      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
   }

   public function getApplicabilitymissingcountAttribute(): int
   {
      if ($this->relationLoaded('int_requirements')) {
         return $this->int_requirements->whereNull('applicable')->count();
      }
      return $this->int_requirements()->whereNull('applicable')->count();
   }

   public function getRequirementsAttribute(): array
   {
      $collection = $this->relationLoaded('int_requirements')
         ? $this->int_requirements
         : $this->int_requirements()->get();

      return $collection
         ->map(fn($obj) => ['id' => $obj->id, 'reference' => $obj->reference, 'name' => $obj->reference])
         ->all();
   }

   public function getTagsAttribute()
   {
      return $this->tags()->get()->each->setAppends([]);
   }

   public function getNeedsapprovalAttribute(): bool
   {
      if ($this->relationLoaded('int_requirements')) {
         $requirements = $this->int_requirements;
         if ($requirements->isEmpty()) {
            return false;
         }
         if (null === $this->approved_at) {
            return true;
         }
         return $requirements->contains(fn($r) => $r->updated_at > $this->approved_at);
      }

      if (null == $this->approved_at && $this->int_requirements()->count()) {
         return true;
      }
      return $this->int_requirements()
         ->when(null !== $this->approved_at, fn (Builder $q) =>
         $q->where('updated_at', '>', $this->approved_at)
         )
         ->exists();
   }

   public function getMessagecountAttribute(): int
   {
      return $this->relationLoaded('messages')
         ? $this->messages->count()
         : $this->messages()->count();
   }
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'reference',
      'description',
      'created_at',
      'updated_at',
      'responsible_user_id',
      'applicabilitymissingcount',
      'requirements',
      'status',
      'tags',
      'approved_at',
      'needsapproval',
      'messagecount',
      'not_applicable_at',
      'max_sanction_fee',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'responsible_user_id',
      'name',
      'reference',
      'description',
      'max_sanction_fee',
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
      'approve',
      'notapplicable',
      'applicable',
   ];

   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $search = trim(request()->input('search', ''));

      $query = (__CLASS__)::query()
         // Fixar också SQL-bug: OR-kedjan wrappas i en grupp
         ->when($search !== '', function (Builder $query) use ($search) {
            $query->where(function (Builder $q) use ($search) {
               $q->where('name', 'LIKE', '%'.$search.'%')
                  ->orWhere('reference', 'LIKE', '%'.$search.'%')
                  ->orWhere('description', 'LIKE', '%'.$search.'%')
                  ->orWhereHas('tags', fn (Builder $q) =>
                  $q->where('name', 'LIKE', '%'.$search.'%')
                  )
                  ->orWhereHas('int_requirements', fn (Builder $q) =>
                  $q->where('name', 'LIKE', '%'.$search.'%')
                     ->orWhere('reference', 'LIKE', '%'.$search.'%')
                     ->orWhere('description', 'LIKE', '%'.$search.'%')
                     ->orWhere('governance', 'LIKE', '%'.$search.'%')
                  );
            });
         })
         ->when(0 < intval(request()->input('tag_id', 0)), function (Builder $query) {
            $query->whereHas('tags', fn (Builder $q) =>
            $q->where('tags.id', intval(request()->input('tag_id', 0)))
            );
         })
         ->when(1 == intval(request()->input('showmyonly', 0)), function (Builder $query) {
            $query->where('responsible_user_id', auth()->user()->id);
         })
         ->when(0 < intval(request()->input('responsible_user_id', 0)), function (Builder $query) {
            $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
         })
         ->when(0 == intval(request()->input('shownotapplicable', 0)), function (Builder $query) {
            $query->whereNull('not_applicable_at');
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when(
            0 < count(array_filter(array_keys(request()->all()), fn($v) => str_starts_with($v, 'customproperty_'))),
            fn (Builder $query) => (__CLASS__)::getIndexQuery($query)
         )
         // Flyttar hidechecked-filtret till SQL – eliminerar N+1 och PHP-laddning av alla rader
         ->when(request()->input('hidechecked', 0), function (Builder $query) {
            $query->where(function (Builder $q) {
               $q->whereNull('responsible_user_id')
                  ->orWhereHas('int_requirements', fn (Builder $q) =>
                  $q->whereNull('applicable')
                  )
                  ->orWhere(fn (Builder $q) =>
                  $q->whereNull('approved_at')
                     ->whereHas('int_requirements')
                  )
                  ->orWhereHas('int_requirements', fn (Builder $q) =>
                  $q->whereColumn('requirements.updated_at', '>', 'requirement_sources.approved_at')
                  );
            });
         })
         // Eager-load alla relationer som accessorerna använder
         ->with(['int_requirements', 'tags', 'messages'])
         ->orderBy('reference')
         ->orderBy('name');

      if (request()->input('hidechecked', 0)) {
         return $query->get();
      }

      return $query->paginate();
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
         'responsible_user_id' => 'nullable|exists:App\Models\User,id',
         'reference' => 'required',
         'description' => 'nullable',
         'max_sanction_fee' => 'sometimes|nullable|numeric|min:0',
      ];
   }
   
   /**
     * Get requirements
     */
   public function int_requirements() : HasMany
   {
      return $this->hasMany(Requirement::class);
   }

   /**
    * Approve this requirement source
    */
   public function approve()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);

      // Ensure that applicability has been determined for all requirements
      if($this->int_requirements()->whereNull('applicable')->exists())
         abort(400, __('All requirements must have their applicability determined before the requirement source can be approved'));

      // Ensure current user is responsible
      if(auth()->user()->id != $this->responsible_user_id)
         abort(400, __('Only the responsible user can approve the requirement source'));

      DB::table('requirement_sources')->where('id', $this->id)->update(['approved_at' => date("Y-m-d H:i:s")]);

      ActivityLog::addMessage(__("The requirement source was approved by")." ".auth()->user()->name, $this);
   }

   /**
    * Set this requirement source as not applicable
    */
   public function notapplicable()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);

      // Ensure requirement source is not already set as not applicable
      if(null != $this->not_applicable_at)
         abort(400, __('The requirement source is already set as not applicable'));

      // Ensure current user is responsible
      if(auth()->user()->id != $this->responsible_user_id)
         abort(400, __('Only the responsible user can set the requirement source as not applicable'));

      DB::table('requirement_sources')->where('id', $this->id)->update(['not_applicable_at' => date("Y-m-d H:i:s")]);

      ActivityLog::addMessage(__("The requirement source was set not applicable by")." ".auth()->user()->name, $this);
   }

   /**
    * Set this requirement source as applicable
    */
   public function applicable()
   {
      // Ensure correct authorization
      if(!request()->user()->can('update', $this))
         abort(403);

      // Ensure requirement source is not already set as applicable
      if(null == $this->not_applicable_at)
         abort(400, __('The requirement source is already set as applicable'));

      // Ensure current user is responsible
      if(auth()->user()->id != $this->responsible_user_id)
         abort(400, __('Only the responsible user can set the requirement source as applicable'));

      DB::table('requirement_sources')->where('id', $this->id)->update(['not_applicable_at' => null]);

      ActivityLog::addMessage(__("The requirement source was set applicable by")." ".auth()->user()->name, $this);
   }
}


