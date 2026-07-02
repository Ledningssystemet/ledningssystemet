<?php

namespace App\Plugins;

class PluginHookRegistry
{
    /**
     * @var array<string, array<int, array{priority: int, callback: callable}>>
     */
    private array $filters = [];

    /**
     * @var array<string, array<int, array{priority: int, callback: callable}>>
     */
    private array $actions = [];

    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][] = [
            'priority' => $priority,
            'callback' => $callback,
        ];
    }

    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][] = [
            'priority' => $priority,
            'callback' => $callback,
        ];
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->sortedCallbacks($this->filters[$hook] ?? []) as $listener) {
            $value = $listener($value, ...$args);
        }

        return $value;
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        foreach ($this->sortedCallbacks($this->actions[$hook] ?? []) as $listener) {
            $listener(...$args);
        }
    }

    /**
     * @param  array<int, array{priority: int, callback: callable}>  $listeners
     * @return array<int, callable>
     */
    private function sortedCallbacks(array $listeners): array
    {
        usort($listeners, static function (array $left, array $right): int {
            return $left['priority'] <=> $right['priority'];
        });

        return array_map(
            static fn (array $listener): callable => $listener['callback'],
            $listeners,
        );
    }
}

