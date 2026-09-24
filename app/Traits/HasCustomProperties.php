<?php
namespace App\Traits;

use App\Models\CustomPropertyObject;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use App\Models\CustomProperty;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

trait HasCustomProperties {
   // Per-model instance cache for decoded custom property values.
   protected bool $customPropertyValueMapLoaded = false;
   protected array $customPropertyValueMap = [];

   // Per-request cache for custom property definitions per context class.
   protected static array $customPropertiesForContext = [];

   // Request-local shared cache/batch state per model class.
   protected static array $customPropertyPendingObjectIdsByClass = [];
   protected static array $customPropertyValueMapsByClass = [];

   public function int_custom_properties() : HasManyThrough
   {
      return $this->hasManyThrough(
         CustomProperty::class,
         CustomPropertyObject::class,
         'object_id', // Foreign key on custom_property_object table...
         'id', // Foreign key on custom_properties table...
         'id', // Local key on the model using the trait...
         'custom_property_id' // Local key on the custom_property_object table...
      )
      ->where('custom_properties.context', static::class);
   }
   public static function getCustomProperties(){
      if (!array_key_exists(static::class, self::$customPropertiesForContext)) {
         self::$customPropertiesForContext[static::class] = CustomProperty::where('context', static::class)->get();
      }

      return self::$customPropertiesForContext[static::class];
   }

   protected function loadCustomPropertyValueMap(): void
   {
      if ($this->customPropertyValueMapLoaded) {
         return;
      }

      if (null == $this->id) {
         $this->customPropertyValueMapLoaded = true;
         $this->customPropertyValueMap = [];
         return;
      }

      $class = static::class;
      if (!array_key_exists($class, self::$customPropertyValueMapsByClass)) {
         self::$customPropertyValueMapsByClass[$class] = [];
      }

      // If this object's map is already loaded in request memory, reuse it directly.
      if (array_key_exists($this->id, self::$customPropertyValueMapsByClass[$class])) {
         $this->customPropertyValueMapLoaded = true;
         $this->customPropertyValueMap = self::$customPropertyValueMapsByClass[$class][$this->id];
         return;
      }

      if (!array_key_exists($class, self::$customPropertyPendingObjectIdsByClass)) {
         self::$customPropertyPendingObjectIdsByClass[$class] = [];
      }

      // Ensure current object id is included in the next batch load.
      self::$customPropertyPendingObjectIdsByClass[$class][$this->id] = true;

      $this->loadPendingCustomPropertyValueMapsForClass($class);

      $this->customPropertyValueMapLoaded = true;
      $this->customPropertyValueMap = self::$customPropertyValueMapsByClass[$class][$this->id] ?? [];
   }

   protected function loadPendingCustomPropertyValueMapsForClass(string $class): void
   {
      $pending = self::$customPropertyPendingObjectIdsByClass[$class] ?? [];
      if (0 === count($pending)) {
         return;
      }

      $objectIds = array_map('intval', array_keys($pending));
      self::$customPropertyPendingObjectIdsByClass[$class] = [];

      if (!array_key_exists($class, self::$customPropertyValueMapsByClass)) {
         self::$customPropertyValueMapsByClass[$class] = [];
      }

      // Query only ids we have not loaded yet.
      $missingObjectIds = [];
      foreach ($objectIds as $objectId) {
         if (!array_key_exists($objectId, self::$customPropertyValueMapsByClass[$class])) {
            self::$customPropertyValueMapsByClass[$class][$objectId] = [];
            $missingObjectIds[] = $objectId;
         }
      }

      if (0 === count($missingObjectIds)) {
         return;
      }

      $rows = DB::table('custom_property_object')
         ->where('object_type', $class)
         ->whereIn('object_id', $missingObjectIds)
         ->select(['object_id', 'custom_property_id', 'value'])
         ->get();

      foreach ($rows as $row) {
         $objectId = intval($row->object_id);
         $propertyId = intval($row->custom_property_id);
         self::$customPropertyValueMapsByClass[$class][$objectId][$propertyId] =
            (null == $row->value) ? null : json_decode($row->value);
      }
   }

   protected function getCustomPropertyValueById(int $propertyId)
   {
      $this->loadCustomPropertyValueMap();

      return array_key_exists($propertyId, $this->customPropertyValueMap)
         ? $this->customPropertyValueMap[$propertyId]
         : null;
   }

