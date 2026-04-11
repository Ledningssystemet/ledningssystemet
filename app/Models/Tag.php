<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class Tag extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    private static array $objectTypeLabelCache = [];

    protected $table = 'tags';

    protected $fillable = ['name'];

    protected $appends = ['usageinformation'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:25'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
            ],
            'relations' => [
                // 'relation.path' => ['name'],
            ],
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            Validator::make($model->attributesToArray(), static::validationRules())->validate();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Tags' : 'Tag';
    }

    public function getUsageinformationAttribute(): string
    {
        $rows = DB::table('object_tags')
            ->select('object_tags_type', DB::raw('COUNT(*) as total'))
            ->where('tag_id', $this->getKey())
            ->groupBy('object_tags_type')
            ->orderByDesc('total')
            ->get();

        if ($rows->isEmpty()) {
            return '';
        }

        $parts = $rows->map(function (object $row): string {
            $type = (string) ($row->object_tags_type ?? '');
            $total = (int) ($row->total ?? 0);
            $label = $this->prettyTypeLabel($type);

            return sprintf('%d %s', $total, $label);
        });

        return $parts->implode(', ');
    }

    private function prettyTypeLabel(string $objectType): string
    {
        $normalized = ltrim(trim($objectType), '\\');

        if ($normalized === '') {
            return 'objects';
        }

        if (isset(self::$objectTypeLabelCache[$normalized])) {
            return self::$objectTypeLabelCache[$normalized];
        }

        $label = Str::headline(Str::plural(class_basename($normalized)));

        if (class_exists($normalized) && method_exists($normalized, 'getPrettyName')) {
            try {
                $pretty = $normalized::getPrettyName(true);
                if (is_string($pretty) && trim($pretty) !== '') {
                    $label = $pretty;
                }
            } catch (\Throwable) {
                // Fall back to class basename formatting.
            }
        }

        self::$objectTypeLabelCache[$normalized] = $label;

        return $label;
    }

    public function int_object_tags(): HasMany
    {
        return $this->hasMany(ObjectTag::class, 'tag_id', 'id');
    }

    public function int_custom_property_object_as_object(): MorphMany
    {
        return $this->morphMany(CustomPropertyObject::class, 'object', 'object_type', 'object_id');
    }

    public function int_files_as_object(): MorphMany
    {
        return $this->morphMany(File::class, 'object', 'object_type', 'object_id');
    }

    public function int_findings_as_context(): MorphMany
    {
        return $this->morphMany(Finding::class, 'context', 'context_type', 'context_id');
    }

    public function int_object_histories_as_object(): MorphMany
    {
        return $this->morphMany(ObjectHistory::class, 'object', 'object_type', 'object_id');
    }

    public function int_object_messages_as_object(): MorphMany
    {
        return $this->morphMany(ObjectMessage::class, 'object', 'object_type', 'object_id');
    }

    public function int_object_properties_as_object_properties(): MorphMany
    {
        return $this->morphMany(ObjectProperty::class, 'object_properties', 'object_properties_type', 'object_properties_id');
    }

    public function int_object_tags_as_object_tags(): MorphMany
    {
        return $this->morphMany(ObjectTag::class, 'object_tags', 'object_tags_type', 'object_tags_id');
    }

    public function int_personal_access_tokens_as_tokenable(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable', 'tokenable_type', 'tokenable_id');
    }

    public function int_risks_as_context(): MorphMany
    {
        return $this->morphMany(Risk::class, 'context', 'context_type', 'context_id');
    }

    public function int_vector_embeddings_as_embeddable(): MorphMany
    {
        return $this->morphMany(VectorEmbedding::class, 'embeddable', 'embeddable_type', 'embeddable_id');
    }
}
