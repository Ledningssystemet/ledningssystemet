<?php

namespace App\Models\Concerns;

trait HasStatus
{
    /**
     * Ensure status is always appended during serialization.
     */
    protected function initializeHasStatus(): void
    {
        $this->appends = array_values(array_unique(array_merge($this->appends, ['status'])));
    }

    /**
     * @return array{level: string, explanation: string}
     */
    public function getStatusAttribute(): array
    {
        if (! $this->exists) {
            return $this->defaultStatus();
        }

        return $this->normalizeStatus($this->resolveStatus());
    }

    /**
     * Placeholder for model-specific status logic.
     *
     * @return array{level?: mixed, explanation?: mixed}
     */
    protected function resolveStatus(): array
    {
        return $this->defaultStatus();
    }

    /**
     * @return array{level: string, explanation: string}
     */
    protected function defaultStatus(string $level = 'unknown', string $explanation = ''): array
    {
        return $this->normalizeStatus([
            'level' => $level,
            'explanation' => $explanation,
        ]);
    }

    /**
     * @param array{level?: mixed, explanation?: mixed} $status
     * @return array{level: string, explanation: string}
     */
    protected function normalizeStatus(array $status): array
    {
        $allowedLevels = ['unknown', 'success', 'warning', 'danger'];
        $level = (string) ($status['level'] ?? 'unknown');

        if (! in_array($level, $allowedLevels, true)) {
            $level = 'unknown';
        }

        return [
            'level' => $level,
            'explanation' => (string) ($status['explanation'] ?? ''),
        ];
    }
}
