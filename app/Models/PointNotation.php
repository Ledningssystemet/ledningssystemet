<?php

namespace Ledningssystemet\Ledningssystemet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Ledningssystemet\Ledningssystemet\Models\Concerns\DefersRelationAttributeSync;

class PointNotation extends Model
{
    use DefersRelationAttributeSync;

    protected $fillable = [
        'code',
        'name',
        'mappings',
    ];

    protected $visible = [
        'id',
        'code',
        'name',
        'mappings',
    ];

    protected $appends = [
        'mappings',
    ];

    public function getMappingsAttribute(): array
    {
        $processes = $this->relationLoaded('processes')
            ? $this->getRelation('processes')->pluck('id')
            : $this->processes()->pluck('processes.id');
        $activities = $this->relationLoaded('processActivities')
            ? $this->getRelation('processActivities')->pluck('id')
            : $this->processActivities()->pluck('process_activities.id');

        return $processes->map(fn ($id) => 'process:'.$id)
            ->merge($activities->map(fn ($id) => 'activity:'.$id))
            ->values()
            ->all();
    }

    public function setMappingsAttribute($value): void
    {
        $this->syncRelationAttribute('mappings', function (self $model) use ($value): void {
            $mappings = ($value === '' || $value === null) ? [] : $value;
            $ids = self::validateMappings($mappings);
            $model->processes()->sync($ids['processes']);
            $model->processActivities()->sync($ids['activities']);
        });
    }

    public function getValidationRules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[0-9]+(?:\.[0-9]+)*\z/',
                Rule::unique('point_notations')->ignore($this->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'mappings' => ['present', 'array'],
            'mappings.*' => ['required', 'string', 'regex:/\A(?:process|activity):[0-9]+\z/', 'distinct'],
        ];
    }

    private static function validateMappings(mixed $mappings): array
    {
        $validated = Validator::make(
            ['mappings' => $mappings],
            [
                'mappings' => ['present', 'array'],
                'mappings.*' => ['required', 'string', 'regex:/\A(?:process|activity):[0-9]+\z/', 'distinct'],
            ]
        )->validate();

        $processIds = [];
        $activityIds = [];
        foreach ($validated['mappings'] as $mapping) {
            [$type, $id] = explode(':', $mapping, 2);
            if ($type === 'process') {
                $processIds[] = (int) $id;
            } else {
                $activityIds[] = (int) $id;
            }
        }

        $processIds = array_values(array_unique($processIds));
        $activityIds = array_values(array_unique($activityIds));
        Validator::make(
            ['processes' => $processIds, 'activities' => $activityIds],
            [
                'processes' => ['array'],
                'processes.*' => ['integer', 'exists:processes,id'],
                'activities' => ['array'],
                'activities.*' => ['integer', 'exists:process_activities,id'],
            ]
        )->validate();

        return ['processes' => $processIds, 'activities' => $activityIds];
    }

    public static function compareCodes(string $left, string $right): int
    {
        $leftParts = explode('.', $left);
        $rightParts = explode('.', $right);
        $partCount = max(count($leftParts), count($rightParts));

        for ($index = 0; $index < $partCount; $index++) {
            if (! isset($leftParts[$index])) {
                return -1;
            }
            if (! isset($rightParts[$index])) {
                return 1;
            }

            $leftPart = ltrim($leftParts[$index], '0') ?: '0';
            $rightPart = ltrim($rightParts[$index], '0') ?: '0';
            $comparison = strlen($leftPart) <=> strlen($rightPart);
            if ($comparison === 0) {
                $comparison = strcmp($leftPart, $rightPart);
            }
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return strcmp($left, $right);
    }

    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'point_notation_process');
    }

    public function processActivities(): BelongsToMany
    {
        return $this->belongsToMany(ProcessActivity::class, 'point_notation_process_activity');
    }
}
