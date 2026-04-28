<?php

namespace App\Services\Bpmn;

use DOMDocument;
use DOMElement;
use DOMXPath;

class BpmnTextContentValidator
{
    public const ERROR_KEY = 'pages.process_editor.validation.invalid_text_content';

    private const ALLOWED_TEXT_PATTERN = '/\A[a-zA-Z_ åäöÅÄÖ\-,.]*\z/u';

    public function hasInvalidTextInXml(string $xml): bool
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (! $dom->loadXML($xml, LIBXML_NONET)) {
                return false;
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $this->hasInvalidTextInXPath(new DOMXPath($dom));
    }

    public function hasInvalidTextInXPath(DOMXPath $xpath): bool
    {
        foreach ($this->extractNameValues($xpath) as $value) {
            if (! $this->isValidTextValue($value)) {
                return true;
            }
        }

        foreach ($this->extractTextAnnotationValues($xpath) as $value) {
            if (! $this->isValidTextValue($value)) {
                return true;
            }
        }

        return false;
    }

    public function isValidTextValue(string $value): bool
    {
        return preg_match(self::ALLOWED_TEXT_PATTERN, trim($value)) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function extractNameValues(DOMXPath $xpath): array
    {
        $values = [];
        $nodes = $xpath->query('//*[@name]');

        if ($nodes === false) {
            return $values;
        }

        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $values[] = (string) $node->getAttribute('name');
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    private function extractTextAnnotationValues(DOMXPath $xpath): array
    {
        $values = [];
        $nodes = $xpath->query("//*[local-name()='textAnnotation']/*[local-name()='text']");

        if ($nodes === false) {
            return $values;
        }

        foreach ($nodes as $node) {
            $values[] = $node->textContent;
        }

        return $values;
    }
}


