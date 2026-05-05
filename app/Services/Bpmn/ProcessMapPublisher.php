<?php

namespace App\Services\Bpmn;

use App\Models\Asset;
use App\Models\AssetInformationType;
use App\Models\InformationType;
use App\Models\InformationTypeProcessActivity;
use App\Models\Process;
use App\Models\ProcessActivity;
use App\Models\ProcessLink;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessMapPublisher
{
    public function __construct(private readonly BpmnPublishValidator $validator)
    {
    }

    public function publish(Process $process, string $xml): Process
    {
        $this->validator->validateForPublish($xml);

        if (($process->bpmn ?? null) !== $xml) {
            throw ValidationException::withMessages([
                'publishedbpmn' => ['pages.process_editor.validation.save_before_publish'],
            ]);
        }

        $snapshot = $this->buildSnapshot($process, $xml);

        return DB::transaction(function () use ($process, $xml, $snapshot): Process {
            $process->bpmn = $xml;
            $process->publishedbpmn = $xml;
            $process->save();

            $activityIdsByTaskId = $this->syncProcessActivities($process, $snapshot);
            $this->syncInformationTypeProcessActivities($activityIdsByTaskId, $snapshot['task_to_information_types']);
            $this->syncAssetInformationTypes($process, $snapshot['information_types']);
            $this->syncProcessLinks($process, $snapshot['linked_processes']);

            return $process->fresh();
        });
    }

    /**
     * @return array{
     *     tasks: array<string, array{id: string, name: string}>,
     *     task_order: array<int, string>,
     *     task_to_information_types: array<string, array<string, string>>,
     *     information_types: array<string, array{name: string, assets: array<string, string>}>,
     *     linked_processes: array<string, string>
     * }
     */
    private function buildSnapshot(Process $process, string $xml): array
    {
        $dom = new DOMDocument();
        $loaded = $this->loadXml($dom, $xml);

        if (! $loaded) {
            throw ValidationException::withMessages([
                'publishedbpmn' => ['pages.process_editor.validation.invalid_xml'],
            ]);
        }

        $xpath = new DOMXPath($dom);
        $typesById = $this->collectElementTypesById($xpath);
        $namesById = $this->collectElementNamesById($xpath, ['task', 'dataObjectReference', 'dataStoreReference', 'subProcess']);
        $sequenceConnections = $this->collectSequenceConnections($xpath);
        $associationConnections = $this->collectAssociationConnections($xpath);

        $tasks = [];
        foreach ($xpath->query("//*[local-name()='task']") ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $taskId = trim((string) $node->getAttribute('id'));
            $taskName = trim((string) $node->getAttribute('name'));

            if ($taskId === '' || $taskName === '') {
                continue;
            }

            $tasks[$taskId] = [
                'id' => $taskId,
                'name' => $taskName,
            ];
        }

        $dataObjectNames = [];
        $dataStoreNames = [];
        $informationTypes = [];
        $linkedProcesses = [];

        foreach ($typesById as $elementId => $type) {
            $name = trim((string) ($namesById[$elementId] ?? ''));
            if ($name === '') {
                continue;
            }

            $normalizedName = mb_strtolower($name);

            if ($type === 'dataObjectReference') {
                $dataObjectNames[$elementId] = [
                    'normalized' => $normalizedName,
                    'name' => $name,
                ];
                $informationTypes[$normalizedName] ??= [
                    'name' => $name,
                    'assets' => [],
                ];
            }

            if ($type === 'dataStoreReference') {
                $dataStoreNames[$elementId] = [
                    'normalized' => $normalizedName,
                    'name' => $name,
                ];
            }

            if ($type === 'subProcess') {
                $linkedProcesses[$normalizedName] = $name;
            }
        }

        $taskToInformationTypes = [];

        foreach ($associationConnections as $connection) {
            $sourceType = $typesById[$connection['source']] ?? null;
            $targetType = $typesById[$connection['target']] ?? null;

            if (
                ($sourceType === 'task' && $targetType === 'dataObjectReference')
                || ($sourceType === 'dataObjectReference' && $targetType === 'task')
            ) {
                $taskId = $sourceType === 'task' ? $connection['source'] : $connection['target'];
                $dataObjectId = $sourceType === 'dataObjectReference' ? $connection['source'] : $connection['target'];
                $dataObject = $dataObjectNames[$dataObjectId] ?? null;

                if ($dataObject !== null) {
                    $taskToInformationTypes[$taskId][$dataObject['normalized']] = $dataObject['name'];
                }

                continue;
            }

            if (
                ($sourceType === 'dataObjectReference' && $targetType === 'dataStoreReference')
                || ($sourceType === 'dataStoreReference' && $targetType === 'dataObjectReference')
            ) {
                $dataObjectId = $sourceType === 'dataObjectReference' ? $connection['source'] : $connection['target'];
                $dataStoreId = $sourceType === 'dataStoreReference' ? $connection['source'] : $connection['target'];

                $dataObject = $dataObjectNames[$dataObjectId] ?? null;
                $dataStore = $dataStoreNames[$dataStoreId] ?? null;

                if ($dataObject !== null && $dataStore !== null) {
                    $informationTypes[$dataObject['normalized']]['assets'][$dataStore['normalized']] = $dataStore['name'];
                }
            }
        }

        return [
            'tasks' => $tasks,
            'task_order' => $this->determineTaskOrder(array_keys($tasks), $typesById, $sequenceConnections),
            'task_to_information_types' => $taskToInformationTypes,
            'information_types' => $informationTypes,
            'linked_processes' => $linkedProcesses,
        ];
    }

    /**
     * @param array{
     *     tasks: array<string, array{id: string, name: string}>,
     *     task_order: array<int, string>
     * } $snapshot
     * @return array<string, int>
     */
    private function syncProcessActivities(Process $process, array $snapshot): array
    {
        $existingActivities = ProcessActivity::query()
            ->where('process_id', $process->id)
            ->get();

        $existingByCandidateId = [];

        foreach ($existingActivities as $existingActivity) {
            $existingByCandidateId[$existingActivity->bpmnId] = $existingActivity;

            $rawTaskId = $this->extractRawTaskId($process, $existingActivity->bpmnId);
            if ($rawTaskId !== null) {
                $existingByCandidateId[$rawTaskId] ??= $existingActivity;
            }
        }

        $keptActivityIds = [];
        $activityIdsByTaskId = [];

        foreach ($snapshot['task_order'] as $index => $taskId) {
            $task = $snapshot['tasks'][$taskId] ?? null;
            if ($task === null) {
                continue;
            }

            $scopedTaskId = $this->scopedTaskId($process, $taskId);
            $activity = $existingByCandidateId[$scopedTaskId] ?? $existingByCandidateId[$taskId] ?? new ProcessActivity();

            $activity->process_id = $process->id;
            $activity->name = $task['name'];
            $activity->bpmnId = $scopedTaskId;
            $activity->ordinal = $index + 1;
            $activity->save();

            $keptActivityIds[] = $activity->id;
            $activityIdsByTaskId[$taskId] = (int) $activity->id;
        }

        $orphanedActivities = ProcessActivity::query()
            ->where('process_id', $process->id)
            ->when($keptActivityIds !== [], static fn ($query) => $query->whereNotIn('id', $keptActivityIds))
            ->when($keptActivityIds === [], static fn ($query) => $query)
            ->get();

        foreach ($orphanedActivities as $orphanedActivity) {
            $orphanedActivity->delete();
        }

        return $activityIdsByTaskId;
    }

    /**
     * @param array<string, int> $activityIdsByTaskId
     * @param array<string, array<string, string>> $taskToInformationTypes
     */
    private function syncInformationTypeProcessActivities(array $activityIdsByTaskId, array $taskToInformationTypes): void
    {
        $activityIds = array_values($activityIdsByTaskId);
        if ($activityIds === []) {
            return;
        }

        InformationTypeProcessActivity::query()
            ->whereIn('process_activity_id', $activityIds)
            ->delete();

        $informationTypesByNormalizedName = [];

        foreach ($taskToInformationTypes as $informationTypeNames) {
            foreach ($informationTypeNames as $normalizedName => $name) {
                $informationTypesByNormalizedName[$normalizedName] ??= InformationType::query()->firstOrCreate([
                    'name' => $name,
                ]);
            }
        }

        $rows = [];
        $seenRows = [];

        foreach ($taskToInformationTypes as $taskId => $informationTypeNames) {
            $processActivityId = $activityIdsByTaskId[$taskId] ?? null;

            if ($processActivityId === null) {
                continue;
            }

            foreach ($informationTypeNames as $normalizedName => $name) {
                $informationType = $informationTypesByNormalizedName[$normalizedName] ?? null;
                if ($informationType === null) {
                    continue;
                }

                $rowKey = $informationType->id.'-'.$processActivityId;
                if (isset($seenRows[$rowKey])) {
                    continue;
                }

                $seenRows[$rowKey] = true;
                $rows[] = [
                    'information_type_id' => $informationType->id,
                    'process_activity_id' => $processActivityId,
                ];
            }
        }

        if ($rows !== []) {
            InformationTypeProcessActivity::query()->insert($rows);
        }
    }

    /**
     * @param array<string, array{name: string, assets: array<string, string>}> $informationTypes
     */
    private function syncAssetInformationTypes(Process $process, array $informationTypes): void
    {
        AssetInformationType::query()
            ->where('process_id', $process->id)
            ->delete();

        $rows = [];

        foreach ($informationTypes as $informationType) {
            $informationTypeModel = InformationType::query()->firstOrCreate([
                'name' => $informationType['name'],
            ]);

            foreach ($informationType['assets'] as $assetName) {
                $asset = Asset::query()->firstOrCreate([
                    'name' => $assetName,
                ]);

                $rows[] = [
                    'asset_id' => $asset->id,
                    'information_type_id' => $informationTypeModel->id,
                    'process_id' => $process->id,
                ];
            }
        }

        if ($rows !== []) {
            AssetInformationType::query()->insert($rows);
        }
    }

    /**
     * @param array<string, string> $linkedProcesses
     */
    private function syncProcessLinks(Process $process, array $linkedProcesses): void
    {
        ProcessLink::query()
            ->where('process_id', $process->id)
            ->delete();

        if ($linkedProcesses === []) {
            return;
        }

        $processesByNormalizedName = Process::query()
            ->select('id', 'name')
            ->whereNotNull('name')
            ->get()
            ->mapWithKeys(static fn (Process $candidate): array => [mb_strtolower(trim((string) $candidate->name)) => $candidate])
            ->all();

        $rows = [];

        foreach ($linkedProcesses as $normalizedName => $name) {
            $linkedProcess = $processesByNormalizedName[$normalizedName] ?? null;

            if (! $linkedProcess instanceof Process) {
                continue;
            }

            $rows[] = [
                'process_id' => $process->id,
                'linked_process_id' => $linkedProcess->id,
            ];
        }

        if ($rows !== []) {
            ProcessLink::query()->insert($rows);
        }
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

    /**
     * @return array<string, string>
     */
    private function collectElementTypesById(DOMXPath $xpath): array
    {
        $allowedTypes = [
            'startEvent',
            'endEvent',
            'task',
            'exclusiveGateway',
            'sequenceFlow',
            'dataObjectReference',
            'dataStoreReference',
            'textAnnotation',
            'subProcess',
        ];

        $typesById = [];

        foreach ($allowedTypes as $type) {
            foreach ($xpath->query("//*[local-name()='{$type}']") ?: [] as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $id = trim((string) $node->getAttribute('id'));
                if ($id === '') {
                    continue;
                }

                $typesById[$id] = $type;
            }
        }

        return $typesById;
    }

    /**
     * @param array<int, string> $nameTypes
     * @return array<string, string>
     */
    private function collectElementNamesById(DOMXPath $xpath, array $nameTypes): array
    {
        $namesById = [];

        foreach ($nameTypes as $type) {
            foreach ($xpath->query("//*[local-name()='{$type}']") ?: [] as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                $id = trim((string) $node->getAttribute('id'));
                if ($id === '') {
                    continue;
                }

                $namesById[$id] = trim((string) $node->getAttribute('name'));
            }
        }

        return $namesById;
    }

    /**
     * @return array<int, array{source: string, target: string}>
     */
    private function collectSequenceConnections(DOMXPath $xpath): array
    {
        $connections = [];

        foreach ($xpath->query("//*[local-name()='sequenceFlow']") ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $source = trim((string) $node->getAttribute('sourceRef'));
            $target = trim((string) $node->getAttribute('targetRef'));

            if ($source === '' || $target === '') {
                continue;
            }

            $connections[] = ['source' => $source, 'target' => $target];
        }

        return $connections;
    }

    /**
     * @return array<int, array{source: string, target: string}>
     */
    private function collectAssociationConnections(DOMXPath $xpath): array
    {
        $connections = [];

        foreach (['association', 'dataInputAssociation', 'dataOutputAssociation'] as $type) {
            foreach ($xpath->query("//*[local-name()='{$type}']") ?: [] as $node) {
                if (! $node instanceof DOMElement) {
                    continue;
                }

                foreach ($this->extractConnections($xpath, $node) as $connection) {
                    $connections[] = $connection;
                }
            }
        }

        return $connections;
    }

    /**
     * @return array<int, array{source: string, target: string}>
     */
    private function extractConnections(DOMXPath $xpath, DOMElement $edge): array
    {
        $source = trim((string) $edge->getAttribute('sourceRef'));
        $target = trim((string) $edge->getAttribute('targetRef'));

        if ($source !== '' && $target !== '') {
            return [['source' => $source, 'target' => $target]];
        }

        $sources = [];
        $targets = [];

        foreach ($xpath->query("./*[local-name()='sourceRef']", $edge) ?: [] as $sourceNode) {
            $sourceValue = trim($sourceNode->textContent);
            if ($sourceValue !== '') {
                $sources[] = $sourceValue;
            }
        }

        foreach ($xpath->query("./*[local-name()='targetRef']", $edge) ?: [] as $targetNode) {
            $targetValue = trim($targetNode->textContent);
            if ($targetValue !== '') {
                $targets[] = $targetValue;
            }
        }

        if ($sources === [] || $targets === []) {
            return [];
        }

        $connections = [];

        foreach ($sources as $sourceId) {
            foreach ($targets as $targetId) {
                $connections[] = ['source' => $sourceId, 'target' => $targetId];
            }
        }

        return $connections;
    }

    /**
     * @param array<int, string> $taskIdsInDocumentOrder
     * @param array<string, string> $typesById
     * @param array<int, array{source: string, target: string}> $sequenceConnections
     * @return array<int, string>
     */
    private function determineTaskOrder(array $taskIdsInDocumentOrder, array $typesById, array $sequenceConnections): array
    {
        $outgoingTargets = [];
        $startEventIds = [];

        foreach ($typesById as $elementId => $type) {
            if ($type === 'startEvent') {
                $startEventIds[] = $elementId;
            }
        }

        foreach ($sequenceConnections as $connection) {
            $outgoingTargets[$connection['source']][] = $connection['target'];
        }

        $queue = $startEventIds;
        $visitedNodes = [];
        $orderedTaskIds = [];

        while ($queue !== []) {
            $currentNodeId = array_shift($queue);
            if (! is_string($currentNodeId) || isset($visitedNodes[$currentNodeId])) {
                continue;
            }

            $visitedNodes[$currentNodeId] = true;
            $currentType = $typesById[$currentNodeId] ?? null;

            if ($currentType === 'task') {
                $orderedTaskIds[] = $currentNodeId;
            }

            foreach ($outgoingTargets[$currentNodeId] ?? [] as $targetId) {
                if (! isset($visitedNodes[$targetId])) {
                    $queue[] = $targetId;
                }
            }
        }

        foreach ($taskIdsInDocumentOrder as $taskId) {
            if (! in_array($taskId, $orderedTaskIds, true)) {
                $orderedTaskIds[] = $taskId;
            }
        }

        return $orderedTaskIds;
    }

    private function scopedTaskId(Process $process, string $taskId): string
    {
        return $process->id.':'.$taskId;
    }

    private function extractRawTaskId(Process $process, string $storedTaskId): ?string
    {
        $prefix = $process->id.':';

        if (! str_starts_with($storedTaskId, $prefix)) {
            return null;
        }

        $rawTaskId = substr($storedTaskId, strlen($prefix));

        return $rawTaskId !== '' ? $rawTaskId : null;
    }
}
