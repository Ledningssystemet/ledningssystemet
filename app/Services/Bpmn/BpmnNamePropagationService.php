<?php

namespace App\Services\Bpmn;

use App\Models\Asset;
use App\Models\AssetInformationType;
use App\Models\InformationType;
use App\Models\InformationTypeProcessActivity;
use App\Models\Process;
use App\Models\ProcessLink;
use DOMDocument;
use DOMElement;
use DOMXPath;

class BpmnNamePropagationService
{
    public function syncAssetRename(Asset $asset, string $oldName): void
    {
        $processIds = AssetInformationType::query()
            ->where('asset_id', $asset->id)
            ->pluck('process_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->syncElementNameInProcesses($processIds, 'dataStoreReference', $oldName, (string) $asset->name);
    }

    public function syncInformationTypeRename(InformationType $informationType, string $oldName): void
    {
        $processIdsFromAssets = AssetInformationType::query()
            ->where('information_type_id', $informationType->id)
            ->pluck('process_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->all();

        $processIdsFromActivities = InformationTypeProcessActivity::query()
            ->join('process_activities', 'process_activities.id', '=', 'information_type_process_activity.process_activity_id')
            ->where('information_type_process_activity.information_type_id', $informationType->id)
            ->pluck('process_activities.process_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->all();

        $processIds = array_values(array_unique(array_merge($processIdsFromAssets, $processIdsFromActivities)));

        $this->syncElementNameInProcesses($processIds, 'dataObjectReference', $oldName, (string) $informationType->name);
    }

    public function syncProcessRename(Process $process, string $oldName): void
    {
        $processIds = ProcessLink::query()
            ->where('linked_process_id', $process->id)
            ->pluck('process_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->syncElementNameInProcesses($processIds, 'subProcess', $oldName, (string) $process->name);
    }

    /**
     * @param array<int, int> $processIds
     */
    private function syncElementNameInProcesses(array $processIds, string $elementType, string $oldName, string $newName): void
    {
        $oldName = trim($oldName);
        $newName = trim($newName);

        if ($processIds === [] || $oldName === '' || $newName === '' || mb_strtolower($oldName) === mb_strtolower($newName)) {
            return;
        }

        $processes = Process::query()
            ->whereIn('id', $processIds)
            ->get(['id', 'bpmn', 'publishedbpmn']);

        foreach ($processes as $process) {
            $updatedBpmn = $this->renameElementNameInXml($process->bpmn, $elementType, $oldName, $newName);
            $updatedPublishedBpmn = $this->renameElementNameInXml($process->publishedbpmn, $elementType, $oldName, $newName);

            $updates = [];

            if ($updatedBpmn !== $process->bpmn) {
                $updates['bpmn'] = $updatedBpmn;
            }

            if ($updatedPublishedBpmn !== $process->publishedbpmn) {
                $updates['publishedbpmn'] = $updatedPublishedBpmn;
            }

            if ($updates === []) {
                continue;
            }

            $updates['updated_at'] = now();

            Process::query()
                ->whereKey($process->id)
                ->update($updates);
        }
    }

    private function renameElementNameInXml(?string $xml, string $elementType, string $oldName, string $newName): ?string
    {
        if (! is_string($xml) || trim($xml) === '') {
            return $xml;
        }

        $dom = new DOMDocument();

        if (! $this->loadXml($dom, $xml)) {
            return $xml;
        }

        $xpath = new DOMXPath($dom);
        $didChange = false;

        foreach ($xpath->query("//*[local-name()='{$elementType}']") ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $currentName = trim((string) $node->getAttribute('name'));

            if ($currentName === '' || mb_strtolower($currentName) !== mb_strtolower($oldName)) {
                continue;
            }

            $node->setAttribute('name', $newName);
            $didChange = true;
        }

        if (! $didChange) {
            return $xml;
        }

        $serialized = $dom->saveXML();

        return is_string($serialized) && $serialized !== '' ? $serialized : $xml;
    }

    private function loadXml(DOMDocument $dom, string $xml): bool
    {
        $previous = libxml_use_internal_errors(true);

        try {
            return $dom->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}

