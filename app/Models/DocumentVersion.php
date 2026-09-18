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
use App\Http\Controllers\DocumentController;
use function Safe\eio_seek;

class DocumentVersion extends Model
{
   public static function getPrettyName($plural = false){ if($plural) { return __("Document versions"); } else { return __("Document version"); }}

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
         // Perform validation
         Validator::make($model->toArray(), $model->getValidationRules())->validate();
      });

      // Generate a new document version when a new document is created
      static::created(function ($model) {
      });
      
      // Prevent deletion of partner provided documents
      static::deleting(function ($model) {
      });
   }
   
   /**
    * Appended attributes
    */
   protected $appends = [
      'access',
      'library_document',

   ];

   public function getAccessAttribute($user = null)
   {
      if(null == $user)
         $user = auth()->user();

      if(null == $user)
         return null;

      return ['create' => $user->can('create', $this), 'read' => $user->can('view', $this), 'update' => $user->can('update', $this), 'delete' => $user->can('delete', $this)];
   }

   public function getLibraryDocumentAttribute()
   {
      return $this->int_library_document->toArray();
   }

   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'id',
      'access',
      'created_at',
      'updated_at',
      'approved_at',
      'library_document_id',
      'contents',
      'major_version',
      'minor_version',
      'approver_id',
      'finished_at',
      'library_document',
    ];

   
   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
      'responsible_user_id',
      'contents',
      'approver_id',
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
      'pdfpreview',
      'approve',
      'reject',
      'finish',
   ];

   /**
    * Index-function used for fetching multiple items via API
    */
   public static function index(User $user)
   {
      $returnCollection = (__CLASS__)
         ::leftJoin('library_documents', 'library_documents.id', '=', 'document_versions.library_document_id')
         ->where(function (Builder $query) use ($user){
            $query->where('library_documents.responsible_user_id', $user->id)
                  ->orWhere('document_versions.approver_id', $user->id);
         })
         ->when(1 == intval(request()->input('pending_approval', 0)), function (Builder $query) {
            $query->whereNull('document_versions.approved_at')
               ->whereNotNull('document_versions.finished_at')
               ->where('document_versions.approver_id', '!=', 'library_documents.responsible_user_id');
         })
         ->select('document_versions.*')
         ->get();

      // Filter out document versions that are not the latest version for each library document
      $returnCollection = $returnCollection->filter(function($item) {
         return ($item->id == DocumentVersion::where('library_document_id', $item->library_document_id)->orderBy('major_version', 'desc')->orderBy('minor_version', 'desc')->firstOrFail()->id);
      });

      return $returnCollection;
   }
   /**
    * Validation rules
    *
    * @var array<int, string>
    */
   public function getValidationRules()
   {
      return [
      ];
   }

   /**
    * Get approver
    */
   public function int_approver() : BelongsTo
   {
      return $this->belongsTo(User::class, 'approver_id');
   }

   /**
    * Library document
    */
   public function int_library_document() : BelongsTo
   {
      return $this->belongsTo(LibraryDocument::class, 'library_document_id');
   }

   /**
    * Internal function for converting json structure to xml structure
    */
   private static function convertJsonToXml(&$json, &$xml)
   {
      foreach($json as $key => $value)
      {
         if(is_array($value))
         {
            $subnode = $xml->ownerDocument->createElement(is_numeric($key) ? 'item' : $key);
            $xml->appendChild($subnode);
            self::convertJsonToXml($value, $subnode);
         }
         else
         {
            $subnode = $xml->ownerDocument->createElement($key, htmlspecialchars($value));
            $xml->appendChild($subnode);
         }
      }
   }

   public function generatePdf($draft = false)
   {
      // Ensure contents is set
      if(!$this->contents)
         abort(400, __('This document has no contents'));

      // Convert contents from json to XML for usage with dompdf
      $xml = new \DOMDocument();
      $xml->formatOutput = true;
      $rootnode = $xml->appendChild($xml->createElement("document"));
      $json = json_decode($this->contents, true);

      $this::convertJsonToXml($json, $rootnode);

      // Add name of document to xml
      $rootnode->appendChild($xml->createElement("doc_name", htmlspecialchars($this->int_library_document->name)));
      $rootnode->appendChild($xml->createElement("doc_author", htmlspecialchars($this->int_library_document->int_responsible_user ? $this->int_library_document->int_responsible_user->name : '')));
      $rootnode->appendChild($xml->createElement("doc_approver", htmlspecialchars($this->int_approver ? $this->int_approver->name : '')));
      $rootnode->appendChild($xml->createElement("creation_date", date("Y-m-d")));
      $rootnode->appendChild($xml->createElement("creation_time_hour", date("H")));
      $rootnode->appendChild($xml->createElement("creation_time_minute", date("i")));
      $rootnode->appendChild($xml->createElement("creation_time_seconds", date("s")));
      $rootnode->appendChild($xml->createElement("doc_major", htmlspecialchars($this->major_version)));
      $rootnode->appendChild($xml->createElement("doc_minor", htmlspecialchars($this->minor_version)));
      $rootnode->appendChild($xml->createElement("draft", $draft ? "true" : "false"));

      // Load xslt
      $xslt = file_get_contents(resource_path('xslt/DocumentVersion/Default_'.app()->getLocale().'.xslt'));

      // Generate pdf
      $pdf = DocumentController::generatePdf($xml->saveXML(), $xslt, $draft);

      return $pdf;
   }

   /**
    * Reject
    */
   public function reject()
   {
      if(($this->approver_id != auth()->user()->id) && ($this->int_library_document->responsible_user_id != auth()->user()->id))
         abort(400, __('You are not allowed to approve this document'));

      // Ensure this document is not already approved
      if($this->approved_at)
         abort(400, __('This document has already been approved'));

      // Ensure this document is finished
      if(!$this->finished_at)
         abort(400, __('This document has not yet been finished'));

      // Ensure that this is the last version available for this library document
      $lastversion = DocumentVersion::where('library_document_id', $this->library_document_id)->orderBy('major_version', 'desc')->orderBy('minor_version', 'desc')->firstOrFail();
      if($lastversion->id != $this->id)
         abort(400, __('This document is not the last version available'));

      $newDocVersion = $this->replicate();

      // Update versions
      $newDocVersion->minor_version++;
      $newDocVersion->finished_at = null;
      $newDocVersion->save();
   }

   /**
    * Approve
    */
   public function approve()
   {
      if($this->approver_id != auth()->user()->id)
         abort(400, __('You are not allowed to approve this document'));

      // Ensure this document is not already approved
      if($this->approved_at)
         abort(400, __('This document has already been approved'));

      // Ensure this document is finished
      if(!$this->finished_at && ($this->approver_id != $this->int_library_document->responsible_user_id))
         abort(400, __('This document has not yet been finished'));
      else if(!$this->finished_at)
         $this->finished_at = now();

      if(!$this->int_library_document->responsible_user_id)
         abort(400, __('This document does not have a responsible user assigned'));


      // Ensure that this is the last version available for this library document
      $lastversion = DocumentVersion::where('library_document_id', $this->library_document_id)->orderBy('major_version', 'desc')->orderBy('minor_version', 'desc')->firstOrFail();
      if($lastversion->id != $this->id)
         abort(400, __('This document is not the last version available'));

      // Generate the PDF and save it to the library document
      $this->major_version = $lastversion->major_version + 1;
      $this->minor_version = 0;
      $this->approved_at = now();

      $pdf = $this->generatePdf(false);
      $this->int_library_document->filename="doc{$this->int_library_document->id}_{$this->major_version}.{$this->minor_version}.pdf";
      $this->int_library_document->contentlength = strlen($pdf);
      $this->int_library_document->filecontent = $pdf;
      $this->int_library_document->save();

      // Update versions
      $this->save();
   }


   /**
    * Finish
    */
   public function finish()
   {
      if($this->int_library_document->responsible_user_id != auth()->user()->id)
         abort(400, __('You are not allowed to edit this document'));

      // Ensure this document is not already approved
      if($this->approved_at)
         abort(400, __('This document has already been approved'));

      // Ensure this document is finished
      if($this->finished_at)
         abort(400, __('This document has already been finished'));

      if(!$this->approver_id)
         abort(400, __('You cannot finish this document until an approver has been assigned'));

      // Ensure that this is the last version available for this library document
      $lastversion = DocumentVersion::where('library_document_id', $this->library_document_id)->orderBy('major_version', 'desc')->orderBy('minor_version', 'desc')->firstOrFail();
      if($lastversion->id != $this->id)
         abort(400, __('This document is not the last version available'));

      $this->finished_at = now();
      $this->save();
   }

   /**
    * Preview document as pdf
    */
   public function pdfpreview()
   {
      // Validate authorization
      if (auth()->user()->cannot('view', $this))
         abort(403);

      // Output pdf to browser
      header('Content-Type: application/pdf');
      echo $this->generatePdf(true);
      exit;
   }
}
