<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => $this->buildMenuCategories(),
        ]);
    }

    private function buildMenuCategories(): array
    {
        $categories = [];

        // ─── Mitt arbete (always visible for authenticated users) ────────
        $categories[] = [
            'label'        => 'Mitt arbete',
            'categoryIcon' => 'ClipboardList',
            'columns'      => [
                [
                    'heading' => 'Personligt',
                    'items'   => [
                        [
                            'key'         => 'min-profil',
                            'label'       => 'Min profil',
                            'icon'        => 'UserCircle',
                            'description' => 'Din anställningsprofil och inställningar',
                        ],
                        [
                            'key'         => 'mina-uppgifter',
                            'label'       => 'Mina uppgifter',
                            'icon'        => 'ClipboardList',
                            'description' => 'Åtgärder, aktiviteter och att-göra',
                        ],
                        [
                            'key'         => 'mina-dokument',
                            'label'       => 'Mina dokument',
                            'icon'        => 'FolderOpen',
                            'description' => 'Dokument kopplade till dig',
                        ],
                    ],
                ],
            ],
        ];

        // ─── Register & Resurser ─────────────────────────────────────────
        $registerColumns = [];

        $verksamhetItems = [];
        if ($this->allows('viewAny', \App\Models\CustomerProcess::class)) {
            $verksamhetItems[] = [
                'key'         => 'processer',
                'label'       => 'Processer',
                'icon'        => 'GitBranch',
                'description' => 'Processkartor och flöden',
            ];
        }
        if ($this->allows('viewAny', \App\Models\LibraryDocument::class)) {
            $verksamhetItems[] = [
                'key'         => 'dokumentarkiv',
                'label'       => 'Dokumentarkiv',
                'icon'        => 'FileText',
                'description' => 'Gemensamma dokument och mallar',
            ];
        }
        if ($verksamhetItems !== []) {
            $registerColumns[] = ['heading' => 'Verksamhetsstöd', 'items' => $verksamhetItems];
        }

        $relationItems = [];
        if ($this->allows('viewAny', \App\Models\Customer::class)) {
            $relationItems[] = [
                'key'         => 'kunder',
                'label'       => 'Kunder',
                'icon'        => 'Globe',
                'description' => 'Kundregister och kundkrav',
            ];
        }
        if ($this->allows('viewAny', \App\Models\Supplier::class)) {
            $relationItems[] = [
                'key'         => 'leverantorer',
                'label'       => 'Leverantörer',
                'icon'        => 'Truck',
                'description' => 'Leverantörsbedömning',
            ];
        }
        if ($this->allows('viewAny', \App\Models\Agreement::class)) {
            $relationItems[] = [
                'key'         => 'avtal',
                'label'       => 'Avtal',
                'icon'        => 'FileSignature',
                'description' => 'Avtal och överenskommelser',
            ];
        }
        if ($relationItems !== []) {
            $registerColumns[] = ['heading' => 'Relationer', 'items' => $relationItems];
        }

        $assetItems = [];
        if ($this->allows('viewAny', \App\Models\Asset::class)) {
            $assetItems[] = [
                'key'         => 'tillgangar',
                'label'       => 'Tillgångar',
                'icon'        => 'Shield',
                'description' => 'IT-system, utrustning, lokaler',
            ];
        }
        if ($this->allows('viewAny', \App\Models\InformationType::class)) {
            $assetItems[] = [
                'key'         => 'informationstyper',
                'label'       => 'Informationstyper',
                'icon'        => 'Database',
                'description' => 'Klassificera informationstillgångar',
            ];
        }
        if ($this->allows('viewAny', \App\Models\Chemical::class)) {
            $assetItems[] = [
                'key'         => 'kemikalier',
                'label'       => 'Kemikalieförteckning',
                'icon'        => 'FlaskConical',
                'description' => 'Kemikaliehantering',
            ];
        }
        if ($assetItems !== []) {
            $registerColumns[] = ['heading' => 'Tillgångar & Teknik', 'items' => $assetItems];
        }

        if ($registerColumns !== []) {
            $categories[] = [
                'label'        => 'Register & Resurser',
                'categoryIcon' => 'Shield',
                'columns'      => $registerColumns,
            ];
        }

        // ─── Efterlevnad & Krav ──────────────────────────────────────────
        $complianceItems = [];
        if ($this->allows('viewAny', \App\Models\RequirementSource::class)) {
            $complianceItems[] = [
                'key'         => 'kravkallor',
                'label'       => 'Kravkällor',
                'icon'        => 'Scale',
                'description' => 'Lagar, föreskrifter och standarder',
            ];
        }
        if ($this->allows('viewAny', \App\Models\Control::class)) {
            $complianceItems[] = [
                'key'         => 'kontroller',
                'label'       => 'Kontroller',
                'icon'        => 'CheckCircle2',
                'description' => 'Kontroller kopplade till krav',
            ];
        }
        if ($this->allows('viewAny', \App\Models\DataCategory::class)) {
            $complianceItems[] = [
                'key'         => 'personuppgifter',
                'label'       => 'Personuppgifter',
                'icon'        => 'Database',
                'description' => 'GDPR och dataskydd (ISO 27001)',
            ];
        }
        if ($this->allows('viewAny', \App\Models\SustainabilityAspect::class)) {
            $complianceItems[] = [
                'key'         => 'hallbarhet',
                'label'       => 'Hållbarhet',
                'icon'        => 'Leaf',
                'description' => 'Miljö- och hållbarhetsarbete (ISO 14001)',
            ];
        }
        if ($complianceItems !== []) {
            $categories[] = [
                'label'        => 'Efterlevnad & Krav',
                'categoryIcon' => 'Scale',
                'columns'      => [['heading' => 'Compliance', 'items' => $complianceItems]],
            ];
        }

        // ─── Risk & Förbättring ──────────────────────────────────────────
        $riskItems = [];
        if ($this->allows('viewAny', \App\Models\RiskProject::class)) {
            $riskItems[] = [
                'key'         => 'riskhantering',
                'label'       => 'Riskhantering',
                'icon'        => 'AlertTriangle',
                'description' => 'Riskprojekt och riskbedömningar',
            ];
        }
        if ($this->allows('viewAny', \App\Models\Finding::class)) {
            $riskItems[] = [
                'key'         => 'avvikelser',
                'label'       => 'Avvikelser & Förbättringar',
                'icon'        => 'RefreshCcw',
                'description' => 'Rapportera och följ upp avvikelser',
            ];
        }
        if ($this->allows('viewAny', \App\Models\ComplianceEvaluation::class)) {
            $riskItems[] = [
                'key'         => 'revisioner',
                'label'       => 'Revisioner',
                'icon'        => 'ScanSearch',
                'description' => 'Interna och externa revisioner',
            ];
        }
        if ($riskItems !== []) {
            $categories[] = [
                'label'        => 'Risk & Förbättring',
                'categoryIcon' => 'AlertTriangle',
                'columns'      => [['heading' => 'Strategiska verktyg', 'items' => $riskItems]],
            ];
        }

        return $categories;
    }

    /**
     * Safely check a Gate ability, returning false on any error.
     */
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

