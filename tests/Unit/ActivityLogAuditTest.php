<?php

namespace Tests\Unit;

use Illuminate\Foundation\Application;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Ledningssystemet\Ledningssystemet\Models\ActivityLog;
use PHPUnit\Framework\TestCase;

class ActivityLogAuditTest extends TestCase
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

    public function testHistoryTextDisplaysOldAndNewValues(): void
    {
        $activity = new ActivityLog();
        $activity->properties = [
            'action' => 'U',
            'modified' => ['Description' => ['old' => 'Old value', 'new' => 'New value']],
        ];

        $this->assertSame(
            'The item was updated, changed Description changed from Old value to New value',
            $activity->text
        );
    }

    public function testHistoryTextKeepsExistingFieldOnlyEntriesReadable(): void
    {
        $activity = new ActivityLog();
        $activity->properties = ['action' => 'U', 'modified' => ['name' => true]];

        $this->assertSame('The item was updated, changed name', $activity->text);
    }
}
