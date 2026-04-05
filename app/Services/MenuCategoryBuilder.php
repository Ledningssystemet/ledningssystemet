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
                            'key' => 'min-profil',
                            'label' => __('menu.items.my_profile.label'),
                            'icon' => 'UserCircle',
                            'description' => __('menu.items.my_profile.description'),
                        ],
                        [
                            'key' => 'mina-uppgifter',
                            'label' => __('menu.items.my_tasks.label'),
                            'icon' => 'ClipboardList',
                            'description' => __('menu.items.my_tasks.description'),
                        ],
                        [
                            'key' => 'mina-dokument',
                            'label' => __('menu.items.my_documents.label'),
                            'icon' => 'FolderOpen',
                            'description' => __('menu.items.my_documents.description'),
                        ],
                    ],
                ],
            ],
        ];

        $registerColumns = [];

        $verksamhetItems = [];
        if ($this->allows('viewAny', \App\Models\CustomerProcess::class)) {
            $verksamhetItems[] = [
                'key' => 'processer',
                'label' => __('menu.items.processes.label'),
                'icon' => 'GitBranch',
                'description' => __('menu.items.processes.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\LibraryDocument::class)) {
            $verksamhetItems[] = [
                'key' => 'dokumentarkiv',
                'label' => __('menu.items.document_archive.label'),
                'icon' => 'FileText',
                'description' => __('menu.items.document_archive.description'),
            ];
        }
        if ($verksamhetItems !== []) {
            $registerColumns[] = ['heading' => __('menu.headings.business_support'), 'items' => $verksamhetItems];
        }

        $relationItems = [];
        if ($this->allows('viewAny', \App\Models\Customer::class)) {
            $relationItems[] = [
                'key' => 'kunder',
                'label' => __('menu.items.customers.label'),
                'icon' => 'Globe',
                'description' => __('menu.items.customers.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\Supplier::class)) {
            $relationItems[] = [
                'key' => 'leverantorer',
                'label' => __('menu.items.suppliers.label'),
                'icon' => 'Truck',
                'description' => __('menu.items.suppliers.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\Agreement::class)) {
            $relationItems[] = [
                'key' => 'avtal',
                'label' => __('menu.items.agreements.label'),
                'icon' => 'FileSignature',
                'description' => __('menu.items.agreements.description'),
            ];
        }
        if ($relationItems !== []) {
            $registerColumns[] = ['heading' => __('menu.headings.relations'), 'items' => $relationItems];
        }

        $assetItems = [];
        if ($this->allows('viewAny', \App\Models\Asset::class)) {
            $assetItems[] = [
                'key' => 'tillgangar',
                'label' => __('menu.items.assets.label'),
                'icon' => 'Shield',
                'description' => __('menu.items.assets.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\InformationType::class)) {
            $assetItems[] = [
                'key' => 'informationstyper',
                'label' => __('menu.items.information_types.label'),
                'icon' => 'Database',
                'description' => __('menu.items.information_types.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\Chemical::class)) {
            $assetItems[] = [
                'key' => 'kemikalier',
                'label' => __('menu.items.chemicals.label'),
                'icon' => 'FlaskConical',
                'description' => __('menu.items.chemicals.description'),
            ];
        }
        if ($assetItems !== []) {
            $registerColumns[] = ['heading' => __('menu.headings.assets_technology'), 'items' => $assetItems];
        }

        if ($registerColumns !== []) {
            $categories[] = [
                'label' => __('menu.categories.registers_resources'),
                'categoryIcon' => 'Shield',
                'columns' => $registerColumns,
            ];
        }

        $complianceItems = [];
        if ($this->allows('viewAny', \App\Models\RequirementSource::class)) {
            $complianceItems[] = [
                'key' => 'kravkallor',
                'label' => __('menu.items.requirement_sources.label'),
                'icon' => 'Scale',
                'description' => __('menu.items.requirement_sources.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\Control::class)) {
            $complianceItems[] = [
                'key' => 'kontroller',
                'label' => __('menu.items.controls.label'),
                'icon' => 'CheckCircle2',
                'description' => __('menu.items.controls.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\DataCategory::class)) {
            $complianceItems[] = [
                'key' => 'personuppgifter',
                'label' => __('menu.items.personal_data.label'),
                'icon' => 'Database',
                'description' => __('menu.items.personal_data.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\SustainabilityAspect::class)) {
            $complianceItems[] = [
                'key' => 'hallbarhet',
                'label' => __('menu.items.sustainability.label'),
                'icon' => 'Leaf',
                'description' => __('menu.items.sustainability.description'),
            ];
        }
        if ($complianceItems !== []) {
            $categories[] = [
                'label' => __('menu.categories.compliance_requirements'),
                'categoryIcon' => 'Scale',
                'columns' => [['heading' => __('menu.headings.compliance'), 'items' => $complianceItems]],
            ];
        }

        $riskItems = [];
        if ($this->allows('viewAny', \App\Models\RiskProject::class)) {
            $riskItems[] = [
                'key' => 'riskhantering',
                'label' => __('menu.items.risk_management.label'),
                'icon' => 'AlertTriangle',
                'description' => __('menu.items.risk_management.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\Finding::class)) {
            $riskItems[] = [
                'key' => 'avvikelser',
                'label' => __('menu.items.findings.label'),
                'icon' => 'RefreshCcw',
                'description' => __('menu.items.findings.description'),
            ];
        }
        if ($this->allows('viewAny', \App\Models\ComplianceEvaluation::class)) {
            $riskItems[] = [
                'key' => 'revisioner',
                'label' => __('menu.items.audits.label'),
                'icon' => 'ScanSearch',
                'description' => __('menu.items.audits.description'),
            ];
        }
        if ($riskItems !== []) {
            $categories[] = [
                'label' => __('menu.categories.risk_improvement'),
                'categoryIcon' => 'AlertTriangle',
                'columns' => [['heading' => __('menu.headings.strategic_tools'), 'items' => $riskItems]],
            ];
        }

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

