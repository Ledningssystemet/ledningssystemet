<?php

namespace App\Models\Concerns;

use App\Models\ObjectTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasTags
{
    abstract public function morphMany($related, $name, $type = null, $id = null, $localKey = null);

    public function tags(): MorphMany
    {
        return $this->morphMany(ObjectTag::class, 'object_tags', 'object_tags_type', 'object_tags_id');
    }

    public function int_object_tags_as_object_tags(): MorphMany
    {
        return $this->tags();
    }
}

