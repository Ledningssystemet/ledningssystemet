<?php

namespace App\Mcp;

use App\Models\Asset;
use App\Models\InformationType;
use PhpMcp\Server\Attributes\McpTool;

class AssetTools
{
    /**
     * List assets (information assets) with optional search.
     *
     * @param  string  $search  Optional search term to filter by name or description.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of assets with classification details.
     */
    #[McpTool(name: 'list_assets')]
    public function listAssets(string $search = '', int $limit = 50): array
    {
        $query = Asset::with([
            'int_responsible_user:id,name',
            'int_confidentiality_class:id,name',
            'int_integrity_class:id,name',
            'int_availability_class:id,name',
            'int_supplier:id,name',
        ])->select(['id', 'name', 'description', 'responsible_user_id', 'confidentiality_class_id', 'integrity_class_id', 'availability_class_id', 'supplier_id', 'mtd', 'rpo']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->limit($limit)->get()->map(fn (Asset $a) => [
            'id'                    => $a->id,
            'name'                  => $a->name,
            'description'           => $a->description,
            'responsible_user'      => $a->int_responsible_user?->name,
            'supplier'              => $a->int_supplier?->name,
            'confidentiality_class' => $a->int_confidentiality_class?->name,
            'integrity_class'       => $a->int_integrity_class?->name,
            'availability_class'    => $a->int_availability_class?->name,
            'mtd_hours'             => $a->mtd,
            'rpo_hours'             => $a->rpo,
        ])->toArray();
    }

    /**
     * Get detailed information about a specific asset.
     *
     * @param  int  $id  The ID of the asset.
     * @return array Asset details including all classification data.
     */
    #[McpTool(name: 'get_asset')]
    public function getAsset(int $id): array
    {
        $asset = Asset::with([
            'int_responsible_user:id,name',
            'int_confidentiality_class:id,name',
            'int_integrity_class:id,name',
            'int_availability_class:id,name',
            'int_supplier:id,name',
        ])->findOrFail($id);

        return [
            'id'                    => $asset->id,
            'name'                  => $asset->name,
            'description'           => $asset->description,
            'responsible_user'      => $asset->int_responsible_user?->name,
            'supplier'              => $asset->int_supplier?->name,
            'confidentiality_class' => $asset->int_confidentiality_class?->name,
            'integrity_class'       => $asset->int_integrity_class?->name,
            'availability_class'    => $asset->int_availability_class?->name,
            'mtd_hours'             => $asset->mtd,
            'rpo_hours'             => $asset->rpo,
        ];
    }

    /**
     * List information types with optional search.
     *
     * @param  string  $search  Optional search term to filter by name or description.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of information types.
     */
    #[McpTool(name: 'list_information_types')]
    public function listInformationTypes(string $search = '', int $limit = 50): array
    {
        $query = InformationType::select(['id', 'name', 'description']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->limit($limit)->get()->toArray();
    }
}

