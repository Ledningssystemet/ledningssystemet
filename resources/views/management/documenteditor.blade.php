<?php
// Authorize
if(   (auth()->user()->cannot('update', $document) &&
      (auth()->user()->id != $document->approver_id))
      || $document->approved_at
)
   abort(403);

// Calculate allowed actions
$allowedit = (null == $document->finished_at) && auth()->user()->can('update', $document);
$allowapprove = (null == $document->approved_at) && (null != $document->finished_at) && (auth()->user()->id == $document->approver_id);

// Handle JSON request
if(request()->has('json'))
{
   header("Content-Type: application/json");

   if(!$document->contents)
   {
      echo json_encode((object)['blocks' => []]);
      exit;
   }
   else
   {
      $doc = json_decode($document->contents);
      echo json_encode((object) $doc);
      exit;
   }
}
?>
@extends('layouts.master')
@section('container')
<style>
   .ai-agent {
      margin: 20px 0 20px 0;
      max-width: 800px;
      padding: 10px;
      border: 1px solid #aaaaaa;
      background-color: var(--bs-light);
      border-radius: 10px;
   }
</style>
<script>
   var isDirty = false;

   function setDirtyFlag()
   {
      if(!isDirty){
         $("#previewpdf").hide();
         $("#approve").hide();
         $("#finish").hide();
         $("#reject").hide();
         $("#revert").show();
         isDirty = true;
      }
   }

   function clearDirtyFlag()
   {
      if(isDirty){
         $("#previewpdf").show();
         $("#approve").show();
         $("#finish").hide();
         $("#reject").show();
         $("#revert").hide();
         isDirty = false;
      }
   }

   function normalizeListItem(item) {
      if (item == null || typeof item !== 'object') return;
      if (!('content' in item) || item.content == null) item.content = '';
      if (!('items' in item) || !Array.isArray(item.items)) item.items = [];
      item.items.forEach(normalizeListItem);
   }

   function normalizeBlocks(blocks) {
      if (!Array.isArray(blocks)) return;
      blocks.forEach(block => {
         if (block == null || typeof block !== 'object') return;

         if (!('type' in block) || block.type == null) block.type = 'paragraph';
         if (!('data' in block) || block.data == null || typeof block.data !== 'object') block.data = {};

         switch (block.type) {
            case 'paragraph':
               if (!('text' in block.data) || block.data.text == null) block.data.text = '';
               break;
            case 'header':
               if (!('text' in block.data) || block.data.text == null) block.data.text = '';
               if (!('level' in block.data) || block.data.level == null) block.data.level = 1;
               break;
            case 'list':
               if (!('items' in block.data) || !Array.isArray(block.data.items)) block.data.items = [];
               if (!('style' in block.data) || block.data.style == null) block.data.style = 'unordered';
               block.data.items.forEach(item => normalizeListItem(item));
               break;
         }
      });
   }

   function loadDocument()
   {
      ajaxGet('/management/documenteditor/{{ $document->library_document_id }}?json', function(data){
         var document = data;
         clearDirtyFlag();
         normalizeBlocks(document.blocks);
         if(!document.blocks || !document.blocks.length)
            $('#previewpdf').hide();

         $("#editorJscontainer").documentEditor('load', {
            doc: document,
            change: setDirtyFlag,
            readonly: @php echo($allowedit ? "false" : "true"); @endphp,
         });
      });
   }
</script>
<h1>{{ __("Edit") }} {{ $document->int_library_document->name }} v{{ $document->major_version  }}.{{ $document->minor_version }}</h1>
@if($allowedit && auth()->user()->can('useai'))
   <script>
      function runAiInstruction(){
         var aiInstruction = $('#ai-instruction').val();
         if(aiInstruction.length == 0)
            return;

         $('#editorJscontainer').documentEditor('save', {
            success: function(data){
               ajaxPost('/api/v1/ai/document', { instructions: aiInstruction, document: data }, function(aidata, textStatus, jqXHR){
                  if(null == aidata.retval)
                     showDialog('{{ __("No response was received from AI agent") }}');
                  else
                  {
                     var document = aidata.retval;
                     normalizeBlocks(document.blocks);
                     $("#editorJscontainer").documentEditor('load', {
                        doc: document,
                        change: setDirtyFlag,
                     });

                     setDirtyFlag();
                  }
               });
            }
         });
      }
   </script>
   <div class="ai-agent">
      <h3>{{ __("AI agent") }}</h3>
      <div class="mb-3">
         <label class="form-label">{{ __("AI agent instructions") }}</label>
         <textarea id="ai-instruction" class="form-control" rows="3" placeholder="{{ __("Provide instructions for your AI assistant here") }}"></textarea>
      </div>
      <button class="btn btn-primary btn-sm text-light mb-3" onclick="runAiInstruction();"><span class="material-symbols-rounded">support_agent</span>{{ __("Run AI instruction") }}</button>
   </div>
