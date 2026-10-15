<?php
namespace Ledningssystemet\Ledningssystemet\Traits;

use Ledningssystemet\Ledningssystemet\Models\Tag;
use Ledningssystemet\Ledningssystemet\Relations\NamespaceTolerantMorphToMany;
use Ledningssystemet\Ledningssystemet\Support\TagMorphType;

trait HasTags
{
    public static function allUsedTags()
    {
        return Tag::leftJoin('object_tags', 'object_tags.tag_id', '=', 'tags.id')
            ->whereIn('object_tags.object_tags_type', TagMorphType::forModel(static::class))
            ->select(['tags.*'])
            ->distinct()
            ->orderBy('tags.name')
            ->get()
            ->each
            ->setAppends([]);
    }

    public function tags(): NamespaceTolerantMorphToMany
    {
        $related = $this->newRelatedInstance(Tag::class);

        return (new NamespaceTolerantMorphToMany(
            $related->newQuery(),
            $this,
            'object_tags',
            'object_tags',
            'object_tags_id',
            'tag_id',
            $this->getKeyName(),
            $related->getKeyName(),
            'tags',
            false,
            TagMorphType::forModel($this),
        ))->withPivot('tag_id');
    }
}
