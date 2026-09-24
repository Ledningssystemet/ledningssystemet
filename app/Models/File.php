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

class File extends Model
{
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
         $obj = $model->obj();

         $model->object_type = $obj::class;
         $model->object_id = $obj->id;
         $model->created_by = request()->user()->name;
         $model->name = $model->name ? $model->name : $model->filename;
         
         if(request()->user()->cannot('update', $obj))
            abort(403);
         
         // Check if valid context
         switch($obj::class)
         {
            case 'App\\Models\\Agreement':
            case 'App\\Models\\Customer':
            case 'App\\Models\\Supplier':
               break;
            default:
               abort(400, __("This object does not support file upload"));
         }
         
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'created_at_pretty',
      'file',
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


   public function getFileAttribute($file)
   {
      return null;
   }
   
   public function setFileAttribute($file)
   {
      switch($file->getError())
      {
         case 0:
            break;
         case 1:
            abort(400, __("The uploaded file is too large. Maximum upload size is").' '.File::getSizeText(File::maxUploadSize()));
         default:
            abort(400, __("The upload failed"));;
      }
      
      $this->contenttype = $file->getMimeType();
      $this->filename = $file->getClientOriginalName();
      $this->contentlength = $file->getSize();
      $this->contents = $file->get();
   }     
   
   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'access',
      'id',
      'name',
      'description',
      'created_by',
      'object_id',
      'object_type',
      'created_at',
      'updated_at',
      'created_at_pretty',
      'filename',
      'contenttype',
      'contentlength',
   ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'name',
      'description',
      'object_type',
      'object_id',
      'file',
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
      $classname = '\\App\\Models\\'.request()->input('object_type', '');
      if(!class_exists($classname))
         abort(404);
      
      $obj = $classname::findOrFail(request()->input('object_id', 0));
      
      if(request()->user()->cannot('view', $obj))
         abort(403);
      
      return( (__CLASS__)::where('object_type', $obj::class)
         ->where('object_id', $obj->id)
         ->orderBy('updated_at', 'desc')
         ->select(['id', 'created_by', 'name', 'description', 'filename', 'contenttype', 'contentlength', 'created_at', 'updated_at', 'object_type', 'object_id'])
         ->get());
   }
   
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
         'filename' => 'required|max:255',
      ];
   }
   
   /**
    * Download file
    */
    
   public function download()
   {
      // Check that allowed
      if(auth()->user()->cannot('view', $this))
         abort(403);
      
      // Output contents
      header('Content-Type: '.$this->contenttype);
      header('Content-Disposition: attachment; filename="'.$this->filename.'"');
      header('Content-Length: '.$this->contentlength);
      die($this->contents);
   }
   
   /**
    * Get the Objects of certain type associated with the file
    */
    public function obj()
    {
         // Set model connection
         $classname = (false !== strpos($this->object_type, 'App\\')) ? $this->object_type : '\\App\\Models\\'.$this->object_type;
         if(!class_exists($classname))
            abort(404);
         
         $obj = $classname::findOrFail($this->object_id);
         return $obj;
    }   
    
    
   /**
     * Convert bytes to more standard unit 
     */
   public static function getSizeText($size, $unit="")
   {
      if( (!$unit && $size >= 1<<30) || $unit == "GB")
         return number_format($size/(1<<30),2)."GB";
      
      if( (!$unit && $size >= 1<<20) || $unit == "MB")
         return number_format($size/(1<<20),0)."MB";
      
      if( (!$unit && $size >= 1<<10) || $unit == "KB")
         return number_format($size/(1<<10),0)."KB";
      
      return number_format($size)." B";
   }     
    
   /**
    * Get max upload size
    */
   public static function maxUploadSize()
   {
      static $max_size = -1;

      if ($max_size < 0)
      {
         $post_max_size = File::parse_size(ini_get('post_max_size'));
         
         if ($post_max_size > 0)
            $max_size = $post_max_size;

         $upload_max = File::parse_size(ini_get('upload_max_filesize'));
         if ($upload_max > 0 && $upload_max < $max_size)
            $max_size = $upload_max;
      }
      
      return $max_size;
   }

   public static function parse_size($size)
   {
      $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
      $size = preg_replace('/[^0-9\.]/', '', $size);
      if ($unit)
         return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
      else
         return round($size);
   }    
   
   public static function getPrettyName($plural = false){ if($plural) { return __("Files"); } else { return __("File"); }}

   /* Retrieve status for the entire collection of objects */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      return [];
   }
}
