<?php

namespace Ledningssystemet\Ledningssystemet\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Ledningssystemet\Ledningssystemet\Models\Asset;
use Ledningssystemet\Ledningssystemet\Models\InformationType;
use Ledningssystemet\Ledningssystemet\Models\Process;
use Ledningssystemet\Ledningssystemet\Models\ProcessActivity;

final class ProcessChartBpmn
{
    private const BPMN = 'http://www.omg.org/spec/BPMN/20100524/MODEL';

    private const BPMNDI = 'http://www.omg.org/spec/BPMN/20100524/DI';

    private const DC = 'http://www.omg.org/spec/DD/20100524/DC';

    private const DI = 'http://www.omg.org/spec/DD/20100524/DI';

    private const TYPES = [
        'task' => 'ProcessActivity',
        'subProcess' => 'Process',
        'dataObject' => 'InformationType',
        'dataStoreReference' => 'Asset',
    ];

    private const ARTIFACTS = ['startEvent', 'endEvent', 'exclusiveGateway', 'textAnnotation'];

    public static function fromXml(string $xml, ?int $processId = null): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);
            if (! $loaded || $document->doctype !== null) {
                throw new InvalidArgumentException('Invalid BPMN XML. DTDs are not supported.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('b', self::BPMN);
        $xpath->registerNamespace('bd', self::BPMNDI);
        $xpath->registerNamespace('dc', self::DC);
        $xpath->registerNamespace('di', self::DI);
        $processes = $xpath->query('/b:definitions/b:process');
        if ($processes->length !== 1) {
            throw new InvalidArgumentException('BPMN must contain exactly one process.');
        }
        $ids = [];
        foreach ($xpath->query('//@id') as $attribute) {
            if (isset($ids[$attribute->value]) || $attribute->value === '') {
                throw new InvalidArgumentException('BPMN IDs must be non-empty and unique.');
            }
            $ids[$attribute->value] = true;
        }

        $objects = $graphics = $edges = [];
        $process = $processes->item(0);
        foreach ($process->childNodes as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }
            $type = $element->localName;
            if ($element->namespaceURI !== self::BPMN) {
                throw new InvalidArgumentException('Unsupported BPMN namespace.');
            }
            $id = $element->getAttribute('id');
            if ($id === '') {
                throw new InvalidArgumentException('BPMN elements require an ID.');
            }
            if (isset(self::TYPES[$type])) {
                $objects[$id] = ['type' => self::TYPES[$type], 'name' => $element->getAttribute('name')];
                if ($type !== 'dataObject') {
                    $graphics[$id] = ['type' => $type, 'object' => $id];
                    if ($type === 'subProcess') {
                        $graphics[$id]['isExpanded'] = false;
                    }
                }
            } elseif ($type === 'dataObjectReference') {
                $graphics[$id] = ['type' => $type, 'object' => $element->getAttribute('dataObjectRef')];
            } elseif (in_array($type, self::ARTIFACTS, true)) {
                $graphics[$id] = ['type' => $type, 'name' => $element->getAttribute('name')];
                if ($type === 'textAnnotation') {
                    $graphics[$id]['text'] = $xpath->evaluate('string(b:text)', $element);
                }
            } elseif (in_array($type, ['sequenceFlow', 'association'], true)) {
                $edges[$id] = ['source' => $element->getAttribute('sourceRef'), 'target' => $element->getAttribute('targetRef')];
                if ($element->hasAttribute('name')) {
                    $edges[$id]['name'] = $element->getAttribute('name');
                }
            } else {
                throw new InvalidArgumentException("Unsupported BPMN element: {$type}.");
            }
            if (in_array($type, ['task', 'subProcess'], true)) {
                foreach ($xpath->query('b:dataInputAssociation | b:dataOutputAssociation', $element) as $association) {
                    $input = $association->localName === 'dataInputAssociation';
                    $refs = $xpath->query($input ? 'b:sourceRef' : 'b:targetRef', $association);
                    if ($refs->length !== 1) {
                        throw new InvalidArgumentException('Data associations require exactly one information reference.');
                    }
                    $ref = trim($refs->item(0)->textContent);
                    $edges[$association->getAttribute('id')] = [
                        'source' => $input ? $ref : $id,
                        'target' => $input ? $id : $ref,
                    ];
                    foreach ($association->childNodes as $child) {
                        if ($child instanceof DOMElement && ($child->namespaceURI !== self::BPMN
                            || ! in_array($child->localName, ['sourceRef', 'targetRef'], true))) {
                            throw new InvalidArgumentException("Unsupported data association element: {$child->localName}.");
                        }
                    }
                }
            }
            $allowedChildren = match ($type) {
                'task', 'subProcess' => ['incoming', 'outgoing', 'property', 'dataInputAssociation', 'dataOutputAssociation'],
                'startEvent', 'endEvent', 'exclusiveGateway' => ['incoming', 'outgoing'],
                'textAnnotation' => ['text'],
                default => [],
            };
            foreach ($element->childNodes as $child) {
                if ($child instanceof DOMElement && ($child->namespaceURI !== self::BPMN
                    || ! in_array($child->localName, $allowedChildren, true))) {
                    throw new InvalidArgumentException("Unsupported {$type} element: {$child->localName}.");
                }
            }
        }
        foreach ($xpath->query('b:dataObjectReference', $process) as $reference) {
            $object = $reference->getAttribute('dataObjectRef');
            if (($objects[$object]['type'] ?? null) !== 'InformationType') {
                throw new InvalidArgumentException('Unknown dataObjectRef: '.$object);
            }
            if ($objects[$object]['name'] === '') {
                $objects[$object]['name'] = $reference->getAttribute('name');
            }
        }
        foreach ($xpath->query('b:dataObjectReference', $process) as $reference) {
            $object = $reference->getAttribute('dataObjectRef');
            if ($reference->getAttribute('name') !== $objects[$object]['name']) {
                $graphics[$reference->getAttribute('id')]['name'] = $reference->getAttribute('name');
            }
        }
        $mainPlane = null;
        foreach ($xpath->query('/b:definitions/bd:BPMNDiagram') as $diagram) {
            $planes = $xpath->query('bd:BPMNPlane', $diagram);
            if ($planes->length !== 1) {
                throw new InvalidArgumentException('Each BPMN diagram must contain exactly one plane.');
            }
            $plane = $planes->item(0);
            $reference = $plane->getAttribute('bpmnElement');
            if ($reference === $process->getAttribute('id')) {
                if ($mainPlane !== null) {
                    throw new InvalidArgumentException('Multiple BPMN diagrams are not supported.');
                }
                $mainPlane = $plane;
            } elseif (($graphics[$reference]['type'] ?? null) === 'subProcess') {
                foreach ($plane->childNodes as $child) {
                    if ($child instanceof DOMElement || ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) !== '')) {
                        throw new InvalidArgumentException('Subprocess BPMN planes must be empty: '.$reference);
                    }
                }
            } else {
                throw new InvalidArgumentException('BPMN plane must reference the process or a subprocess link.');
            }
        }
        if ($mainPlane === null) {
            throw new InvalidArgumentException('BPMN must contain a diagram for the process.');
        }
        $diagramElements = [];
        foreach ($xpath->query('bd:BPMNShape | bd:BPMNEdge', $mainPlane) as $shape) {
            $id = $shape->getAttribute('bpmnElement');
            if (! isset($graphics[$id]) && ! isset($edges[$id])) {
                throw new InvalidArgumentException('Unknown diagram element: '.$id);
            }
            if (isset($diagramElements[$id]) || (($shape->localName === 'BPMNShape') !== isset($graphics[$id]))) {
                throw new InvalidArgumentException('Invalid or duplicate diagram element: '.$id);
            }
            $diagramElements[$id] = true;
            $entry = $graphics[$id] ?? $edges[$id];
            if ($shape->localName === 'BPMNShape') {
                $entry += self::readBounds($xpath, $shape, 'dc:Bounds', '');
                if ($shape->hasAttribute('isMarkerVisible')) {
                    $entry['isMarkerVisible'] = $shape->getAttribute('isMarkerVisible') === 'true';
                }
                if ($shape->hasAttribute('isExpanded')) {
                    $entry['isExpanded'] = $shape->getAttribute('isExpanded') === 'true';
                }
            } else {
                $entry['waypoints'] = [];
                foreach ($xpath->query('di:waypoint', $shape) as $point) {
                    $entry['waypoints'][] = [
                        'x' => self::number($point->getAttribute('x')),
                        'y' => self::number($point->getAttribute('y')),
                    ];
                }
            }
            $entry += self::readBounds($xpath, $shape, 'bd:BPMNLabel/dc:Bounds', 'label');
            if (isset($graphics[$id])) {
                $graphics[$id] = $entry;
            } else {
                $edges[$id] = $entry;
            }
        }
        $graphics += $edges;
        self::validateGraphics($objects, $graphics);

        return self::encodeChartData([
            'objects' => $objects,
            'relations' => array_values(array_map(
                fn (array $path) => ['source' => $path['source'], 'target' => $path['target']],
                self::paths($graphics),
            )),
            'graphics' => $graphics,
        ], $processId);
    }

    /**
     * Returns chart_data with BPMN-style IDs and every object's current name resolved.
     */
    public static function resolve(array $data): array
    {
        foreach (['objects', 'relations', 'graphics'] as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                throw new InvalidArgumentException("chart_data.{$key} must be an array.");
            }
        }

        return self::decodeChartData($data);
    }

    public static function toXml(array $data): string
    {
        $data = self::resolve($data);
        $objects = $data['objects'];
        $graphics = $data['graphics'];
        self::validateGraphics($objects, $graphics);
        $wanted = [];
        foreach ($data['relations'] as $relation) {
            if (! is_array($relation) || ! is_string($relation['source'] ?? null) || ! is_string($relation['target'] ?? null)
                || ! isset($objects[$relation['source']], $objects[$relation['target']])) {
                throw new InvalidArgumentException('Relations must reference existing objects.');
            }
            self::connectionType($objects[$relation['source']]['type'], $objects[$relation['target']]['type']);
            $wanted[self::pair($relation['source'], $relation['target'])] = $relation;
        }
        $paths = self::paths($graphics);
        $keep = [];
        foreach ($paths as $key => $path) {
            if (isset($wanted[$key])) {
                $keep += $path['edges'];
            }
        }
        $sameRelations = array_diff_key($paths, $wanted) === [] && array_diff_key($wanted, $paths) === [];
        foreach ($graphics as $id => $entry) {
            if (isset($entry['source']) && ! $sameRelations && ! isset($keep[$id])) {
                $source = $graphics[$entry['source']];
                $target = $graphics[$entry['target']];
                if (! in_array($source['type'], ['startEvent', 'endEvent', 'textAnnotation'], true)
                    && ! in_array($target['type'], ['startEvent', 'endEvent', 'textAnnotation'], true)) {
                    unset($graphics[$id]);
                }
            }
        }
        $remaining = self::paths($graphics);
        // A shared merge/split gateway can imply unwanted cross-product relations after pruning.
        if (array_diff_key($remaining, $wanted) !== []) {
            foreach ($remaining as $path) {
                foreach ($path['edges'] as $id => $_) {
                    unset($graphics[$id]);
                }
            }
            $remaining = self::paths($graphics);
        }
        $used = array_fill_keys(array_merge(array_keys($objects), array_keys($graphics)), true);
        foreach ($wanted as $key => $relation) {
            if (isset($remaining[$key])) {
                continue;
            }
            $ends = [];
            foreach (['source', 'target'] as $end) {
                $objectId = $relation[$end];
                foreach ($graphics as $id => $entry) {
                    if (($entry['object'] ?? null) === $objectId) {
                        $ends[$end] = $id;
                        break;
                    }
                }
                if (! isset($ends[$end])) {
                    throw new InvalidArgumentException('Related objects require a graphical node: '.$objectId);
                }
            }
            $graphics[self::newId('Connection', $used)] = $ends;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $definitions = $document->createElementNS(self::BPMN, 'bpmn:definitions');
        $definitions->setAttribute('targetNamespace', 'http://bpmn.io/schema/bpmn');
        $document->appendChild($definitions);
        $processId = self::newId('Process', $used);
        $process = self::element($definitions, self::BPMN, 'bpmn:process', ['id' => $processId, 'isExecutable' => 'false']);
        $nodes = [];
        foreach ($objects as $id => $object) {
            if ($object['type'] === 'InformationType') {
                self::element($process, self::BPMN, 'bpmn:dataObject', ['id' => $id, 'name' => $object['name']]);
            }
        }
        foreach ($graphics as $id => $entry) {
            if (isset($entry['source'])) {
                continue;
            }
            $attributes = ['id' => $id];
            if (isset($entry['object'])) {
                $attributes['name'] = $entry['type'] === 'dataObjectReference'
                    ? ($entry['name'] ?? $objects[$entry['object']]['name'])
                    : $objects[$entry['object']]['name'];
                if ($entry['type'] === 'dataObjectReference') {
                    $attributes['dataObjectRef'] = $entry['object'];
                }
            } elseif ($entry['type'] !== 'textAnnotation') {
                $attributes['name'] = $entry['name'] ?? '';
            }
            $nodes[$id] = self::element($process, self::BPMN, 'bpmn:'.$entry['type'], $attributes);
            if ($entry['type'] === 'textAnnotation') {
                self::element($nodes[$id], self::BPMN, 'bpmn:text')->appendChild($document->createTextNode($entry['text'] ?? ''));
            }
        }
        $properties = [];
        foreach ($nodes as $nodeId => $node) {
            foreach (['target' => 'incoming', 'source' => 'outgoing'] as $end => $tag) {
                foreach ($graphics as $id => $entry) {
                    if (($entry[$end] ?? null) === $nodeId
                        && self::connectionType($graphics[$entry['source']]['type'], $graphics[$entry['target']]['type']) === 'sequenceFlow') {
                        self::element($node, self::BPMN, 'bpmn:'.$tag)->textContent = $id;
                    }
                }
            }
            foreach ($graphics as $entry) {
                if (($entry['target'] ?? null) === $nodeId
                    && self::connectionType($graphics[$entry['source']]['type'], $graphics[$nodeId]['type']) === 'dataInputAssociation') {
                    $properties[$nodeId] = self::newId('Property', $used);
                    self::element($node, self::BPMN, 'bpmn:property', ['id' => $properties[$nodeId], 'name' => '__targetRef_placeholder']);
                    break;
                }
            }
        }
        foreach ($graphics as $id => $entry) {
            if (! isset($entry['source'])) {
                continue;
            }
            $source = $entry['source'];
            $target = $entry['target'];
            $type = self::connectionType($graphics[$source]['type'], $graphics[$target]['type']);
            if ($type === 'dataInputAssociation' || $type === 'dataOutputAssociation') {
                $input = $type === 'dataInputAssociation';
                $task = $nodes[$input ? $target : $source];
                $association = self::element($task, self::BPMN, 'bpmn:'.$type, ['id' => $id]);
                if ($input) {
                    self::element($association, self::BPMN, 'bpmn:sourceRef')->textContent = $source;
                    self::element($association, self::BPMN, 'bpmn:targetRef')->textContent = $properties[$target];
                } else {
                    self::element($association, self::BPMN, 'bpmn:targetRef')->textContent = $target;
                }
            } else {
                $attributes = ['id' => $id, 'sourceRef' => $source, 'targetRef' => $target];
                if (isset($entry['name'])) {
                    $attributes['name'] = $entry['name'];
                }
                self::element($process, self::BPMN, 'bpmn:'.$type, $attributes);
            }
        }
        $diagram = self::element($definitions, self::BPMNDI, 'bpmndi:BPMNDiagram', ['id' => self::newId('Diagram', $used)]);
        $plane = self::element($diagram, self::BPMNDI, 'bpmndi:BPMNPlane', ['id' => self::newId('Plane', $used), 'bpmnElement' => $processId]);
        foreach ($graphics as $id => $entry) {
            $edge = isset($entry['source']);
            $shape = self::element($plane, self::BPMNDI, $edge ? 'bpmndi:BPMNEdge' : 'bpmndi:BPMNShape', [
                'id' => self::newId('DI_'.$id, $used), 'bpmnElement' => $id,
            ]);
            if ($edge) {
                $points = $entry['waypoints'] ?? null;
                if ($points === null) {
                    $points = [];
                    foreach (['source', 'target'] as $end) {
                        $node = $graphics[$entry[$end]];
                        $points[] = ['x' => $node['x'] + $node['w'] / 2, 'y' => $node['y'] + $node['h'] / 2];
                    }
                }
                foreach ($points as $point) {
                    self::element($shape, self::DI, 'di:waypoint', $point);
                }
            } else {
                self::writeBounds($shape, $entry, '');
                if (isset($entry['isMarkerVisible'])) {
                    $shape->setAttribute('isMarkerVisible', $entry['isMarkerVisible'] ? 'true' : 'false');
                }
                if ($entry['type'] === 'subProcess') {
                    $shape->setAttribute('isExpanded', ($entry['isExpanded'] ?? false) ? 'true' : 'false');
                }
            }
            if (isset($entry['labelX'])) {
                self::writeBounds(self::element($shape, self::BPMNDI, 'bpmndi:BPMNLabel'), $entry, 'label');
            }
        }

        return $document->saveXML();
    }

    private static function encodeChartData(array $data, ?int $processId): array
    {
        $objects = [];
        $ids = [];
        $nextId = 1;
        $lookups = [];
        foreach ($data['objects'] as $id => $object) {
            $chartId = self::chartIdFor((string) $id, $ids, $nextId);
            $databaseId = self::findEntityId($object['type'], $object['name'], $processId, $lookups);
            $objects[$chartId] = ['type' => $object['type']];
            if ($databaseId === null) {
                $objects[$chartId]['name'] = $object['name'];
            } else {
                $objects[$chartId]['id'] = $databaseId;
            }
        }

        $graphics = [];
        foreach ($data['graphics'] as $id => $entry) {
            $chartId = self::chartIdFor((string) $id, $ids, $nextId);
            if (isset($entry['object'])) {
                $entry['object'] = self::chartIdFor((string) $entry['object'], $ids, $nextId);
            }
            foreach (['source', 'target'] as $reference) {
                if (isset($entry[$reference])) {
                    $entry[$reference] = self::chartIdFor((string) $entry[$reference], $ids, $nextId);
                }
            }
            $graphics[$chartId] = $entry;
        }

        $relations = [];
        foreach ($data['relations'] as $relation) {
            $relations[] = [
                'source' => self::chartIdFor((string) $relation['source'], $ids, $nextId),
                'target' => self::chartIdFor((string) $relation['target'], $ids, $nextId),
            ];
        }

        return ['objects' => $objects, 'relations' => $relations, 'graphics' => $graphics];
    }

    private static function decodeChartData(array $data): array
    {
        $objects = [];
        $ids = [];
        $idsByType = [];
        foreach ($data['objects'] as $id => $object) {
            $chartId = self::validateChartId($id);
            $xmlId = self::bpmnId($chartId, $ids);
            $type = $object['type'] ?? null;
            if (! is_string($type) || ! in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException('Invalid chart object: '.$chartId);
            }
            if (isset($object['id'])) {
                $databaseId = filter_var($object['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($databaseId === false) {
                    throw new InvalidArgumentException('Invalid database object ID: '.$chartId);
                }
                $idsByType[$type][] = $databaseId;
                $objects[$xmlId] = ['type' => $type, 'database_id' => $databaseId];
            } elseif (is_string($object['name'] ?? null)) {
                $objects[$xmlId] = ['type' => $type, 'name' => $object['name']];
            } else {
                throw new InvalidArgumentException('Chart objects require a database ID or a name: '.$chartId);
            }
        }

        $names = [];
        foreach ($idsByType as $type => $databaseIds) {
            $class = self::modelForType($type);
            $records = $class::query()->whereIn('id', array_unique($databaseIds))->get(['id', 'name']);
            foreach ($records as $record) {
                $names[$type][(int) $record->id] = $record->name;
            }
        }
        foreach ($objects as $xmlId => &$object) {
            if (isset($object['database_id'])) {
                $name = $names[$object['type']][$object['database_id']] ?? null;
                if (! is_string($name)) {
                    throw new InvalidArgumentException('Unknown '.$object['type'].' database ID: '.$object['database_id']);
                }
                $object['name'] = $name;
                unset($object['database_id']);
            }
        }
        unset($object);

        $graphics = [];
        foreach ($data['graphics'] as $id => $entry) {
            $xmlId = self::bpmnId(self::validateChartId($id), $ids);
            if (isset($entry['object'])) {
                $entry['object'] = self::bpmnId(self::validateChartId($entry['object']), $ids);
            }
            foreach (['source', 'target'] as $reference) {
                if (isset($entry[$reference])) {
                    $entry[$reference] = self::bpmnId(self::validateChartId($entry[$reference]), $ids);
                }
            }
            $graphics[$xmlId] = $entry;
        }
        $relations = [];
        foreach ($data['relations'] as $relation) {
            if (! is_array($relation)) {
                throw new InvalidArgumentException('Relations must be arrays.');
            }
            $relations[] = [
                'source' => self::bpmnId(self::validateChartId($relation['source'] ?? null), $ids),
                'target' => self::bpmnId(self::validateChartId($relation['target'] ?? null), $ids),
            ];
        }

        return ['objects' => $objects, 'relations' => $relations, 'graphics' => $graphics];
    }

    private static function findEntityId(string $type, string $name, ?int $processId, array &$lookups): ?int
    {
        $normalized = self::normalizeName($name);
        if ($normalized === '') {
            return null;
        }
        if (! array_key_exists($type, $lookups)) {
            $class = self::modelForType($type);
            $query = $class::query();
            if ($type === 'ProcessActivity' && $processId !== null) {
                $query->where('process_id', $processId);
            }
            $matches = [];
            foreach ($query->get(['id', 'name']) as $record) {
                $key = self::normalizeName($record->name);
                if ($key !== '') {
                    $matches[$key][] = (int) $record->id;
                }
            }
            $lookups[$type] = $matches;
        }
        $matchingIds = $lookups[$type][$normalized] ?? [];

        return count($matchingIds) === 1 ? $matchingIds[0] : null;
    }

    private static function modelForType(string $type): string
    {
        return match ($type) {
            'Process' => Process::class,
            'ProcessActivity' => ProcessActivity::class,
            'InformationType' => InformationType::class,
            'Asset' => Asset::class,
            default => throw new InvalidArgumentException('Unsupported chart object type: '.$type),
        };
    }

    private static function normalizeName(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}]/u', '', $name);

        return $normalized ?? '';
    }

    private static function chartIdFor(string $id, array &$ids, int &$nextId): int
    {
        if (array_key_exists($id, $ids)) {
            return $ids[$id];
        }
        if (preg_match('/^Chart_([1-9]\d*)$/D', $id, $matches)) {
            $candidate = filter_var($matches[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($candidate !== false && ! isset($ids['#'.$candidate])) {
                $ids[$id] = $candidate;
                $ids['#'.$candidate] = true;

                return $candidate;
            }
        }

        while (isset($ids['#'.$nextId])) {
            $nextId++;
        }
        $ids[$id] = $nextId;
        $ids['#'.$nextId] = true;

        return $ids[$id];
    }

    private static function validateChartId(mixed $id): int
    {
        if (is_string($id) && ctype_digit($id)) {
            $normalized = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($normalized === false || (string) $normalized !== $id) {
                throw new InvalidArgumentException('Chart IDs must be positive integers.');
            }
            $id = $normalized;
        }
        if (! is_int($id) || $id < 1) {
            throw new InvalidArgumentException('Chart IDs must be positive integers.');
        }

        return $id;
    }

    private static function bpmnId(int $id, array &$ids): string
    {
        $xmlId = 'Chart_'.$id;
        if (isset($ids[$xmlId])) {
            return $xmlId;
        }
        $ids[$xmlId] = true;

        return $xmlId;
    }

    private static function paths(array $graphics): array
    {
        $outgoing = $paths = [];
        foreach ($graphics as $id => $entry) {
            if (isset($entry['source'])) {
                $outgoing[$entry['source']][$id] = $entry['target'];
            }
        }
        foreach ($graphics as $id => $node) {
            if (! isset($node['object'])) {
                continue;
            }
            $queue = [(string) $id];
            $visited = $incoming = $targets = [];
            for ($index = 0; $index < count($queue); $index++) {
                $current = $queue[$index];
                foreach ($outgoing[$current] ?? [] as $edge => $target) {
                    $next = $graphics[$target];
                    if (! isset($next['object']) && $next['type'] !== 'exclusiveGateway') {
                        continue;
                    }
                    $incoming[$target][$edge] = $current;
                    if (isset($next['object'])) {
                        $targets[$next['object']][$target] = true;
                    } elseif (! isset($visited[$target])) {
                        $visited[$target] = true;
                        $queue[] = $target;
                    }
                }
            }
            foreach ($targets as $object => $occurrences) {
                $key = self::pair($node['object'], $object);
                $paths[$key] ??= ['source' => $node['object'], 'target' => $object, 'edges' => []];
                $queue = array_keys($occurrences);
                $visited = $occurrences;
                for ($index = 0; $index < count($queue); $index++) {
                    foreach ($incoming[$queue[$index]] ?? [] as $edge => $parent) {
                        $paths[$key]['edges'][$edge] = true;
                        if ($parent !== $id && ! isset($visited[$parent])) {
                            $visited[$parent] = true;
                            $queue[] = $parent;
                        }
                    }
                }
            }
        }

        return $paths;
    }

    private static function pair(string $source, string $target): string
    {
        return json_encode([$source, $target], JSON_THROW_ON_ERROR);
    }

    private static function connectionType(string $source, string $target): string
    {
        $source = self::TYPES[$source] ?? ($source === 'dataObjectReference' ? 'InformationType' : $source);
        $target = self::TYPES[$target] ?? ($target === 'dataObjectReference' ? 'InformationType' : $target);
        if ($source === 'textAnnotation' || $target === 'textAnnotation') {
            return 'association';
        }
        $flow = ['ProcessActivity', 'Process', 'exclusiveGateway', 'startEvent', 'endEvent'];
        if (in_array($source, $flow, true) && in_array($target, $flow, true)) {
            return 'sequenceFlow';
        }

        return match ([$source, $target]) {
            ['InformationType', 'ProcessActivity'], ['InformationType', 'Process'] => 'dataInputAssociation',
            ['ProcessActivity', 'InformationType'], ['Process', 'InformationType'] => 'dataOutputAssociation',
            ['InformationType', 'Asset'], ['Asset', 'InformationType'] => 'association',
            default => throw new InvalidArgumentException("Unsupported connection: {$source} -> {$target}."),
        };
    }

    private static function validateGraphics(array $objects, array $graphics): void
    {
        foreach ($objects as $id => $object) {
            self::validateId($id);
            if (! is_array($object) || ! in_array($object['type'] ?? null, self::TYPES, true) || ! is_string($object['name'] ?? null)) {
                throw new InvalidArgumentException('Invalid chart object: '.$id);
            }
        }
        foreach ($graphics as $id => $entry) {
            self::validateId($id);
            if (! is_array($entry)) {
                throw new InvalidArgumentException('Invalid graphics entry: '.$id);
            }
            if (isset($entry['source'])) {
                if (isset($objects[$id]) || ! is_string($entry['source']) || ! is_string($entry['target'] ?? null)
                    || ! is_string($graphics[$entry['source']]['type'] ?? null)
                    || ! is_string($graphics[$entry['target']]['type'] ?? null)) {
                    throw new InvalidArgumentException('Invalid graphical connection: '.$id);
                }
                self::connectionType($graphics[$entry['source']]['type'], $graphics[$entry['target']]['type']);
                if (isset($entry['waypoints']) && (! is_array($entry['waypoints']) || ! array_is_list($entry['waypoints']) || count($entry['waypoints']) < 2)) {
                    throw new InvalidArgumentException('Graphical connections require at least two waypoints.');
                }
                foreach ($entry['waypoints'] ?? [] as $point) {
                    if (! is_array($point)) {
                        throw new InvalidArgumentException('Invalid waypoint.');
                    }
                    self::number($point['x'] ?? null);
                    self::number($point['y'] ?? null);
                }
            } else {
                $type = $entry['type'] ?? '';
                if (! is_string($type) || (isset($objects[$id]) && ! in_array($type, ['task', 'subProcess', 'dataStoreReference'], true))) {
                    throw new InvalidArgumentException('Invalid graphical node ID or type: '.$id);
                }
                $objectType = $type === 'dataObjectReference' ? 'InformationType' : (self::TYPES[$type] ?? null);
                if ($objectType !== null && $type !== 'dataObject') {
                    if (! is_string($entry['object'] ?? null) || ($objects[$entry['object']]['type'] ?? null) !== $objectType
                        || ($type !== 'dataObjectReference' && $entry['object'] !== $id)) {
                        throw new InvalidArgumentException('Invalid graphical object reference: '.$id);
                    }
                } elseif (! in_array($type, self::ARTIFACTS, true) || isset($objects[$id]) || isset($entry['object'])) {
                    throw new InvalidArgumentException('Invalid graphical node: '.$id);
                }
                foreach (['x', 'y', 'w', 'h'] as $coordinate) {
                    self::number($entry[$coordinate] ?? null);
                }
                if ($entry['w'] <= 0 || $entry['h'] <= 0) {
                    throw new InvalidArgumentException('Graphical nodes require positive dimensions.');
                }
                if (isset($entry['text']) && ! is_string($entry['text'])) {
                    throw new InvalidArgumentException('Annotation text must be a string.');
                }
                if (isset($entry['isMarkerVisible']) && ! is_bool($entry['isMarkerVisible'])) {
                    throw new InvalidArgumentException('isMarkerVisible must be a boolean.');
                }
                if (isset($entry['isExpanded']) && ($type !== 'subProcess' || ! is_bool($entry['isExpanded']))) {
                    throw new InvalidArgumentException('isExpanded must be a boolean on a subProcess.');
                }
            }
            $labelKeys = ['labelX', 'labelY', 'labelW', 'labelH'];
            foreach ($labelKeys as $coordinate) {
                if (array_intersect($labelKeys, array_keys($entry)) !== []) {
                    self::number($entry[$coordinate] ?? null);
                }
            }
            if (isset($entry['name']) && ! is_string($entry['name'])) {
                throw new InvalidArgumentException('Graphical names must be strings.');
            }
        }
        foreach ($objects as $id => $object) {
            if ($object['type'] !== 'InformationType' && ! isset($graphics[$id]['object'])) {
                throw new InvalidArgumentException('Missing graphical node for object: '.$id);
            }
        }
    }

    private static function validateId(string|int $id): void
    {
        if (! is_string($id) || ! preg_match('/^[\p{L}_][\p{L}\p{N}_.-]*$/uD', $id)) {
            throw new InvalidArgumentException('Invalid BPMN ID: '.$id);
        }
    }

    private static function number(mixed $value): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            throw new InvalidArgumentException('BPMN coordinates must be finite numbers.');
        }

        return (float) $value;
    }

    private static function readBounds(DOMXPath $xpath, DOMElement $element, string $query, string $prefix): array
    {
        $bounds = $xpath->query($query, $element)->item(0);
        $result = [];
        if ($bounds instanceof DOMElement) {
            foreach (['x' => 'x', 'y' => 'y', 'w' => 'width', 'h' => 'height'] as $key => $attribute) {
                $result[$prefix === '' ? $key : $prefix.strtoupper($key)] = self::number($bounds->getAttribute($attribute));
            }
        }

        return $result;
    }

    private static function writeBounds(DOMElement $parent, array $entry, string $prefix): void
    {
        $attributes = [];
        foreach (['x' => 'x', 'y' => 'y', 'w' => 'width', 'h' => 'height'] as $key => $attribute) {
            $attributes[$attribute] = $entry[$prefix === '' ? $key : $prefix.strtoupper($key)];
        }
        self::element($parent, self::DC, 'dc:Bounds', $attributes);
    }

    private static function element(DOMElement $parent, string $namespace, string $name, array $attributes = []): DOMElement
    {
        $element = $parent->ownerDocument->createElementNS($namespace, $name);
        foreach ($attributes as $key => $value) {
            $element->setAttribute($key, (string) $value);
        }
        $parent->appendChild($element);

        return $element;
    }

    private static function newId(string $prefix, array &$used): string
    {
        $id = $prefix;
        for ($suffix = 1; isset($used[$id]); $suffix++) {
            $id = $prefix.'_'.$suffix;
        }
        $used[$id] = true;

        return $id;
    }
}
