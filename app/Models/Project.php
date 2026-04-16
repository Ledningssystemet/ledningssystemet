<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Spatie\QueryBuilder\QueryBuilder;

class Project extends Model
{
    use HasFactory;

    protected $table = 'projects';

    protected $fillable = ['name', 'scopedescription', 'purposedescription', 'responsible_user_id', 'department_id', 'start_date', 'end_date', 'archived_at', 'project_type_id', 'users'];

    protected $appends = ['users'];

    /**
     * @var array<int, int>
     */
    private array $pendingUserIds = [];

    private bool $hasPendingUsersUpdate = false;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'archived_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'scopedescription' => ['nullable', 'string'],
            'purposedescription' => ['nullable', 'string'],
            'responsible_user_id' => ['required', 'integer', 'min:0', 'exists:users,id'],
            'department_id' => ['required', 'integer', 'min:0', 'exists:departments,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'archived_at' => ['nullable', 'date'],
            'project_type_id' => ['nullable', 'integer', 'min:0', 'exists:project_types,id'],
            'users' => ['sometimes', 'array'],
            'users.*' => ['integer', 'min:1', 'exists:users,id'],
        ];
    }

    public static function crudAppends(): array
    {
        return ['users'];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'scopedescription',
                'purposedescription',
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

        static::saved(function (self $model): void {
            $model->syncPendingUsers();
        });
    }

    public static function applyCrudIndexFilters(Builder|QueryBuilder $query, Request $request): void
    {
        if (! $request->boolean('show_archived')) {
            $query->whereNull('archived_at');
        }

        if ($request->boolean('show_my_only') && $request->user()) {
            $query->where('responsible_user_id', $request->user()->id);
        }
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Projects' : 'Project';
    }

    /**
     * @return array<int, int>
     */
    public function getUsersAttribute(): array
    {
        return $this->int_users()->pluck('users.id')->map(static fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @param array<int, int|string>|int|string|null $value
     */
    public function setUsersAttribute(array|int|string|null $value): void
    {
        $rawUserIds = is_array($value)
            ? $value
            : ($value === null || $value === '' ? [] : [$value]);

        $this->pendingUserIds = collect($rawUserIds)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->hasPendingUsersUpdate = true;
    }

    private function syncPendingUsers(): void
    {
        if (! $this->hasPendingUsersUpdate) {
            return;
        }

        $this->int_users()->sync($this->pendingUserIds);

        $this->pendingUserIds = [];
        $this->hasPendingUsersUpdate = false;
    }

    public function int_department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function int_responsible_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function int_project_type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function int_project_user(): HasMany
    {
        return $this->hasMany(ProjectUser::class, 'project_id', 'id');
    }

    public function int_risks(): HasMany
    {
        return $this->hasMany(Risk::class, 'project_id', 'id');
    }

    public function int_users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id')
            ->withTimestamps();
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
