<?php

namespace Ledningssystemet\Ledningssystemet\Http\Controllers\Api;

use Ledningssystemet\Ledningssystemet\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Ledningssystemet\Ledningssystemet\Models\User;
use Ledningssystemet\Ledningssystemet\Models\Tag;

class TagController extends Controller
{
   /**
    * Get tags.
    */
   public function getTags($modelname, $id)
   {
     // Check if model exist
     $classname = !str_contains($modelname, '\\') && is_subclass_of('Ledningssystemet\\Ledningssystemet\\Models\\'.$modelname, \Illuminate\Database\Eloquent\Model::class) ? 'Ledningssystemet\\Ledningssystemet\\Models\\'.$modelname : null;
     if(! $classname)
      abort(404, __('Could not find the requested item'));
      
     // Ensure requested object exist
     $modelobj = $classname::findOrFail($id);
     
     // Authorize action
     $this->authorize('view', $modelobj);
     
     // Get tags
     $currentTags = $modelobj->tags()->get();

     $retobj = [];
     foreach($currentTags as $tag)
     {
        $retobj[] = array(
         'id' => $tag->id,
         'text' => $tag->name,
         'selected' => true,
         );
     }
     
     // Check if there are any properties
     return response()->json($retobj);
   }
   
   /**
    * Get all tags.
    */
   public function getAllTags($modelname, $id)
   {
     // Check if model exist
     $classname = !str_contains($modelname, '\\') && is_subclass_of('Ledningssystemet\\Ledningssystemet\\Models\\'.$modelname, \Illuminate\Database\Eloquent\Model::class) ? 'Ledningssystemet\\Ledningssystemet\\Models\\'.$modelname : null;
     if(! $classname)
      abort(404, __('Could not find the requested item'));
      
     // Ensure requested object exist
     $modelobj = $classname::findOrFail($id);
     
     // Authorize action
     $this->authorize('view', $modelobj);
     
     // Get currently attached tags
     $currentTags = $modelobj->tags()->get();

     // Get tags
     $retobj = [];
     foreach(Tag::all()->sortBy('name') as $tag)
     {
        $selected = false;
        
        $retobj[] = array(
         'id' => $tag->id,
         'text' => $tag->name,
         'selected' => $currentTags->contains($tag),
         );
     }
     
     // Check if there are any properties
     return response()->json($retobj);
   }
   
   /**
    * Update the specified resource in storage.
    */
   public function setTags($modelname, $id)
   {
     // Check if model exist
     $classname = !str_contains($modelname, '\\') && is_subclass_of('Ledningssystemet\\Ledningssystemet\\Models\\'.$modelname, \Illuminate\Database\Eloquent\Model::class) ? 'Ledningssystemet\\Ledningssystemet\\Models\\'.$modelname : null;
     if(! $classname)
        abort(404, __('Could not find the requested item'));
     
     // Get object
     $modelobj = $classname::findOrFail($id);
     
     // Authorize action
     $this->authorize('update', $modelobj);
     
     // Derive and validate tags
     $tags = [];
     if(request()->has('tags'))
        $tags = request()->input('tags');
     
     if(!is_array($tags))
        abort(400, 'Invalid arguments supplied');
     
     foreach(array_keys($tags) as $tagkey)
     {
        if(!is_numeric($tags[$tagkey]))
        {
           $newtag = new Tag;
           $newtag->name = $tags[$tagkey];
           $newtag->save();
           $tags[$tagkey] = $newtag->id;
        }
        else
        {
           // Check that tag exists
           $tagobj = Tag::find($tags[$tagkey]);
           if(null == $tagobj)
              abort(400, 'Invalid arguments supplied');
        }
     }
     
     // Sync tags
     $modelobj->tags()->sync($tags);

     // Get tags
     $currentTags = $modelobj->tags()->get();

     $retobj = [];
     foreach($currentTags as $tag)
     {
        $retobj[] = array(
         'id' => $tag->id,
         'text' => $tag->name,
         'selected' => true,
         );
     }
     
     // Check if there are any properties
     return response()->json($retobj);
  }
}
