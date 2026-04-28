<?php

namespace App\Services\Bpmn;

use App\Models\Process;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Validation\ValidationException;

class BpmnPublishValidator
{
    /**
     * @var array<int, string>
     */
    private array $errors = [];

    public function __construct(private readonly BpmnTextContentValidator $textContentValidator)
    {
    }

    public function validateForPublish(string $xml): void
    {
        $this->errors = [];

        $dom = new DOMDocument();
        $loaded = $this->loadXml($dom, $xml);

        if (! $loaded) {
            $this->addError('pages.process_editor.validation.invalid_xml');
            $this->throwIfInvalid();

            return;
        }

        $xpath = new DOMXPath($dom);

        if ($this->textContentValidator->hasInvalidTextInXPath($xpath)) {
            $this->addError(BpmnTextContentValidator::ERROR_KEY);
        }

        $typesById = $this->collectElementTypesById($xpath);
        $namesById = $this->collectElementNamesById($xpath, ['dataObjectReference', 'subProcess']);

        $sequenceConnections = $this->collectSequenceConnections($xpath);
        $associationConnections = $this->collectAssociationConnections($xpath);

        $this->validateAllowedSequenceConnections($typesById, $sequenceConnections);
        $this->validateAllowedAssociationConnections($typesById, $associationConnections);

        $this->validateStartEvents($typesById, $sequenceConnections);
        $this->validateEndEvents($typesById, $sequenceConnections);
        $this->validateDataObjectTaskAssociation($typesById, $associationConnections);
        $this->validateDataStoreAssociation($typesById, $associationConnections, $namesById);
        $this->validateSubProcessNames($typesById, $namesById);

        $this->throwIfInvalid();
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
            $nodes = $xpath->query("//*[local-name()='{$type}']");
            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
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
            $nodes = $xpath->query("//*[local-name()='{$type}']");
            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
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
        $nodes = $xpath->query("//*[local-name()='sequenceFlow']");

        if ($nodes === false) {
            return $connections;
        }

        foreach ($nodes as $node) {
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
        $edgeTypes = ['association', 'dataInputAssociation', 'dataOutputAssociation'];

        foreach ($edgeTypes as $type) {
            $nodes = $xpath->query("//*[local-name()='{$type}']");
            if ($nodes === false) {
                continue;
            }

            foreach ($nodes as $node) {
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

        $sourceNodes = $xpath->query("./*[local-name()='sourceRef']", $edge);
        if ($sourceNodes !== false) {
            foreach ($sourceNodes as $sourceNode) {
                $sourceValue = trim($sourceNode->textContent);
                if ($sourceValue !== '') {
                    $sources[] = $sourceValue;
                }
            }
        }

        $targetNodes = $xpath->query("./*[local-name()='targetRef']", $edge);
        if ($targetNodes !== false) {
            foreach ($targetNodes as $targetNode) {
                $targetValue = trim($targetNode->textContent);
                if ($targetValue !== '') {
                    $targets[] = $targetValue;
                }
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
     * @param array<string, string> $typesById
     * @param array<int, array{source: string, target: string}> $connections
     */
    private function validateAllowedSequenceConnections(array $typesById, array $connections): void
    {
        $allowedPairs = [
            'startEvent->task' => true,
            'task->task' => true,
            'task->exclusiveGateway' => true,
            'exclusiveGateway->task' => true,
            'task->endEvent' => true,
        ];

        foreach ($connections as $connection) {
            $sourceType = $typesById[$connection['source']] ?? null;
            $targetType = $typesById[$connection['target']] ?? null;

            if (! is_string($sourceType) || ! is_string($targetType)) {
                $this->addError('pages.process_editor.validation.invalid_sequence_reference');
                continue;
            }

            if (! isset($allowedPairs[$sourceType.'->'.$targetType])) {
                $this->addError('pages.process_editor.validation.invalid_sequence_connection');
            }
        }
    }

    /**
     * @param array<string, string> $typesById
     * @param array<int, array{source: string, target: string}> $connections
     */
    private function validateAllowedAssociationConnections(array $typesById, array $connections): void
    {
        $allowedPairs = [
            'task->dataObjectReference' => true,
            'dataObjectReference->dataStoreReference' => true,
            'task->subProcess' => true,
        ];

        foreach ($connections as $connection) {
            $sourceType = $typesById[$connection['source']] ?? null;
            $targetType = $typesById[$connection['target']] ?? null;

            if (! is_string($sourceType) || ! is_string($targetType)) {
                $this->addError('pages.process_editor.validation.invalid_association_reference');
                continue;
            }

            if ($sourceType === 'textAnnotation' || $targetType === 'textAnnotation') {
                continue;
            }

            if (! isset($allowedPairs[$sourceType.'->'.$targetType])) {
                $this->addError('pages.process_editor.validation.invalid_association_connection');
            }
        }
    }

    /**
     * @param array<string, string> $typesById
     * @param array<int, array{source: string, target: string}> $connections
     */
    private function validateStartEvents(array $typesById, array $connections): void
    {
        foreach ($typesById as $id => $type) {
            if ($type !== 'startEvent') {
                continue;
            }

            $hasTaskSuccessor = false;

            foreach ($connections as $connection) {
                if ($connection['source'] !== $id) {
                    continue;
                }

                if (($typesById[$connection['target']] ?? null) === 'task') {
                    $hasTaskSuccessor = true;
                    break;
                }
            }

            if (! $hasTaskSuccessor) {
                $this->addError('pages.process_editor.validation.start_event_requires_task');
            }
        }
    }

    /**
     * @param array<string, string> $typesById
     * @param array<int, array{source: string, target: string}> $connections
     */
    private function validateEndEvents(array $typesById, array $connections): void
    {
        foreach ($typesById as $id => $type) {
            if ($type !== 'endEvent') {
                continue;
            }

            $hasTaskSource = false;

            foreach ($connections as $connection) {
                if ($connection['target'] !== $id) {
                    continue;
                }

                if (($typesById[$connection['source']] ?? null) === 'task') {
                    $hasTaskSource = true;
                    break;
                }
            }

            if (! $hasTaskSource) {
                $this->addError('pages.process_editor.validation.end_event_requires_task');
            }
        }
    }

    /**
     * @param array<string, string> $typesById
     * @param array<int, array{source: string, target: string}> $connections
     */
    private function validateDataObjectTaskAssociation(array $typesById, array $connections): void
    {
        foreach ($typesById as $id => $type) {
            if ($type !== 'dataObjectReference') {
                continue;
            }

            $hasTaskAssociation = false;

            foreach ($connections as $connection) {
                if ($connection['target'] !== $id) {
                    continue;
                }

                if (($typesById[$connection['source']] ?? null) === 'task') {
                    $hasTaskAssociation = true;
                    break;
                }
            }

            if (! $hasTaskAssociation) {
                $this->addError('pages.process_editor.validation.data_object_requires_task');
            }
        }
    }

    /**
     * @param array<string, string> $typesById
     * @param array<int, array{source: string, target: string}> $connections
     * @param array<string, string> $namesById
     */
    private function validateDataStoreAssociation(array $typesById, array $connections, array $namesById): void
    {
        $dataObjectToStore = [];

        foreach ($connections as $connection) {
            if (($typesById[$connection['source']] ?? null) !== 'dataObjectReference') {
                continue;
            }

            if (($typesById[$connection['target']] ?? null) !== 'dataStoreReference') {
                continue;
            }

            $dataObjectToStore[$connection['source']] = true;
        }

        foreach ($typesById as $id => $type) {
            if ($type !== 'dataStoreReference') {
                continue;
            }

            $hasDataObjectAssociation = false;

            foreach ($connections as $connection) {
                if ($connection['target'] !== $id) {
                    continue;
                }

                if (($typesById[$connection['source']] ?? null) === 'dataObjectReference') {
                    $hasDataObjectAssociation = true;
                    break;
                }
            }

            if (! $hasDataObjectAssociation) {
                $this->addError('pages.process_editor.validation.data_store_requires_data_object');
            }
        }

        $dataObjectsByName = [];

        foreach ($typesById as $id => $type) {
            if ($type !== 'dataObjectReference') {
                continue;
            }

            $name = trim((string) ($namesById[$id] ?? ''));
            $groupKey = $name !== '' ? mb_strtolower($name) : $id;
            $dataObjectsByName[$groupKey][] = $id;
        }

        foreach ($dataObjectsByName as $ids) {
            $hasDataStoreAssociation = false;

            foreach ($ids as $id) {
                if (isset($dataObjectToStore[$id])) {
                    $hasDataStoreAssociation = true;
                    break;
                }
            }

            if (! $hasDataStoreAssociation) {
                $this->addError('pages.process_editor.validation.data_object_requires_data_store');
            }
        }
    }

    /**
     * @param array<string, string> $typesById
     * @param array<string, string> $namesById
     */
    private function validateSubProcessNames(array $typesById, array $namesById): void
    {
        $existingProcessNames = Process::query()
            ->select('name')
            ->whereNotNull('name')
            ->pluck('name')
            ->map(static fn (string $name): string => mb_strtolower(trim($name)))
            ->filter(static fn (string $name): bool => $name !== '')
            ->all();

        $existingNameMap = array_fill_keys($existingProcessNames, true);

        foreach ($typesById as $id => $type) {
            if ($type !== 'subProcess') {
                continue;
            }

            $name = trim((string) ($namesById[$id] ?? ''));
            if ($name === '') {
                $this->addError('pages.process_editor.validation.sub_process_requires_name');
                continue;
            }

            if (! isset($existingNameMap[mb_strtolower($name)])) {
                $this->addError('pages.process_editor.validation.sub_process_name_not_found');
            }
        }
    }

    private function addError(string $key): void
    {
        if (! in_array($key, $this->errors, true)) {
            $this->errors[] = $key;
        }
    }

    private function throwIfInvalid(): void
    {
        if ($this->errors === []) {
            return;
        }

        throw ValidationException::withMessages([
            'publishedbpmn' => $this->errors,
        ]);
    }
}

