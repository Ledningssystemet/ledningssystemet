<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AccessGroup extends Model
{
    use HasFactory;

    protected $table = 'access_groups';

    protected $fillable = ['name', 'claims', 'risk_level_id', 'external_provider_group_id', 'user_ids'];

    protected $appends = ['user_ids'];

    /**
     * @var array<int, int>|null
     */
    private ?array $pendingUserIds = null;

    protected function casts(): array
    {
        return [
            'claims' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function validationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'claims' => ['nullable', 'array'],
            'risk_level_id' => ['nullable', 'integer', 'min:0', 'exists:risk_levels,id'],
            'external_provider_group_id' => ['nullable', 'integer', 'min:0', 'exists:external_provider_groups,id'],
            'user_ids' => ['nullable', 'array'],
        ];
    }

    public static function crudSearch(): array
    {
        return [
            'direct' => [
                'name',
                'claims',
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
            $model->syncUsersIfNeeded();
        });
    }

    public static function getPrettyName($plural = false): string
    {
        return $plural ? 'Access Groups' : 'Access Group';
    }

    public function int_external_provider_group(): BelongsTo
    {
        return $this->belongsTo(ExternalProviderGroup::class, 'external_provider_group_id');
    }

    public function int_risk_level(): BelongsTo
    {
        return $this->belongsTo(RiskLevel::class, 'risk_level_id');
    }

    public function int_access_group_user(): HasMany
    {
        return $this->hasMany(AccessGroupUser::class, 'access_group_id', 'id');
    }

    public function int_users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'access_group_user', 'access_group_id', 'user_id')
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

    public static function allClaims(): array
    {
        static $claims = null;

        if (is_array($claims)) {
            return $claims;
        }

        $catalog = [
            'superadmin.edit' => 'Superadmin - Edit',
            'systemadministrator.edit' => 'System Administrator - Edit',
        ];

        foreach (glob(app_path('Policies/*.php')) ?: [] as $policyPath) {
            $contents = @file_get_contents($policyPath);
            if (! is_string($contents) || $contents === '') {
                continue;
            }

            preg_match_all('/haveAnyAccessRights\(\s*\[(.*?)]\s*\)/s', $contents, $policyClaimBlocks);
            foreach ($policyClaimBlocks[1] ?? [] as $claimBlock) {
                preg_match_all("/'([^']+)'/", $claimBlock, $claimMatches);
                foreach ($claimMatches[1] ?? [] as $claim) {
                    $catalog[$claim] = static::claimLabel($claim);
                }
            }
        }

        asort($catalog, SORT_NATURAL | SORT_FLAG_CASE);

        $claims = $catalog;

        return $claims;
    }

    /**
     * @return array<int, int>
     */
    public function getUserIdsAttribute(): array
    {
        $users = $this->relationLoaded('int_users')
            ? $this->int_users
            : $this->int_users()->select('users.id')->get();

        return $users
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function setUserIdsAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->pendingUserIds = [];

            return;
        }

        if (! is_array($value)) {
            return;
        }

        $normalized = collect($value)
            ->filter(static fn (mixed $id): bool => $id !== null && $id !== '')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->pendingUserIds = $normalized;
    }

    private static function claimLabel(string $claim): string
    {
        [$resource, $ability] = array_pad(explode('.', $claim, 2), 2, '');

        if ($ability === '') {
            return Str::headline(str_replace(['-', '_'], ' ', $resource));
        }

        return Str::headline(str_replace(['-', '_'], ' ', $resource))
            .' - '
            .Str::headline(str_replace(['-', '_'], ' ', $ability));
    }

    private function syncUsersIfNeeded(): void
    {
        if ($this->pendingUserIds === null) {
            return;
        }

        $currentUserIds = $this->int_users()->pluck('users.id')->map(static fn (mixed $id): int => (int) $id)->all();
        $nextUserIds = $this->pendingUserIds;

        $this->int_users()->sync($nextUserIds);

        $touchedUserIds = array_values(array_unique(array_merge($currentUserIds, $nextUserIds)));
        if ($touchedUserIds !== []) {
            User::query()->whereIn('id', $touchedUserIds)->update(['updated_at' => now()]);
        }

        $this->pendingUserIds = null;
    }
}
