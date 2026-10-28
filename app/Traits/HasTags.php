<?php
namespace Ledningssystemet\Ledningssystemet\Traits;

use Ledningssystemet\Ledningssystemet\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTags
{
    public static function allUsedTags()
    {
        return Tag::leftJoin('object_tags', 'object_tags.tag_id', '=', 'tags.id')
            ->where('object_tags.object_tags_type', (new static)->getMorphClass())
            ->select(['tags.*'])
            ->distinct()
            ->orderBy('tags.name')
            ->get()
            ->each
            ->setAppends([]);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'object_tags', 'object_tags', 'object_tags_id', 'tag_id')
            ->withPivot('tag_id');
    }
}
