<?php
namespace Ledningssystemet\Ledningssystemet\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasMessages {
   
   public function messages(): MorphMany
   {
      return $this->morphMany(\Ledningssystemet\Ledningssystemet\Models\ObjectMessage::class, 'object');
   }
}
