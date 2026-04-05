<?php

namespace App\Models\Concerns;

use App\Models\ObjectHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @mixin Model
 * @phpstan-require-extends Model
 * @method mixed morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null)
 */
trait HasHistory
{
    abstract public function morphMany($related, $name, $type = null, $id = null, $localKey = null);

    public function history(): MorphMany
    {
        return $this->morphMany(ObjectHistory::class, 'object', 'object_type', 'object_id');
    }

    public function int_object_histories_as_object(): MorphMany
    {
        return $this->history();
    }
}