   /**
    * Bootstrap any application services.
    *
    * @return void
    */
   public static function bootHasCustomProperties()
   {
      static::saving(function ($model) {
         $props = [];
         $validators = [];
         $noneset = true;
         // Fetch all custom properties
         foreach ((static::class)::getCustomProperties() as $prop) {
            if(request()->has('customproperty_' . $prop->id))
               $noneset = false;

            $props['customproperty_'.$prop->id] = request()->input('customproperty_' . $prop->id, null);
            $propertyvalidator = '';

            switch($prop->type)
            {
               case 'string':
                  $propertyvalidator .= 'string|max:255';
                  $propertyvalidator .= $prop->required ? '|required' : '|nullable';
                  break;
               case 'textarea':
                  $propertyvalidator .= $prop->required ? 'required' : 'nullable';
                  break;
               case 'boolean':
                  $propertyvalidator .= 'boolean';
                  $propertyvalidator .= $prop->required ? '|required' : '|nullable';
                  break;
               case 'user':
                  $propertyvalidator .= 'exists:users,id';
                  $propertyvalidator .= $prop->required ? '|required' : '|nullable';
                  break;
               case 'department':
                  $propertyvalidator .= 'exists:departments,id';
                  $propertyvalidator .= $prop->required ? '|required' : '|nullable';
                  break;
               case 'supplier':
                  $propertyvalidator .= 'exists:suppliers,id';
                  $propertyvalidator .= $prop->required ? '|required' : '|nullable';
                  break;
               case 'customer':
                  $propertyvalidator .= 'exists:customers,id';
                  $propertyvalidator .= $prop->required ? '|required' : '|nullable';
                  break;
               case 'asset':
                  $propertyvalidator .= 'exists:assets,id';
                  $propertyvalidator .= $prop->required ? '|required' : '|nullable';
                  break;
               case 'process':
                  $propertyvalidator .= 'exists:processes,id';
                  $propertyvalidator .= $prop->required ? '|required' : '|nullable';
                  break;

               default:
                  throw new \Exception('The custom property of type '.$prop->type.' does not have a corresponding jtable type');
            }

            $validators['customproperty_' . $prop->id] = $propertyvalidator;
         }

         if(!$noneset) {
            // Perform validation
            Validator::make($props, $validators)->validate();

            // Save all custom properties for this object
            foreach (static::getCustomProperties() as $prop) DB::table('custom_property_object')->updateOrInsert(
               [
                  'object_id' => $model->id,
                  'object_type' => $model::class,
                  'custom_property_id' => $prop->id,
               ],
               [
                  'value' => json_encode($props['customproperty_' . $prop->id]),
               ]
            );

            // Ensure fresh values after write in the same request lifecycle.
            $model->customPropertyValueMapLoaded = false;
            $model->customPropertyValueMap = [];
            unset(self::$customPropertyValueMapsByClass[$model::class][$model->id]);
         }
      });

      static::deleted(function ($model) {
         // Delete all custom properties for this object
         DB::table('custom_property_object')->where('object_id', $model->id)->where('object_type', $model::class)->delete();

         $model->customPropertyValueMapLoaded = false;
         $model->customPropertyValueMap = [];
         unset(self::$customPropertyValueMapsByClass[$model::class][$model->id]);
       });

      static::retrieved(function ($model) {
         $modelProperties = Cache::rememberForever($model::class.'.custom_properties', function () use ($model) {
            return DB::table('custom_properties')->where('context', $model::class)->pluck('id');
         });

         foreach($modelProperties as $prop)
         {
            $varname = 'customproperty_'.$prop;
            $model->makeVisible($varname);
            $model->append($varname);
         }

         if (null != $model->id) {
            if (!array_key_exists($model::class, self::$customPropertyPendingObjectIdsByClass)) {
               self::$customPropertyPendingObjectIdsByClass[$model::class] = [];
            }
            self::$customPropertyPendingObjectIdsByClass[$model::class][$model->id] = true;
         }
      });
   }

   public function __call($method, $parameters){
      if(str_starts_with($method, 'getCustomproperty')) {
         // Extract the property id from the method name using regex
         preg_match('/getCustomproperty(\d+)/', $method, $matches);
         $propertyId = intval($matches[1]);

         return $this->getCustomPropertyValueById($propertyId);
      }
      return parent::__call($method, $parameters);
   }

   public static function getIndexQuery(Builder &$query) {
      $customProperties = static::getCustomProperties();
      if(count($customProperties) > 0) {
         foreach ($customProperties as $prop) {
            switch ($prop->type) {
               case 'string':
               case 'textarea':
                  break;

               default:
                  $query->when(0 < intval(request()->input('customproperty_' . $prop->id, -1)), function (Builder $query) use ($prop) {
                     $query->whereHas('int_custom_properties', function (Builder $query) use ($prop) {
                        $query
                           ->where('custom_property_object.value', json_encode(request()->input('customproperty_' . $prop->id, null)))
                           ->where('custom_properties.id', $prop->id);
                     });
                  });
                  $query->when(0 === intval(request()->input('customproperty_' . $prop->id, -1)), function (Builder $query) use ($prop) {
                     $query->whereHas('int_custom_properties', function (Builder $query) use ($prop) {
                        $query
                           ->whereNull('value')
                           ->where('custom_properties.id', $prop->id);
                     });
                  });
                  break;
            }
         }
      }
      return $query;
   }
}
