<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Cache;

trait HasTags {
   
   public static function allUsedTags()
   {
      return \App\Models\Tag::leftJoin('object_tags', 'object_tags.tag_id', '=', 'tags.id')->where('object_tags.object_tags_type', get_called_class())->select(['tags.*'])->distinct()->orderBy('tags.name')->get()->each->setAppends([]);
   }
   
   public function tags(): MorphToMany
   {
       return $this->morphToMany(\App\Models\Tag::class, 'object_tags')->withPivot('tag_id');
   }
}
