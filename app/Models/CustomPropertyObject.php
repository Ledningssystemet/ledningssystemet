<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class CustomPropertyObject extends Model
{
   protected $table = 'custom_property_object';

   /**
    * Bootstrap any application services.
    *
    * @return void
    */
   public static function boot()
   {
      parent::boot();
   }


   /**
    * The attributes that shall be visible during serialization.
    */
   protected $visible = [
      'id',
      'custom_property_id',
      'object_id',
      'object_type',
      'value',
      'created_at',
      'updated_at',
   ];

   /**
    * The attributes that are mass assignable.
    *
    * @var array<int, string>
    */
   protected $fillable = [
   ];

   /**
    * The public actions available
    */
   public $actions = [
   ];

   /**
    * The attributes that should be cast.
    *
    * @var array<string, string>
    */
   protected $casts = [
   ];

   /**
    * Get the displayable name of the model.
    *
    * @param  bool  $plural
    * @return string
    */
   public static function getPrettyName($plural = false)
   {
      if ($plural) {
         return __("Custom property objects");
      } else {
         return __("Custom property object");
      }
   }

   /**
    * Retrieve status for the entire collection of objects
    *
    * @param  mixed  $department
    * @param  mixed  $user
    * @param  bool  $personalOnly
    * @return array
    */
   public static function getItemsStatus($department = null, $user = null, $personalOnly = false)
   {
      return [];
   }

   /**
    * Get the validation rules for the model.
    *
    * @return array
    */
   public function getValidationRules()
   {
      return [];
   }
}
