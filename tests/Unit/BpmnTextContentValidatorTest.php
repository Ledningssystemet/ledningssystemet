<?php

namespace Tests\Unit;

use App\Services\Bpmn\BpmnTextContentValidator;
use Tests\TestCase;

class BpmnTextContentValidatorTest extends TestCase
{
    public function test_it_allows_digits_in_bpmn_name_and_text_values(): void
    {
        $validator = new BpmnTextContentValidator();

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
    <bpmn:task id="Task_1" name="Review 2026" />
    <bpmn:endEvent id="EndEvent_1" />
    <bpmn:textAnnotation id="Text_1">
      <bpmn:text>Phase 2</bpmn:text>
    </bpmn:textAnnotation>
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_1" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_1" targetRef="EndEvent_1" />
    <bpmn:association id="Assoc_1" sourceRef="Text_1" targetRef="Task_1" />
  </bpmn:process>
</bpmn:definitions>
XML;

        $this->assertFalse($validator->hasInvalidTextInXml($xml));
    }
}

