<?php

namespace App\Services;

use Illuminate\Support\Facades\Gate;

class MenuCategoryBuilder
{
    /**
     * Build menu categories for the current authenticated user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function build(): array
    {
        if (! auth()->check()) {
            return [];
        }

        $categories = [];

        $categories[] = [
            'label' => __('menu.categories.my_work'),
            'categoryIcon' => 'ClipboardList',
            'columns' => [
                [
                    'heading' => __('menu.headings.personal'),
                    'items' => [
                        [
                            'key' => 'my-profile',
                            'label' => __('menu.items.my_profile.label'),
                            'icon' => 'UserCircle',
                            'description' => __('menu.items.my_profile.description'),
                        ],
                        [
                            'key' => 'my-tasks',
                            'label' => __('menu.items.my_tasks.label'),
                            'icon' => 'ClipboardList',
                            'description' => __('menu.items.my_tasks.description'),
                        ],
                        [
                            'key' => 'my-documents',
                            'label' => __('menu.items.my_documents.label'),
                            'icon' => 'FolderOpen',
                            'description' => __('menu.items.my_documents.description'),
                        ],
                    ],
                ],
            ],
        ];

        return $categories;
    }

    private function allows(string $ability, string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        try {
            return Gate::allows($ability, $modelClass);
        } catch (\Throwable) {
            return false;
        }
    }
}

