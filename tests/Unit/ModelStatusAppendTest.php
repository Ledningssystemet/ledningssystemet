<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Activity;
use App\Models\ActivityFlow;
use App\Models\Agreement;
use App\Models\Asset;
use App\Models\Chemical;
use App\Models\Control;
use App\Models\ControlAction;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Finding;
use App\Models\Incident;
use App\Models\InformationType;
use App\Models\LibraryDocument;
use App\Models\Objective;
use App\Models\Process;
use App\Models\ProcessActivity;
use App\Models\ProcessPerformanceMetric;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementSource;
use App\Models\Risk;
use App\Models\Site;
use App\Models\Supplier;
use App\Models\SustainabilityAspect;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelStatusAppendTest extends TestCase
{
    /**
     * @var array{level: string, explanation: string}
     */
    private const DEFAULT_STATUS = [
        'level' => 'unknown',
        'explanation' => '',
    ];

    #[DataProvider('statusModelProvider')]
    public function test_target_models_append_default_status_when_serialized(string $modelClass): void
    {
        $model = new $modelClass();

        self::assertInstanceOf(Model::class, $model);
        self::assertContains('status', $model->getAppends(), "{$modelClass} must append status by default.");
        self::assertSame(self::DEFAULT_STATUS, $model->status, "{$modelClass} must expose the default status array.");

        $model->setAppends(['status']);

        $arrayData = $model->toArray();
        $jsonData = json_decode($model->toJson(), true);

        self::assertIsArray($jsonData, "{$modelClass}::toJson() must decode to an array payload.");
        self::assertSame(self::DEFAULT_STATUS, $arrayData['status'] ?? null, "{$modelClass} must serialize status in toArray().");
        self::assertSame(self::DEFAULT_STATUS, $jsonData['status'] ?? null, "{$modelClass} must serialize status in toJson().");
    }

    /**
     * @return array<int, array{0: class-string<Model>}>
     */
    public static function statusModelProvider(): array
    {
        return [
            [Activity::class],
            [ActivityFlow::class],
            [Agreement::class],
            [Asset::class],
            [Chemical::class],
            [Control::class],
            [ControlAction::class],
            [Customer::class],
            [Department::class],
            [Finding::class],
            [Incident::class],
            [InformationType::class],
            [LibraryDocument::class],
            [Objective::class],
            [Process::class],
            [ProcessActivity::class],
            [ProcessPerformanceMetric::class],
            [Project::class],
            [Requirement::class],
            [RequirementSource::class],
            [Risk::class],
            [Site::class],
            [Supplier::class],
            [SustainabilityAspect::class],
            [User::class],
        ];
    }
}

