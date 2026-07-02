<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\ProcessPerformanceMetricReportPolicy;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ProcessPerformanceMetricReportPolicy::class)]
class ProcessPerformanceMetricReportPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_view_and_create_follow_process_metric_claims(): void
    {
        $policy = new ProcessPerformanceMetricReportPolicy();

        $reader = Mockery::mock(User::class);
        $reader->shouldReceive('haveAnyAccessRights')
            ->with(['processmetrics.read', 'processmetrics.edit'])
            ->andReturn(true);
        $reader->shouldReceive('haveAnyAccessRights')
            ->with(['processmetrics.edit'])
            ->andReturn(false);

        $editor = Mockery::mock(User::class);
        $editor->shouldReceive('haveAnyAccessRights')
            ->with(['processmetrics.read', 'processmetrics.edit'])
            ->andReturn(true);
        $editor->shouldReceive('haveAnyAccessRights')
            ->with(['processmetrics.edit'])
            ->andReturn(true);

        $denied = Mockery::mock(User::class);
        $denied->shouldReceive('haveAnyAccessRights')
            ->with(['processmetrics.read', 'processmetrics.edit'])
            ->andReturn(false);
        $denied->shouldReceive('haveAnyAccessRights')
            ->with(['processmetrics.edit'])
            ->andReturn(false);

        $this->assertTrue($policy->viewAny($reader));
        $this->assertFalse($policy->create($reader));

        $this->assertTrue($policy->viewAny($editor));
        $this->assertTrue($policy->create($editor));
        $this->assertTrue($policy->delete($editor));

        $this->assertFalse($policy->viewAny($denied));
        $this->assertFalse($policy->create($denied));
    }
}

