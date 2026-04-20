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
            'categoryIcon' => 'UserRoundCheck',
            'columns' => [
                [
                    'heading' => __('My responsibility'),
                    'items' => [
                        [
                            'key' => 'my-profile',
                            'label' => __('My profile'),
                            'icon' => 'UserCircle',
                            'description' => __('Your profile and personal settings'),
                        ],
                        [
                            'key' => 'my-risks',
                            'label' => __('My risks'),
                            'icon' => 'AlertTriangle',
                            'description' => __('Risks that you are responsible for managing'),
                        ],
                        [
                            'key' => 'my-tasks',
                            'label' => __('My tasks'),
                            'icon' => 'ClipboardList',
                            'description' => __('Activities and control actions assigned to you'),
                        ],
                        [
                            'key' => 'my-documents',
                            'label' => __('My documents'),
                            'icon' => 'FolderOpen',
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
                            'icon' => 'FlaskConical',
                            'description' => __('An overview of the chemicals we use and how we handle them safely'),
                        ],
                        [
                            'key' => 'documents',
                            'label' => __('Documents'),
                            'icon' => 'FileText',
                            'description' => __('The documents we use to support our processes, including policies, instructions, and records'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('Inventory'),
            'categoryIcon' => 'Layers',
            'columns' => [
                [
                    'heading' => __('Way of working'),
                    'items' => [
                        [
                            'key' => 'requirement-sources',
                            'label' => __('Requirements'),
                            'icon' => 'Scale',
                            'description' => __('Laws, regulations, and other sources of requirements we must comply with'),
                        ],
                        [
                            'key' => 'controls',
                            'label' => __('Control register'),
                            'icon' => 'CheckCircle2',
                            'description' => __('Our ways of controlling and monitoring our work, including the activities we perform and the results we achieve'),
                        ],
                        [
                            'key' => 'processes',
                            'label' => __('Processes'),
                            'icon' => 'GitBranch',
                            'description' => __('The processes we follow to get our work done'),
                        ],
                        [
                            'key' => 'information-types',
                            'label' => __('Information types'),
                            'icon' => 'Layers',
                            'description' => __('The types of information we handle in our processes'),
                        ],
                        [
                            'key' => 'assets',
                            'label' => __('Assets'),
                            'icon' => 'Database',
                            'description' => __('The assets we use to support our processes and handle information, including IT systems and physical assets'),
                        ],
                        [
                            'key' => 'sustainability-aspects',
                            'label' => __('Sustainability aspects'),
                            'icon' => 'Leaf',
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
                            'icon' => 'Users',
                            'description' => __('The customers we serve'),
                        ],
                        [
                            'key' => 'suppliers',
                            'label' => __('Suppliers'),
                            'icon' => 'Truck',
                            'description' => __('The suppliers we work with'),
                        ],
                        [
                            'key' => 'agreements',
                            'label' => __('Agreements'),
                            'icon' => 'FileSignature',
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
                            'icon' => 'Shield',
                            'description' => __('Our record of processing activities for compliance with personal data legislation'),
                        ],
                        [
                            'key' => 'information-handling-plan',
                            'label' => __('Information handling plan'),
                            'icon' => 'FileText',
                            'description' => __('An overview of our information handling, including diary and archiving'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('Continuous improvement'),
            'categoryIcon' => 'TrendingUp',
            'columns' => [
                [
                    'heading' => __('Planning and evaluation'),
                    'items' => [
                        [
                            'key' => 'company-dashboard',
                            'label' => __('Company dashboard'),
                            'icon' => 'ChartNoAxesCombined',
                            'description' => __('Provides an overview of our continuous improvement'),
                        ],
                        [
                            'key' => 'process-performance',
                            'label' => __('Process performance'),
                            'icon' => 'TrendingUp',
                            'description' => __('Our performance indicators to evaluate how well our processes are performing'),
                        ],
                        [
                            'key' => 'objectives',
                            'label' => __('Company objectives'),
                            'icon' => 'Target',
                            'description' => __('The objectives we have set for our company and the ways we plan to achieve them'),
                        ],
                        [
                            'key' => 'compliance-evaluation',
                            'label' => __('Compliance evaluation'),
                            'icon' => 'ScanSearch',
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
                            'icon' => 'AlertTriangle',
                            'description' => __('The risks we need to manage and the ways we can reduce them'),
                        ],
                        [
                            'key' => 'projects',
                            'label' => __('Projects'),
                            'icon' => 'Briefcase',
                            'description' => __('The projects we are running'),
                        ],
                        [
                            'key' => 'observations',
                            'label' => __('Observations'),
                            'icon' => 'ScanSearch',
                            'description' => __('Non-conformities and other observations we have made in our processes'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('Act to improve'),
            'categoryIcon' => 'CheckCircle2',
            'columns' => [
                [
                    'heading' => __('Tasks'),
                    'items' => [
                        [
                            'key' => 'incidents',
                            'label' => __('Incidents'),
                            'icon' => 'AlertTriangle',
                            'description' => __('Incidents and other events that we need to investigate and learn from'),
                        ],
                        [
                            'key' => 'control-actions',
                            'label' => __('Control actions'),
                            'icon' => 'Settings',
                            'description' => __('Improvements to our processes and controls that we have implemented'),
                        ],
                        [
                            'key' => 'activities',
                            'label' => __('Activities'),
                            'icon' => 'ClipboardList',
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
                            'icon' => 'GitBranch',
                            'description' => __('Chain of activities that we perform in specific occasions'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('Staff'),
            'categoryIcon' => 'Users',
            'columns' => [
                [
                    'heading' => __('Employees and roles'),
                    'items' => [
                        [
                            'key' => 'employees',
                            'label' => __('Employees'),
                            'icon' => 'Users',
                            'description' => __('Our employees and their roles and responsibilities'),
                        ],
                        [
                            'key' => 'roles',
                            'label' => __('Roles'),
                            'icon' => 'Shield',
                            'description' => __('The roles we have defined in our organization and their responsibilities'),
                        ],
                        [
                            'key' => 'qualifications',
                            'label' => __('Qualifications'),
                            'icon' => 'GraduationCap',
                            'description' => __('Training and qualifications we need to maintain'),
                        ],
                        [
                            'key' => 'compentences',
                            'label' => __('Competences'),
                            'icon' => 'Brain',
                            'description' => __('Competences we want to evaluate and that are necessary for our work'),
                        ],
                    ],
                ],
            ],
        ];

        $categories[] = [
            'label' => __('System administration'),
            'categoryIcon' => 'Settings',
            'columns' => [
                [
                    'heading' => __('Management system settings'),
                    'items' => [
                        [
                            'key' => 'assessment-settings',
                            'label' => __('Assessment settings'),
                            'icon' => 'ScanSearch',
                            'description' => __('Settings for classification, risk assessment and more'),
                        ],
                        [
                            'key' => 'supplier-categories',
                            'label' => __('Supplier categories'),
                            'icon' => 'Truck',
                            'description' => __('The framework for supplier evaluation'),
                        ],
                        [
                            'key' => 'activity-flow-templates',
                            'label' => __('Activity flow templates'),
                            'icon' => 'GitBranch',
                            'description' => __('Templates for activity flows to coordinate our work'),
                        ],
                        [
                            'key' => 'Project types',
                            'label' => __('Project types'),
                            'icon' => 'Briefcase',
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
                            'icon' => 'Users',
                            'description' => __('Users with access to the system'),
                        ],
                        [
                            'key' => 'sites',
                            'label' => __('Sites'),
                            'icon' => 'Building2',
                            'description' => __('Locations where we work'),
                        ],
                        [
                            'key' => 'departments',
                            'label' => __('Departments'),
                            'icon' => 'Layers',
                            'description' => __('Departments within our organization'),
                        ],
                        [
                            'key' => 'user-notification-settings',
                            'label' => __('User notification settings'),
                            'icon' => 'Bell',
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
                            'icon' => 'Key',
                            'description' => __('User access groups for managing permissions'),
                        ],
                        [
                            'key' => 'custom-properties',
                            'label' => __('Custom properties'),
                            'icon' => 'Settings',
                            'description' => __('Company-defined properties for customizing the system'),
                        ],
                        [
                            'key' => 'api-tokens',
                            'label' => __('API tokens'),
                            'icon' => 'Key',
                            'description' => __('API tokens for external integrations'),
                        ],
                        [
                            'key' => 'tags',
                            'label' => __('Tag collection'),
                            'icon' => 'Tag',
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

