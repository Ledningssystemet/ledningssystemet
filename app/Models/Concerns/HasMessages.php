<?php

namespace App\Models\Concerns;

use App\Models\ObjectMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMessages
{
    abstract public function morphMany($related, $name, $type = null, $id = null, $localKey = null);

    public function messages(): MorphMany
    {
        return $this->morphMany(ObjectMessage::class, 'object', 'object_type', 'object_id');
    }

    public function int_object_messages_as_object(): MorphMany
    {
        return $this->messages();
    }
}

