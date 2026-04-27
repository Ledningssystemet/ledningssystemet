<?php

namespace App\Services;

use App\Plugins\PluginRuntime;
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
            'label' => __('My work'),
            'categoryIcon' => 'how_to_reg',
            'columns' => [
                [
                    'heading' => __('My responsibility'),
                    'items' => [
                        [
                            'key' => 'my-risks',
                            'label' => __('My risks'),
                            'icon' => 'warning',
                            'description' => __('Risks that you are responsible for managing'),
                        ],
                        [
                            'key' => 'my-tasks',
                            'label' => __('My tasks'),
                            'icon' => 'checklist',
                            'description' => __('Activities and control actions assigned to you'),
                        ],
                        [
                            'key' => 'my-documents',
                            'label' => __('My documents'),
                            'icon' => 'folder_open',
                            'description' => __('Documents where you are responsible or publisher'),
                        ],
                    ],
                ],
                [
                    'heading' => __('Process support'),
                    'items' => [
                        [
                            'key' => 'chemical-register',
                            'label' => __('Chemical register'),
                            'icon' => 'science',
                            'description' => __('An overview of the chemicals we use and how we handle them safely'),
                        ],
                        [
                            'key' => 'documents',
                            'label' => __('Documents'),
                            'icon' => 'description',
                            'description' => __('The documents we use to support our processes, including policies, instructions, and records'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('Inventory'),
            'categoryIcon' => 'layers',
            'columns' => [
                [
                    'heading' => __('Way of working'),
                    'items' => [
                        [
                            'key' => 'requirement-sources',
                            'label' => __('Requirements'),
                            'icon' => 'balance',
                            'description' => __('Laws, regulations, and other sources of requirements we must comply with'),
                        ],
                        [
                            'key' => 'controls',
                            'label' => __('Control register'),
                            'icon' => 'check_circle',
                            'description' => __('Our ways of controlling and monitoring our work, including the activities we perform and the results we achieve'),
                        ],
                        [
                            'key' => 'processes',
                            'label' => __('Processes'),
                            'icon' => 'account_tree',
                            'description' => __('The processes we follow to get our work done'),
                        ],
                        [
                            'key' => 'information-types',
                            'label' => __('Information types'),
                            'icon' => 'layers',
                            'description' => __('The types of information we handle in our processes'),
                        ],
                        [
                            'key' => 'assets',
                            'label' => __('Assets'),
                            'icon' => 'database',
                            'description' => __('The assets we use to support our processes and handle information, including IT systems and physical assets'),
                        ],
                        [
                            'key' => 'sustainability-aspects',
                            'label' => __('Sustainability aspects'),
                            'icon' => 'eco',
                            'description' => __('Our impact on the environment and the ways we can reduce our impact'),
                        ],
                    ],
                ],
                [
                    'heading' => __('Relations'),
                    'items' => [
                        [
                            'key' => 'customers',
                            'label' => __('Customers'),
                            'icon' => 'group',
                            'description' => __('The customers we serve'),
                        ],
                        [
                            'key' => 'suppliers',
                            'label' => __('Suppliers'),
                            'icon' => 'local_shipping',
                            'description' => __('The suppliers we work with'),
                        ],
                        [
                            'key' => 'agreements',
                            'label' => __('Agreements'),
                            'icon' => 'signature',
                            'description' => __('An overview of our agreements with suppliers and customers'),
                        ],
                    ],
                ],
                [
                    'heading' => __('Compliance'),
                    'items' => [
                        [
                            'key' => 'gdpr-register',
                            'label' => __('Record of processing activities'),
                            'icon' => 'shield',
                            'description' => __('Our record of processing activities for compliance with personal data legislation'),
                        ],
                        [
                            'key' => 'information-handling-plan',
                            'label' => __('Information handling plan'),
                            'icon' => 'description',
                            'description' => __('An overview of our information handling, including diary and archiving'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('Continuous improvement'),
            'categoryIcon' => 'trending_up',
            'columns' => [
                [
                    'heading' => __('Planning and evaluation'),
                    'items' => [
                        [
                            'key' => 'company-dashboard',
                            'label' => __('Company dashboard'),
                            'icon' => 'bar_chart',
                            'description' => __('Provides an overview of our continuous improvement'),
                        ],
                        [
                            'key' => 'process-performance',
                            'label' => __('Process performance'),
                            'icon' => 'trending_up',
                            'description' => __('Our performance indicators to evaluate how well our processes are performing'),
                        ],
                        [
                            'key' => 'objectives',
                            'label' => __('Company objectives'),
                            'icon' => 'target',
                            'description' => __('The objectives we have set for our company and the ways we plan to achieve them'),
                        ],
                        [
                            'key' => 'compliance-evaluation',
                            'label' => __('Compliance evaluation'),
                            'icon' => 'manage_search',
                            'description' => __('Internal audits and other evaluations of compliance'),
                        ],
                    ],
                ],
                [
                    'heading' => __('Assess and mitigate'),
                    'items' => [
                        [
                            'key' => 'risks',
                            'label' => __('Risk register'),
                            'icon' => 'warning',
                            'description' => __('The risks we need to manage and the ways we can reduce them'),
                        ],
                        [
                            'key' => 'projects',
                            'label' => __('Projects'),
                            'icon' => 'work',
                            'description' => __('The projects we are running'),
                        ],
                        [
                            'key' => 'observations',
                            'label' => __('Observations'),
                            'icon' => 'manage_search',
                            'description' => __('Non-conformities and other observations we have made in our processes'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('Act to improve'),
            'categoryIcon' => 'check_circle',
            'columns' => [
                [
                    'heading' => __('Tasks'),
                    'items' => [
                        [
                            'key' => 'incidents',
                            'label' => __('Incidents'),
                            'icon' => 'warning',
                            'description' => __('Incidents and other events that we need to investigate and learn from'),
                        ],
                        [
                            'key' => 'control-actions',
                            'label' => __('Control actions'),
                            'icon' => 'settings',
                            'description' => __('Improvements to our processes and controls that we have implemented'),
                        ],
                        [
                            'key' => 'activities',
                            'label' => __('Activities'),
                            'icon' => 'checklist',
                            'description' => __('Activities, recurring or one-time, that we have planned'),
                        ],
                    ],
                ],
                [
                    'heading' => __('Coordination'),
                    'items' => [
                        [
                            'key' => 'activity-flows',
                            'label' => __('Activity flows'),
                            'icon' => 'account_tree',
                            'description' => __('Chain of activities that we perform in specific occasions'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('Staff'),
            'categoryIcon' => 'group',
            'columns' => [
                [
                    'heading' => __('Employees and roles'),
                    'items' => [
                        [
                            'key' => 'employees',
                            'label' => __('Employees'),
                            'icon' => 'group',
                            'description' => __('Our employees and their roles and responsibilities'),
                        ],
                        [
                            'key' => 'roles',
                            'label' => __('Roles'),
                            'icon' => 'shield',
                            'description' => __('The roles we have defined in our organization and their responsibilities'),
                        ],
                        [
                            'key' => 'qualifications',
                            'label' => __('Qualifications'),
                            'icon' => 'school',
                            'description' => __('Training and qualifications we need to maintain'),
                        ],
                        [
                            'key' => 'compentences',
                            'label' => __('Competences'),
                            'icon' => 'psychology',
                            'description' => __('Competences we want to evaluate and that are necessary for our work'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('System administration'),
            'categoryIcon' => 'settings',
            'columns' => [
                [
                    'heading' => __('Management system settings'),
                    'items' => [
                        [
                            'key' => 'assessment-settings',
                            'label' => __('Assessment settings'),
                            'icon' => 'manage_search',
                            'description' => __('Settings for classification, risk assessment and more'),
                        ],
                        [
                            'key' => 'supplier-categories',
                            'label' => __('Supplier categories'),
                            'icon' => 'local_shipping',
                            'description' => __('The framework for supplier evaluation'),
                        ],
                        [
                            'key' => 'activity-flow-templates',
                            'label' => __('Activity flow templates'),
                            'icon' => 'account_tree',
                            'description' => __('Templates for activity flows to coordinate our work'),
                        ],
                        [
                            'key' => 'Project types',
                            'label' => __('Project types'),
                            'icon' => 'work',
                            'description' => __('The types of projects we have defined, and also risks that are to be evaluated for them'),
                        ],
                    ],
                ],
                [
                    'heading' => __('Organization'),
                    'items' => [
                        [
                            'key' => 'users',
                            'label' => __('Users'),
                            'icon' => 'group',
                            'description' => __('Users with access to the system'),
                        ],
                        [
                            'key' => 'sites',
                            'label' => __('Sites'),
                            'icon' => 'apartment',
                            'description' => __('Locations where we work'),
                        ],
                        [
                            'key' => 'departments',
                            'label' => __('Departments'),
                            'icon' => 'layers',
                            'description' => __('Departments within our organization'),
                        ],
                        [
                            'key' => 'user-notification-settings',
                            'label' => __('User notification settings'),
                            'icon' => 'notifications',
                            'description' => __('Settings for how users receive notifications from the system'),
                        ],
                    ],
                ],
                [
                    'heading' => __('System configuration'),
                    'items' => [
                        [
                            'key' => 'access-groups',
                            'label' => __('Access groups'),
                            'icon' => 'key',
                            'description' => __('User access groups for managing permissions'),
                        ],
                        [
                            'key' => 'custom-properties',
                            'label' => __('Custom properties'),
                            'icon' => 'settings',
                            'description' => __('Company-defined properties for customizing the system'),
                        ],
                        [
                            'key' => 'api-tokens',
                            'label' => __('API tokens'),
                            'icon' => 'key',
                            'description' => __('API tokens for external integrations'),
                        ],
                        [
                            'key' => 'tags',
                            'label' => __('Tag collection'),
                            'icon' => 'sell',
                            'description' => __('Tags being used throughout the system'),
                        ],
                    ],
                ],
            ],
        ];

        return app(PluginRuntime::class)->extendMenu($categories, request());
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

