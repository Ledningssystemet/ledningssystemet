<?php
namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMessages {
   
   public function messages(): MorphMany
   {
      return $this->morphMany(\App\Models\ObjectMessage::class, 'object');
   }
}
