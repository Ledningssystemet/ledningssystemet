<?php

namespace Tests\Unit;

use Illuminate\Foundation\Application;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Ledningssystemet\Ledningssystemet\Models\Asset;
use Ledningssystemet\Ledningssystemet\Models\Customer;
use Ledningssystemet\Ledningssystemet\Models\InformationType;
use Ledningssystemet\Ledningssystemet\Models\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProcessRenameObjectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('translator', new Translator(new ArrayLoader(), 'en'));
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    private function chart(string $element, string $name): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL">'
            .'<bpmn:process id="p"><bpmn:'.$element.' id="e1" name="'.$name.'"/></bpmn:process></bpmn:definitions>';
    }

    private function process(?string $draft, ?string $published): Process
    {
        $process = new class extends Process {
            public int $saves = 0;

            public function save(array $options = [])
            {
                $this->saves++;

                return true;
            }
        };
        $process->bpmn = $draft;
        $process->publishedbpmn = $published;

        return $process;
    }

    public static function objectTypes(): array
    {
        return [
            'asset' => [Asset::class, 'dataStoreReference'],
            'information type' => [InformationType::class, 'dataObjectReference'],
            'process' => [Process::class, 'subProcess'],
        ];
    }

    #[DataProvider('objectTypes')]
    public function testRenamesInDraftAndPublishedCharts(string $class, string $element): void
    {
        $chart = $this->chart($element, 'Old');
        $process = $this->process($chart, $chart);
        $process->int_rename_processobject(new $class(), 'Old', 'New');

        $this->assertStringContainsString('name="New"', $process->bpmn);
        $this->assertStringContainsString('name="New"', $process->publishedbpmn);
        $this->assertSame(1, $process->saves);
    }

    #[DataProvider('objectTypes')]
    public function testRenamesInDraftOnlyChart(string $class, string $element): void
    {
        $process = $this->process($this->chart($element, 'Old'), null);
        $process->int_rename_processobject(new $class(), 'Old', 'New');

        $this->assertStringContainsString('name="New"', $process->bpmn);
        $this->assertNull($process->publishedbpmn);
    }

    public function testRenamesWhenPublishedChartLacksReference(): void
    {
        $process = $this->process($this->chart('dataStoreReference', 'Old'), $this->chart('dataStoreReference', 'Other'));
        $process->int_rename_processobject(new Asset(), 'Old', 'New');

        $this->assertStringContainsString('name="New"', $process->bpmn);
        $this->assertStringContainsString('name="Other"', $process->publishedbpmn);
    }

    public function testRenamesAssetSubclassAndLeavesOtherElementsUntouched(): void
    {
        $chart = '<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"><bpmn:process id="p">'
            .'<bpmn:dataStoreReference id="a" name="Old"/><bpmn:dataObjectReference id="b" name="Old"/></bpmn:process></bpmn:definitions>';
        $process = $this->process($chart, null);
        $process->int_rename_processobject(new class extends Asset {}, 'Old', 'New');

        $this->assertStringContainsString('<bpmn:dataStoreReference id="a" name="New"', $process->bpmn);
        $this->assertStringContainsString('<bpmn:dataObjectReference id="b" name="Old"', $process->bpmn);
    }

    public function testRenamesNameContainingApostrophe(): void
    {
        $process = $this->process($this->chart('dataStoreReference', 'O&apos;Neil'), null);
        $process->int_rename_processobject(new Asset(), "O'Neil", 'New');

        $this->assertStringContainsString('name="New"', $process->bpmn);
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNewNameIsRejectedWithoutChangingCharts(string $newName): void
    {
        $chart = $this->chart('dataStoreReference', 'Old');
        $process = $this->process($chart, $chart);

        try {
            $process->int_rename_processobject(new Asset(), 'Old', $newName);
            $this->fail('Expected exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('not a valid name', $e->getMessage());
        }

        $this->assertSame($chart, $process->bpmn);
        $this->assertSame($chart, $process->publishedbpmn);
        $this->assertSame(0, $process->saves);
    }

    public static function invalidNames(): array
    {
        return [['A<B'], ['A&B'], ['A"B'], ["A'B"], ['A>B']];
    }

    public function testMissingReferenceAndUnsupportedTypeThrowWithoutSaving(): void
    {
        $process = $this->process($this->chart('dataStoreReference', 'Other'), null);

        foreach ([new Asset(), new Customer()] as $object) {
            try {
                $process->int_rename_processobject($object, 'Old', 'New');
                $this->fail('Expected exception');
            } catch (\Exception $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $this->assertSame(0, $process->saves);
    }
}
