<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class Chemical extends Model
{
    use HasFactory;

    protected $table = 'chemicals';

    protected $fillable = ['name', 'manufacturer', 'description', 'usagedescription', 'storagedescription', 'consumptiondescription', 'riskdescription', 'handlingguidance', 'ohs_danger_properties', 'danger', 'sdbfile', 'sdbfilename', 'sdbcontenttype', 'sdbcontentlength', 'sdbfilecontent'];

    protected $appends = ['danger'];

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
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'usagedescription' => ['nullable', 'string'],
            'storagedescription' => ['nullable', 'string'],
            'consumptiondescription' => ['nullable', 'string'],
            'riskdescription' => ['nullable', 'string'],
            'handlingguidance' => ['nullable', 'string'],
            'ohs_danger_properties' => ['nullable', 'integer', 'min:0'],
            'danger' => ['nullable', 'array'],
            'sdbfile' => ['nullable', 'file', 'mimetypes:application/pdf', 'max:10240'],
            'sdbfilename' => ['nullable', 'string', 'max:255'],
            'sdbcontenttype' => ['nullable', 'string', 'max:255'],
            'sdbcontentlength' => ['nullable', 'integer', 'min:0'],
            'sdbfilecontent' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, array{bit:int, code:string}>
     */
    public static function dangerProperties(): array
    {
        return [
            'ghs01' => ['bit' => 1, 'code' => 'GHS01'],
            'ghs02' => ['bit' => 2, 'code' => 'GHS02'],
            'ghs03' => ['bit' => 4, 'code' => 'GHS03'],
            'ghs04' => ['bit' => 8, 'code' => 'GHS04'],
            'ghs05' => ['bit' => 16, 'code' => 'GHS05'],
            'ghs06' => ['bit' => 32, 'code' => 'GHS06'],
            'ghs07' => ['bit' => 64, 'code' => 'GHS07'],
            'ghs08' => ['bit' => 128, 'code' => 'GHS08'],
            'ghs09' => ['bit' => 256, 'code' => 'GHS09'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function getDangerAttribute(): array
    {
        $mask = (int) ($this->attributes['ohs_danger_properties'] ?? 0);
        $selected = [];

        foreach (static::dangerProperties() as $key => $meta) {
            if (($mask & (int) $meta['bit']) !== 0) {
                $selected[] = $key;
            }
        }

        return $selected;
    }

    public function setDangerAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['ohs_danger_properties'] = 0;

            return;
        }

        $values = is_array($value) ? $value : [$value];
        $meta = static::dangerProperties();
        $mask = 0;

        foreach ($values as $dangerKey) {
            $key = (string) $dangerKey;

            if (! array_key_exists($key, $meta)) {
                continue;
            }

            $mask |= (int) $meta[$key]['bit'];
        }

        $this->attributes['ohs_danger_properties'] = $mask;
    }

    public function setSdbfileAttribute(mixed $value): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $this->attributes['sdbfilename'] = (string) $value->getClientOriginalName();
        $this->attributes['sdbcontenttype'] = (string) ($value->getClientMimeType() ?: 'application/pdf');
        $this->attributes['sdbcontentlength'] = (int) $value->getSize();
        $content = $value->get();
        if ($content === false && $value->getRealPath() !== false) {
            $content = file_get_contents($value->getRealPath());
        }

        $this->attributes['sdbfilecontent'] = $content === false ? null : $content;
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'manufacturer',
                'description',
                'usagedescription',
                'storagedescription',
                'consumptiondescription',
                'riskdescription',
                'handlingguidance',
                'sdbfilename',
                'sdbcontenttype',
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
        return $plural ? 'Chemicals' : 'Chemical';
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
