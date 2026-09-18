<?php

namespace App\Models;

use App\Http\Controllers\FormController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\HasMessages;

class Form extends Model
{
   use HasMessages;
    
   public static function getPrettyName($plural = false){ if($plural) { return __("Forms"); } else { return __("Form"); }}

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
         Validator::make($model->toArray(), $model->getValidationRules())->validate();

         // Validate context
         $expectedContextClass = (null != $model->int_form_template->context) ? 'App\\Models\\'.ucfirst($model->int_form_template->context) : null;
         if(null != request()->input('context_id') && (null == $expectedContextClass))
            abort(400, __("The context of this form is not valid"));
         else if((null != $expectedContextClass) && !$expectedContextClass::where('id', $model->context_id)->exists())
            abort(400, __("The context of this form is not valid"));

         if(!$model->context_type)
            $model->context_type = $expectedContextClass;
         if(!$model->context_id)
            $model->context_id = request()->input('context_id');

         // Validate due date
         if(null != $model->due)
         {
            $due = strtotime($model->due);
            if($due < time())
               abort(400, __("The due date of this form is in the past"));
         }
      });

      static::creating(function ($model) {
         // Copy form data from template
         $model->formdata = $model->int_form_template->formdata;
      });

      static::retrieved(function ($model) {
         if(request()->has('include_formdata'))
         {
            $model->makeVisible(['formdata']);

         }
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'status',
      'uploaded_files',
      'context_object_name',
      'context_object_relations',
      'state',
      'state_pretty',
   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      $allowUpdate = $user->can('update', $this);
      switch($this->getStateAttribute())
      {
         case 'submitted':
         case 'overdue':
         case 'archived':
            $allowUpdate = false;
            break;
         case 'draft':
         case 'answered':
            break;
      }

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $allowUpdate, 'delete' => $user->can('delete', $this)];
   }

   public function getStatusAttribute()
   {
      return ['icon' => 'check', 'level' => 'info', 'text' => ''];
   }

   public function getUploadedFilesAttribute()
   {
      return [];
   }

   public function getContextObjectNameAttribute()
   {
      if($this->context_type)
         return $this->context_type::findOrFail($this->context_id)->name ?? null;
      return null;
   }

   public function getContextObjectRelationsAttribute()
   {
      return Relation::where('relation_type', $this->context_type)
                           ->where('relation_id', $this->context_id)
                           ->orderBy('name')
                           ->select(['id', 'name', 'email'])
                           ->get()
                           ->each
                           ->setAppends([]);
   }

   public function getStateAttribute()
   {
      /*
       * Form lifecycle states:
       * draft: Draft
       * submitted: Submitted for form fill (i.e. an exchange proxy)
       * overdue: Overdue
       * answered: Answered (i.e. form filled and submitted back)
       * archived: Archived (i.e. form filled and archived)
       */

      if(null == $this->uploaded_at)
         return 'draft';

      if($this->archived_at)
         return 'archived';

      if($this->uploaded_at && !$this->downloaded_at)
         return strtotime($this->due.' 23:59:59') < time() ? 'overdue' : 'submitted';

      if($this->finished_at)
         return 'answered';

      return 'unknown';
   }

   public function getStatePrettyAttribute()
   {
      switch($this->getStateAttribute())
      {
         case 'draft':
            return __("Draft");
         case 'submitted':
            return __("Submitted");
         case 'overdue':
            return __("Overdue");
         case 'answered':
            return __("Answered");
         case 'archived':
            return __("Archived");
         default:
            return __("Unknown");
      }
   }

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'form_template_id',
      'context_object_name',
      'responsible_user_id',
      'context_type',
      'context_id',
      'due',
      'context_object_relations',
      'state',
      'state_pretty',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'form_template_id',
      'responsible_user_id',
      'context_type',
      'context_id',
      'due',
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
      'activate',
      'recall',
      'archive',
      'view',
      'downloadFile'
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
         ->when((null != request()->input('context_type', null)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.context_type', 'App\\Models\\'.str_replace('App\\Models\\', '', request()->input('context_type')));
         })
         ->when((null != request()->input('context_id', null)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.context_id', request()->input('context_id'));
         })
         ->when(0 < intval(request()->input('id', 0)), function (Builder $query) {
            $query->where((new (__CLASS__))->getTable().'.id', intval(request()->input('id', 0)));
         })
         ->when(request()->input('hidearchived', 0), function (Builder $query) {
            $query->whereNull((new (__CLASS__))->getTable().'.archived_at');
         })
         ->when(request()->input('hideanswered', 0), function (Builder $query) {
            $query->whereNull((new (__CLASS__))->getTable().'.downloaded_at');
         })
         ->orderBy('name');

      $paginator = $returnCollection->paginate();

      return $paginator;
   }
   
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'name' => ['required', 'string', 'max:255'],
         'description' => ['nullable', 'string'],
         'form_template_id' => ['required', 'integer', Rule::exists('form_templates', 'id')],
         'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
      ];
   }

   public function int_relations() : HasManyThrough
   {
      return $this->hasManyThrough(
         Relation::class,
         FormRelation::class,
         'form_id', // Foreign key on FormRelation table
         'id', // Foreign key on Relation table
         'id', // Local key on Form table
         'relation_id' // Local key on FormRelation table
      );
   }

   public function int_form_template()
   {
      return $this->belongsTo(FormTemplate::class, 'form_template_id');
   }

   public function int_context_object()
   {
      return $this->context_type::findOrFail($this->context_id);
   }

   private function getRenderableFormItems()
   {
      $formdata = json_decode($this->formdata ?? '{}', true);
      if(!is_array($formdata))
         $formdata = [];

      $items = array_values(array_filter($formdata['form_items'] ?? [], 'is_array'));
      usort($items, function($left, $right){
         return (($left['form_chapter_id'] ?? 0) <=> ($right['form_chapter_id'] ?? 0))
            ?: (($left['ordinal'] ?? 0) <=> ($right['ordinal'] ?? 0))
            ?: strcmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
      });

      foreach($items as $itemIndex => &$item)
         $item['__item_lookup_id'] = $item['id'] ?? $itemIndex;
      unset($item);

      return $items;
   }

   private function getRenderableFormItemFiles(array $item)
   {
      $files = [];
      foreach(['uploaded_files', 'attached_files', 'file_uploads', 'attachments'] as $key)
      {
         if(!array_key_exists($key, $item))
            continue;

         $entry = $item[$key];
         if(is_array($entry))
            $files = array_merge($files, array_values($entry));
         else if(null !== $entry)
            $files[] = $entry;
      }

      return $files;
   }

   public function recall(){
      // Validate correct state
      if($this->getStateAttribute() != 'submitted' && $this->getStateAttribute() != 'overdue')
         abort(400, __("Cannot recall this form since it has not been activated"));

      (new FormController($this))->recall();
   }

   public function archive(){
      // Validate correct state
      if($this->getStateAttribute() != 'answered')
         abort(400, __("Cannot archive this form since it has not been answered"));

      $this->archived_at = now();
      $this->save();
   }

   public function activate(){
      // Validate correct state
      if($this->getStateAttribute() != 'draft' && $this->getStateAttribute() != 'answered')
         abort(400, __("Cannot activate this form since it is not in draft or answered state"));

      (new FormController($this))->upload();
   }

   public function view() {
      // Fetch associated object
      $contextObject = $this->int_context_object();
      if(null == $contextObject)
         abort(404);

      // Ensure that user may view this form
      if(!auth()->user()->can('view', $this) && !auth()->user()->can('view', $contextObject))
         abort(403);

      // Validate correct state
       if(($this->getStateAttribute() != 'answered') && ($this->getStateAttribute() != 'archived') && ($this->getStateAttribute() != 'draft'))
          abort(400, __("Cannot view this form since it is not in answered, archived, or draft state"));

       // Render the form
       $html = view('assessment.formview', ['form' => $this]);
       $html = $html->render();
       return ['html' => $html];
   }

   public function downloadFile()
   {
      // Fetch associated object
      $contextObject = $this->int_context_object();
      if(null == $contextObject)
         abort(404);

      // Ensure that user may view this form
      if(!auth()->user()->can('view', $this) && !auth()->user()->can('view', $contextObject))
         abort(403);

     if(($this->getStateAttribute() != 'answered') && ($this->getStateAttribute() != 'archived') && ($this->getStateAttribute() != 'draft'))
        abort(400, __("Cannot download file since the form is not in answered, archived, or draft state"));

     $itemId = request()->input('item_id');
     $fileIndex = intval(request()->input('file_idx', -1));
     if(null === $itemId || $fileIndex < 0)
        abort(404, __("Cannot download file since it is not available"));

     $item = null;
     foreach($this->getRenderableFormItems() as $candidate)
     {
        if((string) ($candidate['__item_lookup_id'] ?? '') === (string) $itemId)
        {
           $item = $candidate;
           break;
        }
     }

     if(null === $item)
        abort(404, __("Cannot download file since it is not available"));

     $files = $this->getRenderableFormItemFiles($item);
     if(!isset($files[$fileIndex]))
        abort(404, __("Cannot download file since it is not available"));

     $file = $files[$fileIndex];
     header('Content-Type: '.$file['mime_type']);
     header('Content-Disposition: attachment; filename="'.str_replace('"', '', $file['filename']).'"');
     header('Content-Length: '.$file['size']);
     die(\Safe\base64_decode($file['content']));
   }
}