@endif
@if($allowedit)
<div class="mb-3">
   <label class="form-label">{{ __("Approver") }}</label>
   <select id="approver_id" class="form-select" style="max-width: 500px;" onChange="setDirtyFlag();">
      <option value="">{{ __("No approver") }}</option>
@foreach(\App\Models\User::orderBy('name')->get()->each->setAppends([]) as $user)
   @if($user->can('publishdocument', \App\Models\DocumentVersion::class))
      <option value="{{ $user->id }}"@php echo(($document->approver_id == $user->id) ? " selected=\"selected\"" : ""); @endphp>{{ $user->name }}</option>
   @endif
@endforeach
   </select>
</div>

<button class="btn btn-sm btn-outline-primary mb-3 mt-3" onclick="$('#editorJscontainer').documentEditor('save', {
   success: function(data){
      ajaxPatch('/api/v1/items/DocumentVersion/{{ $document->id }}', { approver_id: $('#approver_id').val(), contents: data }, function(){
         isDirty = false;
         document.location = '/management/documenteditor/{{ $document->library_document_id }}';
      });
   }
});">{{ __("Save draft") }}</button>
<button id="revert" style="display: none;" class="btn btn-sm btn-outline-danger mb-3 mt-3" onclick="confirmDialog('{{__("Revert to last saved version")}}', '{{ __("Are you sure?") }}', function(){loadDocument();}, { warning: true });">{{ __("Revert to last saved version") }}</button>

@if($allowedit)
   <button id="finish" class="btn btn-sm btn-outline-danger mb-3 mt-3" onclick="confirmDialog('{{__("Finish and request approval")}}', '{{ __("Are you sure?") }}', function(){
      ajaxGet('/api/v1/items/DocumentVersion/{{ $document->id }}/finish', function(){
         document.location = '/management/documenteditor/{{ $document->library_document_id }}';
      });
   }
);">{{ __("Finish and request approval") }}</button>
@endif
@endif

<a id="previewpdf" class="btn btn-sm btn-outline-primary mb-3 mt-3" href="/api/v1/items/DocumentVersion/{{ $document->id }}/pdfpreview">{{ __("Preview PDF") }}</a>

@if($allowapprove)
   <button id="approve" class="btn btn-sm btn-outline-danger mb-3 mt-3" onclick="confirmDialog('{{__("Approve and publish")}}', '{{ __("Are you sure?") }}', function(){
      ajaxGet('/api/v1/items/DocumentVersion/{{ $document->id }}/approve', function(){
@if(auth()->user()->can('create', \App\Models\LibraryDocument::class))
         document.location = '/management/documentlibrary';
@else
         document.location = '/user/documents';
@endif
      });
   }
);">{{ __("Approve and publish") }}</button>
   <button id="reject" class="btn btn-sm btn-outline-danger mb-3 mt-3" onclick="confirmDialog('{{__("Reject version")}}', '{{ __("Are you sure?") }}', function(){
      ajaxGet('/api/v1/items/DocumentVersion/{{ $document->id }}/reject', function(){
@if(auth()->user()->can('create', \App\Models\LibraryDocument::class))
         document.location = '/management/documentlibrary';
@else
         document.location = '/user/documents';
@endif
      });
   }
);">{{ __("Reject version") }}</button>

@endif
@if(auth()->user()->can('delete', $document))
   <button id="deletedraft" class="btn btn-sm btn-outline-danger mb-3 mt-3" onclick="confirmDialog('{{__("Delete draft")}}', '{{ __("Are you sure?") }}', function(){
      ajaxDelete('/api/v1/items/DocumentVersion/{{ $document->id }}', function(){
@if(auth()->user()->can('create', \App\Models\LibraryDocument::class))
         document.location = '/management/documentlibrary';
@else
         document.location = '/user/documents';
@endif
      });
   }
);">{{ __("Delete draft") }}</button>
@endif


<div id="editorJscontainer"></div>
<script>
   $(function(){
      const beforeUnloadHandler = (event) => {
         if(isDirty)
         {
            event.preventDefault();
            event.returnValue = true;
         }
      };
      window.addEventListener("beforeunload", beforeUnloadHandler);

      setDirtyFlag();
      loadDocument();
   });
</script>

@endsection
