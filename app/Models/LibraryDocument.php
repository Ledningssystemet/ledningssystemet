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

class LibraryDocument extends Model
{
   use DefersRelationAttributeSync;
   public static function getPrettyName($plural = false){ if($plural) { return __("Documents"); } else { return __("Document"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      $retval = [];

      if (null != $department)
         return [];

      // Don't report if user cannot perform any changes anyway
      if ((null != $user) && $user->cannot('update', LibraryDocument::class))
         return [];

      // Unassigned documents
      $count = LibraryDocument::whereNull('responsible_user_id')->count();
      if (!$personalOnly && $count)
         $retval[] = ['level' => 'danger', 'count' => $count, 'text' => LibraryDocument::getPrettyName($count > 1) . ' ' . __("without assignment"), 'url' => ((($user != null) && $user->can('index', get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/management/documentlibrary') : null];

      $pendingpublishing = 0;
      $pendingapproval = 0;
      foreach(LibraryDocument::where('contenttype', 'ledningssystemet/document')->when($user, function ($query) use ($user) { return $query->where('responsible_user_id', $user->id); })->get() as $obj)
      {
         $lastversion = $obj->int_document_versions()->orderBy('major_version', 'desc')->orderBy('minor_version', 'desc')->first();
         if(null == $lastversion)
            $pendingpublishing++;
         else if($lastversion->finished_at && !$lastversion->approved_at)
            $pendingapproval++;
      }

      if ($pendingapproval && !$user)
         $retval[] = ['level' => 'warning', 'count' => $pendingapproval, 'text' => LibraryDocument::getPrettyName($pendingapproval > 1) . ' ' . __("pending approval"), 'url' => ((($user != null) && $user->can('index', get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/management/documentlibrary') : null];
      else if($pendingapproval)
         $retval[] = ['level' => 'warning', 'count' => $pendingapproval, 'text' => LibraryDocument::getPrettyName($pendingapproval > 1) . ' ' . __("pending approval"), 'url' => url()->query('/user/documents'), 'personal' => ($user != null) ];

      if ($pendingpublishing && !$user)
         $retval[] = ['level' => 'warning', 'count' => $pendingpublishing, 'text' => LibraryDocument::getPrettyName($pendingpublishing > 1) . ' ' . __("pending publishing"), 'url' => ((($user != null) && $user->can('index', get_called_class())) || (($user == null) && (null != auth()->user()) && (auth()->user()->can('index', get_called_class())))) ? url()->query('/management/documentlibrary') : null];
      else if($pendingpublishing)
         $retval[] = ['level' => 'warning', 'count' => $pendingpublishing, 'text' => LibraryDocument::getPrettyName($pendingpublishing > 1) . ' ' . __("pending publishing"), 'url' => url()->query('/user/documents'), 'personal' => ($user != null) ];

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
         // Perform validation
         Validator::make($model->toArray(), $model->getValidationRules())->validate();

         // If this is a new object that is a file upload, then check that the file is provided
         if(!$model->exists && $model->hasAttribute('filetype'))
         {
            if('file' == $model->filetype) {
               // Ensure there is a file provided
               if (!$model->contenttype)
                  abort(400, __("You need to provide a file for upload"));
            }
            else if($model->filetype == 'document')
            {
               $model->contenttype = 'ledningssystemet/document';
               $model->filename = '';
               $model->contentlength = 0;
               $model->filecontent = '';
            }
            else
               abort(400, __("Invalid filetype provided"));

            unset($model->filetype);
         }

      });

      // Generate a new document version when a new document is created
      static::created(function ($model) {
         if($model->contenttype == 'ledningssystemet/document')
         {
            $version = new DocumentVersion();
            $version->library_document_id = $model->id;
            $version->major_version = 0;
            $version->minor_version = 1;
            $version->approver_id = $model->responsible_user_id;
            $version->save();
         }
      });
      
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'processes',
      'file',
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

   public function getFileAttribute($file)
   {
      return null;
   }
   
   public function setFileAttribute($file)
   {
      $this->contenttype = $file->getMimeType();
      $this->filename = $file->getClientOriginalName();
      $this->contentlength = $file->getSize();
      $this->filecontent = $file->get();
   }   
   
   
   public function getProcessesAttribute()
   {
      $retval = [];

      foreach($this->int_processes as $obj)
         $retval[] = array('id' => $obj->id, 'name' => $obj->name);
      return $retval; 
   }
   
   public function setProcessesAttribute($value)
   {
      $this->syncRelationAttribute('processes', fn ($model) => $model->int_processes()->sync($value));
   }
   
   public function getStatusAttribute()
   {
      if (!$this->responsible_user_id)
         return ['icon' => 'warning', 'level' => 'danger', 'text' => __("A responsible user has not been assigned")];

      // Special case for ledningssystemet documents
      if("ledningssystemet/document" != $this->contenttype)
         return ['icon' => 'check', 'level' => 'info', 'text' => ''];

      // If there are versions pending approval, then indicate
      $lastversion = $this->int_document_versions()->orderBy('major_version', 'desc')->orderBy('minor_version', 'desc')->first();
      if(null != $lastversion && $lastversion->finished_at && !$lastversion->approved_at)
         return ['icon' => 'check', 'level' => 'warning', 'text' => __("Pending approval")];

      // If no document has been published, then indicate this
      if(0 >= $this->contentlength)
         return ['icon' => 'docs', 'level' => 'warning', 'text' => __("Document has not been published")];

      return ['icon' => 'docs', 'level' => 'info', 'text' => ''];
   }
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'filename',
      'description',
      'contenttype',
      'contentlength',
      'created_at',
      'updated_at',
      'processes',
      'file',
      'responsible_user_id',
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
      'file',
      'processes',
      'filetype',
      'responsible_user_id',
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
         ->when(0 < intval(request()->input('responsible_user_id', 0)), function (Builder $query) {
            $query->where('responsible_user_id', intval(request()->input('responsible_user_id', 0)));
         })
         ->when($user->cannot('create', (new (__CLASS__))), function (Builder $query) use ($user) {
             // If not admin, then user may only index the ones they are responsible for in any way
            $query->where('responsible_user_id', auth()->user()->id);
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
         'name' => 'required',
         'responsible_user_id' => 'nullable|exists:users,id'
      ];
   }

   public function int_responsible_user(): BelongsTo
   {
      return $this->belongsTo(User::class, 'responsible_user_id');
   }

   /**
    * Document versions
    */
   public function int_document_versions() : HasMany
   {
      return $this->hasMany(DocumentVersion::class, 'library_document_id');
   }

   /**
    * Processes
    */
   public function int_processes() : BelongsToMany
   {
      return $this->belongsToMany(Process::class, 'library_document_processes', 'library_document_id', 'process_id');
   }
   
}
