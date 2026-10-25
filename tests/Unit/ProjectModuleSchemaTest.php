<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Ledningssystemet\Ledningssystemet\Http\Controllers\Api\TableController;
use Ledningssystemet\Ledningssystemet\Models\Project;
use Ledningssystemet\Ledningssystemet\Models\ProjectActivity;
use Ledningssystemet\Ledningssystemet\Models\ProjectRisk;
use Ledningssystemet\Ledningssystemet\Models\ProjectWorkPackage;
use Ledningssystemet\Ledningssystemet\Models\RiskLevel;
use Ledningssystemet\Ledningssystemet\Models\User;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProjectModuleSchemaTest extends TestCase
{
    private User $user;

    private bool $administrator = true;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The SQLite PDO driver is required.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('JSON_SEARCH', static function ($value, $mode, $needle) {
            return in_array($needle, json_decode($value, true) ?? [], true) ? '$' : null;
        });
        $this->createSchema();
        $migration = require base_path('database/migrations/2026_10_24_111700_add_readonly_to_project_user.php');
        $migration->up();
        $workPackages = require base_path('database/migrations/2026_10_24_143000_create_project_work_packages.php');
        $workPackages->up();
        $this->user = Mockery::mock(User::class)->makePartial();
        $this->user->forceFill(['id' => 1, 'name' => 'Owner']);
        $this->user->shouldReceive('hasPermissionTo')->andReturn(false);
        $this->user->shouldReceive('hasAnyPermission')->andReturnUsing(fn () => $this->administrator);
        $this->user->shouldReceive('risklevel')->andReturn((new RiskLevel)->forceFill(['ordinal' => 10]));
        auth()->setUser($this->user);
        $this->request('GET');
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_risk_crud_and_reassessment_use_only_migrated_columns(): void
    {
        $risk = $this->createRisk();
        $this->assertSame('Migrated note', $risk->note);
        $this->assertSame(1, $risk->post_probability_id);
        $this->assertSame([['id' => 1, 'name' => 'Control']], $risk->risk_controls);
        foreach (['context', 'context_type', 'created_by', 'replacing_id', 'replacedby_id', 'assessmentcomment'] as $field) {
            $this->assertNotContains($field, $risk->getFillable());
            $this->assertNotContains($field, $risk->getVisible());
        }

        $risk->approve();
        $this->assertNotNull($risk->fresh()->assessed_at);
        $this->assertSame(1, Project::findOrFail(1)->riskdistribution[1][1]['count']);
        $this->assertSame('Level', Project::findOrFail(1)->maxrisklevel['name']);

        $activity = ProjectActivity::create(['name' => 'Mitigation', 'project_id' => 1, 'risks' => [$risk->id]]);
        $risk->replace();
        $this->assertNull($risk->fresh()->assessed_at);
        $this->assertSame(1, DB::table('project_risks')->count());
        $this->assertSame([$activity->id], $risk->int_activities()->pluck('project_activities.id')->all());
        $this->assertSame([1], $risk->int_controls()->pluck('controls.id')->all());
        $this->assertSame([], Project::findOrFail(1)->riskdistribution);
    }

    public function test_activity_crud_uses_project_activities_and_persists_risk_links(): void
    {
        $risk = $this->createRisk();
        $controller = new TableController;
        $this->request('POST', [
            'risks' => [$risk->id],
            'name' => 'Mitigation',
            'project_id' => 1,
            'responsible_user_id' => 1,
            'startdate' => '2026-10-24',
            'due' => '2026-12-01',
            'estimated_cost' => 1250.50,
            'estimated_hours' => 8,
        ]);
        $created = $controller->create('ProjectActivity')->getData(true);
        $this->assertSame('Mitigation', $created['name']);
        $this->assertSame([['id' => $risk->id, 'name' => $risk->name]], $created['risks']);
        $this->assertEquals(1250.50, $created['estimated_cost']);
        $this->assertSame(8, $created['estimated_hours']);
        $this->assertSame('2026-10-24', $created['startdate']);

        $otherRisk = $this->createRisk(2);
        ProjectActivity::create(['name' => 'Other project activity', 'project_id' => 2, 'risks' => [$otherRisk->id]]);
        $this->request('GET', ['filter' => ['project_id' => 1], 'sort' => 'due']);
        $rows = $controller->index('ProjectActivity')->getData(true);
        $this->assertCount(1, $rows);
        $this->assertSame($created['id'], $rows[0]['id']);

        $this->request('PATCH', ['completed_at' => '2026-10-24 11:00:00']);
        $finished = $controller->update('ProjectActivity', $created['id'])->getData(true);
        $this->assertNotNull($finished['completed_at']);
        $this->request('PATCH', ['completed_at' => null, 'risks' => []]);
        $reopened = $controller->update('ProjectActivity', $created['id'])->getData(true);
        $this->assertNull($reopened['completed_at']);
        $this->assertSame([], $reopened['risks']);
        $this->assertSame(1, Project::findOrFail(1)->int_activities()->count());

        $this->request('DELETE');
        $controller->delete('ProjectActivity', $created['id']);
        $this->assertFalse(ProjectActivity::whereKey($created['id'])->exists());
    }

    public function test_risk_api_filters_updates_and_deletes_migrated_risks(): void
    {
        $risk = $this->createRisk();
        $this->createRisk(2);
        $controller = new TableController;
        $this->request('GET', ['filter' => ['project_id' => 1]]);
        $rows = $controller->index('ProjectRisk')->getData(true);
        $this->assertCount(1, $rows);
        $this->assertSame($risk->id, $rows[0]['id']);
        $this->assertSame('Migrated note', $rows[0]['note']);

        $this->request('PATCH', ['note' => 'Updated note', 'post_probability_id' => null, 'risk_controls' => []]);
        $updated = $controller->update('ProjectRisk', $risk->id)->getData(true);
        $this->assertSame('Updated note', $updated['note']);
        $this->assertNull($updated['post_probability_id']);
        $this->assertSame([], $updated['risk_controls']);

        $this->request('DELETE');
        $controller->delete('ProjectRisk', $risk->id);
        $this->assertFalse(ProjectRisk::whereKey($risk->id)->exists());
    }

    public function test_crud_index_scopes_projects_and_risks_before_pagination(): void
    {
        $this->administrator = false;
        DB::table('project_risks')->insert([
            ['name' => 'Own risk', 'project_id' => 1, 'riskowner_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Other risk', 'project_id' => 1, 'riskowner_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->request('GET', ['fields' => ['projects' => 'id'], 'per_page' => 1]);
        $projects = (new TableController)->index('Project')->getData(true);
        $this->assertSame([1], array_column($projects['data'], 'id'));
        $this->assertSame(1, $projects['total']);

        Gate::define('index', static fn () => true);
        DB::table('risks')->insert([
            ['id' => 1, 'riskowner_id' => 1],
            ['id' => 2, 'riskowner_id' => 2],
        ]);
        $this->request('GET', ['fields' => ['risks' => 'id'], 'per_page' => 1]);
        $risks = (new TableController)->index('Risk')->getData(true);
        $this->assertSame([1], array_column($risks['data'], 'id'));
        $this->assertSame(1, $risks['total']);
    }

    public function test_personal_timeline_activity_filters_combine_projects_and_responsible_user(): void
    {
        $own = ProjectActivity::create(['name' => 'My first activity', 'project_id' => 1, 'responsible_user_id' => 1]);
        $otherProject = ProjectActivity::create(['name' => 'My second activity', 'project_id' => 2, 'responsible_user_id' => 1]);
        ProjectActivity::create(['name' => 'Someone else', 'project_id' => 1, 'responsible_user_id' => 2]);
        ProjectActivity::create(['name' => 'Unassigned', 'project_id' => 2]);
        $this->request('GET', ['filter' => ['project_id' => '1,2', 'responsible_user_id' => 1], 'sort' => 'due']);
        $rows = (new TableController)->index('ProjectActivity')->getData(true);
        $this->assertEqualsCanonicalizing([$own->id, $otherProject->id], array_column($rows, 'id'));
        $this->request('GET', ['filter' => ['project_id' => 1, 'responsible_user_id' => 1]]);
        $rows = (new TableController)->index('ProjectActivity')->getData(true);
        $this->assertSame([$own->id], array_column($rows, 'id'));
    }

    public function test_project_list_includes_responsible_names_without_fetching_user_lists(): void
    {
        Gate::define('view', static fn () => true);
        $this->request('GET', [
            'fields' => ['projects' => 'id,name,responsible_user_id,responsible_user_name'],
            'sort' => 'id',
        ]);
        $rows = (new TableController)->index('Project')->getData(true);
        $this->assertSame(['Owner', 'Other owner'], array_column($rows, 'responsible_user_name'));
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'responsible_user_id', 'responsible_user_name'],
            array_keys($rows[0])
        );

        $projects = Project::visibleTo($this->user)->get();
        $this->assertTrue($projects->every(fn (Project $project) => $project->relationLoaded('int_responsible_user')));
        $unassigned = (new Project)->forceFill(['responsible_user_id' => null]);
        $this->assertNull($unassigned->responsible_user_name);
    }

    public function test_personal_timeline_only_serializes_selected_activity_fields(): void
    {
        $activity = ProjectActivity::create(['name' => 'My activity', 'project_id' => 1, 'responsible_user_id' => 1]);
        $this->request('GET', [
            'filter' => ['project_id' => 1, 'responsible_user_id' => 1],
            'fields' => ['project_activities' => 'id,name,project_id,responsible_user_id,startdate,due,completed_at'],
        ]);
        $rows = (new TableController)->index('ProjectActivity')->getData(true);
        $this->assertSame([$activity->id], array_column($rows, 'id'));
        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'project_id', 'responsible_user_id', 'startdate', 'due', 'completed_at'],
            array_keys($rows[0])
        );
    }

    public function test_risk_links_cannot_cross_project_boundaries(): void
    {
        $otherRisk = $this->createRisk(2);
        $this->expectException(ValidationException::class);
        ProjectActivity::create(['name' => 'Invalid link', 'project_id' => 1, 'risks' => [$otherRisk->id]]);
    }

    public function test_activity_dates_costs_and_hours_are_validated_before_persistence(): void
    {
        foreach ([
            ['startdate' => '2026-12-01', 'due' => '2026-11-01'],
            ['estimated_hours' => 1.5],
            ['estimated_hours' => -1],
            ['estimated_cost' => -0.01],
            ['estimated_cost' => 100000000],
        ] as $invalid) {
            try {
                ProjectActivity::create(['name' => 'Invalid', 'project_id' => 1, ...$invalid]);
                $this->fail('Invalid project activity data must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(0, DB::table('project_activities')->count());
    }

    public function test_scheduled_reassessment_uses_each_risks_linked_project_activities(): void
    {
        $planned = $this->createRisk();
        $unplanned = $this->createRisk();
        $archived = $this->createRisk(2);
        ProjectActivity::create(['name' => 'Mitigation', 'project_id' => 1, 'risks' => [$planned->id]]);
        DB::table('project_risks')->update(['assessed_at' => now()->subDays(2)]);
        DB::table('projects')->where('id', 2)->update(['archived_at' => now()]);
        DB::table('risk_levels')->update(['reassessment_days_withplans' => 1, 'reassessment_days_withoutplans' => 0]);

        $this->artisan('ledningssystemet:risksreassess')->assertSuccessful();

        $this->assertNull($planned->fresh()->assessed_at);
        $this->assertNotNull($unplanned->fresh()->assessed_at);
        $this->assertNotNull($archived->fresh()->assessed_at);
        $this->assertSame(3, DB::table('project_risks')->count());
    }

    public function test_archiving_checks_project_activities_and_archived_activities_cannot_be_changed(): void
    {
        $activity = ProjectActivity::create(['name' => 'Pending', 'project_id' => 1]);
        $project = Project::findOrFail(1);
        try {
            $project->archive();
            $this->fail('Archiving must reject unfinished project activities.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }
        $activity->update(['completed_at' => '2026-10-24 11:00:00']);
        $project->archive();
        $this->assertNotNull($project->fresh()->archived_at);
        $this->expectException(HttpException::class);
        $activity->update(['name' => 'Cannot edit']);
    }

    public function test_project_activities_respect_project_membership_and_responsibility(): void
    {
        $own = ProjectActivity::create(['name' => 'Own project', 'project_id' => 1, 'responsible_user_id' => 2]);
        $other = ProjectActivity::create(['name' => 'Other project', 'project_id' => 2, 'responsible_user_id' => 1]);
        $this->administrator = false;
        $this->assertTrue(Gate::allows('view', $own));
        $this->assertTrue(Gate::allows('update', $own));
        $this->assertTrue(Gate::allows('delete', $own));
        $this->assertFalse(Gate::allows('view', $other));
        $this->assertFalse(Gate::allows('update', $other));

        DB::table('project_user')->insert(['project_id' => 2, 'user_id' => 1]);
        $this->assertTrue(Gate::allows('view', $other));
        $this->assertTrue(Gate::allows('update', $other));
        $this->assertFalse(Gate::allows('delete', $other));
    }

    public function test_responsible_user_manages_participants_with_readonly_permissions(): void
    {
        $this->administrator = false;
        DB::table('users')->insert(['id' => 3, 'name' => 'New member']);
        DB::table('project_user')->insert(['project_id' => 1, 'user_id' => 2]);
        $this->assertEquals(0, DB::table('project_user')->value('readonly'));
        $controller = new TableController;
        $this->request('POST', ['users' => [['id' => 2, 'readonly' => true], ['id' => 3, 'readonly' => false]]]);
        $controller->customAction('Project', 1, 'updateparticipants');
        $project = Project::findOrFail(1);
        $this->assertSame([
            ['id' => 2, 'name' => 'Other owner', 'readonly' => true],
            ['id' => 3, 'name' => 'New member', 'readonly' => false],
        ], $project->users);
        $this->assertTrue($project->access['manageparticipants']);
        $this->request('POST', ['users' => [['id' => 3, 'readonly' => true]]]);
        $controller->customAction('Project', 1, 'updateparticipants');
        $this->assertSame([['id' => 3, 'name' => 'New member', 'readonly' => true]], $project->fresh()->users);
        $this->request('POST', ['users' => []]);
        $controller->customAction('Project', 1, 'updateparticipants');
        $this->assertSame([], $project->fresh()->users);
    }

    public function test_only_responsible_user_can_change_participants(): void
    {
        $this->administrator = false;
        DB::table('project_user')->insert(['project_id' => 2, 'user_id' => 1]);
        $project = Project::findOrFail(2);
        $this->assertFalse($project->access['manageparticipants']);
        $this->request('POST', ['users' => []]);
        $this->expectException(HttpException::class);
        (new TableController)->customAction('Project', 2, 'updateparticipants');
    }

    public function test_participant_payload_is_validated_without_modifying_existing_members(): void
    {
        DB::table('project_user')->insert(['project_id' => 1, 'user_id' => 2]);
        foreach ([
            ['users' => [['id' => 9999, 'readonly' => false]]],
            ['users' => [['id' => 2, 'readonly' => 'invalid']]],
            ['users' => [['id' => 1, 'readonly' => true]]],
            ['users' => [['id' => 2, 'readonly' => false], ['id' => 2, 'readonly' => true]]],
            ['users' => [['id' => 2]]],
            [],
        ] as $data) {
            $this->request('POST', $data);
            try {
                (new TableController)->customAction('Project', 1, 'updateparticipants');
                $this->fail('Invalid participants must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
            $this->assertSame([2], Project::findOrFail(1)->int_users()->pluck('users.id')->all());
        }
    }

    public function test_readonly_members_can_read_but_not_write_even_when_assigned(): void
    {
        $risk = $this->createRisk(2);
        $activity = ProjectActivity::create(['name' => 'Assigned activity', 'project_id' => 2, 'responsible_user_id' => 1]);
        $this->administrator = false;
        DB::table('project_user')->insert(['project_id' => 2, 'user_id' => 1, 'readonly' => true]);
        $project = Project::findOrFail(2);
        $this->assertTrue(Gate::allows('view', $project));
        $this->assertFalse($project->canWrite($this->user));
        foreach ([$risk, $activity] as $model) {
            $this->assertTrue(Gate::allows('view', $model));
            $this->assertFalse(Gate::allows('update', $model));
            $this->assertFalse(Gate::allows('delete', $model));
        }
        foreach (['ProjectRisk', 'ProjectActivity'] as $model) {
            $this->request('POST', ['name' => 'Forbidden', 'project_id' => 2]);
            try {
                (new TableController)->create($model);
                $this->fail('Read-only members must not create project data.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
                DB::rollBack();
            }
        }
        DB::table('project_user')->where('project_id', 2)->update(['readonly' => false]);
        $this->assertTrue($project->canWrite($this->user));
        $this->assertTrue(Gate::allows('update', $risk));
        $this->assertTrue(Gate::allows('update', $activity));
        $this->assertFalse(Gate::allows('update', $project));
        $this->assertFalse($project->access['manageparticipants']);
    }

    public function test_readonly_members_cannot_add_project_or_risk_comments(): void
    {
        $risk = $this->createRisk(2);
        $this->administrator = false;
        DB::table('project_user')->insert(['project_id' => 2, 'user_id' => 1, 'readonly' => true]);
        foreach ([['Project', 2], ['ProjectRisk', $risk->id]] as [$type, $id]) {
            $this->request('POST', ['object_type' => $type, 'object_id' => $id, 'comment' => 'Forbidden']);
            try {
                (new TableController)->create('ObjectMessage');
                $this->fail('Read-only members cannot add comments.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
                DB::rollBack();
            }
        }
        $this->assertSame(0, DB::table('object_messages')->count());
    }

    public function test_readonly_migration_is_reversible(): void
    {
        $migration = require base_path('database/migrations/2026_10_24_111700_add_readonly_to_project_user.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('project_user', 'readonly'));
        DB::table('project_user')->insert(['project_id' => 1, 'user_id' => 2]);
        $migration->up();
        $this->assertEquals(0, DB::table('project_user')->value('readonly'));
    }

    public function test_work_package_crud_filters_and_preserves_activities_on_delete(): void
    {
        $activity = ProjectActivity::create(['name' => 'Activity', 'project_id' => 1]);
        $controller = new TableController;
        $this->request('POST', [
            'project_id' => 1, 'name' => 'Package', 'description' => 'Description', 'activities' => [$activity->id],
            'start_date' => '2026-10-01', 'end_date' => '2026-11-30',
        ]);
        $created = $controller->create('ProjectWorkPackage')->getData(true);
        $this->assertSame('Package', $created['name']);
        $this->assertNull($created['completed_at']);
        $this->assertSame('2026-10-01', $created['start_date']);
        $this->assertSame('2026-11-30', $created['end_date']);
        $this->assertSame([['id' => $activity->id, 'name' => 'Activity', 'completed_at' => null]], $created['activities']);
        $this->assertTrue($created['access']['update']);
        ProjectWorkPackage::create(['project_id' => 2, 'name' => 'Other package']);
        $this->request('GET', ['filter' => ['project_id' => 1], 'sort' => 'name']);
        $rows = $controller->index('ProjectWorkPackage')->getData(true);
        $this->assertCount(1, $rows);
        $this->assertSame($created['id'], $rows[0]['id']);
        $this->assertSame('2026-10-01', $rows[0]['start_date']);
        $this->request('PATCH', ['name' => 'Renamed', 'activities' => [], 'start_date' => null, 'end_date' => null]);
        $updated = $controller->update('ProjectWorkPackage', $created['id'])->getData(true);
        $this->assertSame('Renamed', $updated['name']);
        $this->assertSame([], $updated['activities']);
        $this->assertNull($updated['start_date']);
        $this->assertNull($updated['end_date']);
        $this->request('PATCH', ['activities' => [$activity->id]]);
        $controller->update('ProjectWorkPackage', $created['id']);
        $this->request('DELETE');
        $controller->delete('ProjectWorkPackage', $created['id']);
        $this->assertFalse(ProjectWorkPackage::whereKey($created['id'])->exists());
        $this->assertTrue(ProjectActivity::whereKey($activity->id)->exists());
        $this->assertSame(0, DB::table('project_work_package_activities')->count());
    }

    public function test_work_packages_require_manual_completion_after_all_activities_finish(): void
    {
        $first = ProjectActivity::create(['name' => 'First', 'project_id' => 1]);
        $second = ProjectActivity::create(['name' => 'Second', 'project_id' => 1]);
        $package = ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Package', 'activities' => [$first->id, $second->id]]);
        foreach ([$first, $second] as $activity) {
            try {
                $package->fresh()->update(['completed_at' => '2026-10-24 14:00:00']);
                $this->fail('Packages with unfinished activities cannot be completed.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('completed_at', $exception->errors());
            }
            $activity->update(['completed_at' => '2026-10-24 13:00:00']);
            $this->assertNull($package->fresh()->completed_at);
        }
        $controller = new TableController;
        $this->request('PATCH', ['completed_at' => '2026-10-24 14:00:00']);
        $completed = $controller->update('ProjectWorkPackage', $package->id)->getData(true);
        $this->assertSame('2026-10-24 14:00:00', $completed['completed_at']);
        $this->request('PATCH', ['completed_at' => null]);
        $reopened = $controller->update('ProjectWorkPackage', $package->id)->getData(true);
        $this->assertNull($reopened['completed_at']);
        $this->assertNotNull($first->fresh()->completed_at);
    }

    public function test_package_dates_are_optional_and_end_cannot_precede_start(): void
    {
        $package = ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Undated']);
        $this->assertNull($package->start_date);
        $this->assertNull($package->end_date);
        $package->update(['start_date' => '2026-10-24', 'end_date' => '2026-10-24']);
        foreach ([
            ['end_date' => '2026-10-23'],
            ['start_date' => 'not a date'],
            ['end_date' => 'not a date'],
        ] as $invalid) {
            try {
                $package->fresh()->update($invalid);
                $this->fail('Invalid package dates must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame('2026-10-24', $package->fresh()->end_date);
    }

    public function test_creating_an_activity_from_a_package_saves_and_links_it_to_the_same_project(): void
    {
        $existing = ProjectActivity::create(['project_id' => 1, 'name' => 'Existing']);
        $package = ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Package', 'activities' => [$existing->id]]);
        $this->request('POST', [
            'name' => 'New activity', 'project_id' => 2, 'description' => 'Details',
            'responsible_user_id' => 1, 'startdate' => '2026-10-24', 'due' => '2026-11-15',
            'estimated_cost' => 125.50, 'estimated_hours' => 5,
        ]);
        $created = (new TableController)->customAction('ProjectWorkPackage', $package->id, 'createactivity')->getData(true);
        $this->assertSame('New activity', $created['name']);
        $this->assertSame(1, $created['project_id']);
        $this->assertSame('2026-11-15', $created['due']);
        $this->assertSame(5, $created['estimated_hours']);
        $this->assertSame(
            [$existing->id, $created['id']],
            $package->fresh()->int_activities()->orderBy('project_activities.id')->pluck('project_activities.id')->all(),
        );
        $this->assertNull($package->fresh()->completed_at);
    }

    public function test_new_package_activities_are_atomic_and_respect_validation_and_package_state(): void
    {
        $package = ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Package']);
        $this->request('POST', ['name' => 'Invalid activity', 'startdate' => '2026-11-01', 'due' => '2026-10-01']);
        try {
            $package->createactivity();
            $this->fail('An invalid activity must not be created.');
        } catch (ValidationException) {
            $this->assertSame(0, DB::table('project_activities')->count());
            $this->assertSame(0, DB::table('project_work_package_activities')->count());
        }
        $finished = ProjectActivity::create(['project_id' => 1, 'name' => 'Finished', 'completed_at' => '2026-10-24 12:00:00']);
        $package->update(['activities' => [$finished->id], 'completed_at' => '2026-10-24 14:00:00']);
        $this->request('POST', ['name' => 'Forbidden']);
        try {
            $package->createactivity();
            $this->fail('A completed package must be reopened first.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $package->update(['completed_at' => null]);
        DB::table('projects')->where('id', 1)->update(['archived_at' => now()]);
        try {
            $package->createactivity();
            $this->fail('Archived project activities cannot be created.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }
        $this->assertSame(1, DB::table('project_activities')->count());
        $this->assertSame(1, DB::table('project_work_package_activities')->count());
    }

    public function test_creating_package_activities_requires_project_write_access(): void
    {
        $package = ProjectWorkPackage::create(['project_id' => 2, 'name' => 'Package']);
        $this->administrator = false;
        DB::table('project_user')->insert(['project_id' => 2, 'user_id' => 1, 'readonly' => true]);
        $this->request('POST', ['name' => 'Forbidden']);
        try {
            (new TableController)->customAction('ProjectWorkPackage', $package->id, 'createactivity');
            $this->fail('Read-only members cannot create package activities.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, DB::table('project_activities')->count());
        DB::table('project_user')->where('project_id', 2)->update(['readonly' => false]);
        $created = (new TableController)->customAction('ProjectWorkPackage', $package->id, 'createactivity')->getData(true);
        $this->assertSame(2, $created['project_id']);
        $this->assertSame([$created['id']], $package->int_activities()->pluck('project_activities.id')->all());
    }

    public function test_empty_packages_cannot_be_completed_and_creation_is_atomic(): void
    {
        $this->expectException(ValidationException::class);
        try {
            ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Empty', 'completed_at' => '2026-10-24 14:00:00']);
        } finally {
            $this->assertSame(0, DB::table('project_work_packages')->count());
        }
    }

    public function test_package_links_validate_project_membership_and_reject_duplicates(): void
    {
        $own = ProjectActivity::create(['name' => 'Own', 'project_id' => 1]);
        $other = ProjectActivity::create(['name' => 'Other', 'project_id' => 2]);
        foreach ([[$other->id], [99999], [$own->id, $own->id], ['invalid'], null] as $ids) {
            try {
                ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Invalid', 'activities' => $ids]);
                $this->fail('Invalid activity links must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(0, DB::table('project_work_packages')->count());
        $package = ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Package', 'activities' => [$own->id]]);
        $this->expectException(HttpException::class);
        try {
            $package->update(['project_id' => 2]);
        } finally {
            $this->assertSame(1, $package->fresh()->project_id);
        }
    }

    public function test_completion_validates_proposed_links_and_rejects_changes_to_completed_packages(): void
    {
        $finished = ProjectActivity::create(['project_id' => 1, 'name' => 'Finished', 'completed_at' => '2026-10-24 13:00:00']);
        $pending = ProjectActivity::create(['project_id' => 1, 'name' => 'Pending']);
        $package = ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Package', 'activities' => [$finished->id]]);
        try {
            $package->update(['completed_at' => '2026-10-24 14:00:00', 'activities' => [$pending->id]]);
            $this->fail('Completion must check proposed links, not only saved links.');
        } catch (ValidationException) {
            $this->assertSame([$finished->id], $package->fresh()->int_activities()->pluck('project_activities.id')->all());
        }
        $stale = $package->fresh();
        $package = $package->fresh();
        $package->update(['completed_at' => '2026-10-24 14:00:00']);
        foreach ([$package, $stale] as $record) {
            try {
                $record->update(['activities' => [$pending->id]]);
                $this->fail('A completed package cannot have its activities changed, even by a stale instance.');
            } catch (ValidationException) {
                $this->assertSame([$finished->id], $package->fresh()->int_activities()->pluck('project_activities.id')->all());
            }
        }
        $package = $package->fresh();
        $package->update(['completed_at' => null, 'activities' => [$pending->id]]);
        $this->assertSame([$pending->id], $package->fresh()->int_activities()->pluck('project_activities.id')->all());
    }

    public function test_activity_reopening_and_deletion_require_all_completed_packages_to_be_reopened(): void
    {
        $activity = ProjectActivity::create(['project_id' => 1, 'name' => 'Finished', 'completed_at' => '2026-10-24 13:00:00']);
        $packages = [];
        foreach (['First', 'Second'] as $name) {
            $packages[] = ProjectWorkPackage::create([
                'project_id' => 1, 'name' => $name, 'activities' => [$activity->id], 'completed_at' => '2026-10-24 14:00:00',
            ]);
        }
        foreach ($packages as $package) {
            try {
                $activity->fresh()->update(['completed_at' => null]);
                $this->fail('An activity cannot be reopened while any of its packages is completed.');
            } catch (ValidationException) {
                $this->assertNotNull($activity->fresh()->completed_at);
            }
            try {
                $activity->fresh()->delete();
                $this->fail('Deleting an activity must not bypass the completed-package restriction.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
            $package->update(['completed_at' => null]);
        }
        $activity->update(['completed_at' => null]);
        $this->assertNull($activity->fresh()->completed_at);
        $activity->delete();
        $this->assertSame(0, DB::table('project_work_package_activities')->count());
    }

    public function test_work_packages_follow_project_read_write_delete_and_archive_permissions(): void
    {
        $package = ProjectWorkPackage::create(['project_id' => 2, 'name' => 'Package']);
        $this->administrator = false;
        $this->assertFalse(Gate::allows('view', $package));
        $this->assertFalse(Gate::allows('update', $package));
        DB::table('project_user')->insert(['project_id' => 2, 'user_id' => 1, 'readonly' => true]);
        $this->assertTrue(Gate::allows('index', ProjectWorkPackage::class));
        $this->assertTrue(Gate::allows('view', $package));
        $this->assertFalse(Gate::allows('update', $package));
        $this->assertFalse(Gate::allows('delete', $package));
        $this->request('POST', ['project_id' => 2, 'name' => 'Forbidden']);
        try {
            (new TableController)->create('ProjectWorkPackage');
            $this->fail('Read-only members cannot create work packages.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            DB::rollBack();
        }
        DB::table('project_user')->where('project_id', 2)->update(['readonly' => false]);
        $this->assertTrue(Gate::allows('update', $package));
        $this->assertFalse(Gate::allows('delete', $package));
        $package->update(['name' => 'Writable']);
        DB::table('projects')->where('id', 2)->update(['archived_at' => now()]);
        foreach (['update', 'delete'] as $operation) {
            try {
                $operation === 'update' ? $package->fresh()->update(['name' => 'Forbidden']) : $package->fresh()->delete();
                $this->fail('Archived project packages cannot be changed.');
            } catch (HttpException $exception) {
                $this->assertSame(400, $exception->getStatusCode());
            }
        }
        $this->assertSame('Writable', $package->fresh()->name);
    }

    public function test_work_package_migration_cascades_and_rolls_back(): void
    {
        $activity = ProjectActivity::create(['project_id' => 1, 'name' => 'Activity']);
        ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Package', 'activities' => [$activity->id]]);
        DB::table('projects')->where('id', 1)->delete();
        $this->assertSame(0, DB::table('project_work_packages')->count());
        $this->assertSame(0, DB::table('project_work_package_activities')->count());
        $migration = require base_path('database/migrations/2026_10_24_143000_create_project_work_packages.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('project_work_packages'));
        $this->assertFalse(Schema::hasTable('project_work_package_activities'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('project_work_packages'));
        $this->assertTrue(Schema::hasTable('project_work_package_activities'));
    }

    public function test_work_package_migration_prevents_duplicate_activity_links(): void
    {
        $activity = ProjectActivity::create(['project_id' => 1, 'name' => 'Activity']);
        $package = ProjectWorkPackage::create(['project_id' => 1, 'name' => 'Package', 'activities' => [$activity->id]]);
        $this->expectException(QueryException::class);
        DB::table('project_work_package_activities')->insert([
            'project_work_package_id' => $package->id, 'project_activity_id' => $activity->id,
        ]);
    }

    private function request(string $method, array $data = []): void
    {
        $request = Request::create('/', $method, $data);
        $request->setUserResolver(fn () => $this->user);
        $this->app->instance('request', $request);
    }

    private function createRisk(int $projectId = 1): ProjectRisk
    {
        return ProjectRisk::create([
            'name' => 'Project risk',
            'project_id' => $projectId,
            'riskowner_id' => 1,
            'scenariodescription' => 'Scenario',
            'consequencedescription' => 'Consequence',
            'probability_id' => 1,
            'consequence_id' => 1,
            'post_probability_id' => 1,
            'post_consequence_id' => 1,
            'note' => 'Migrated note',
            'risk_controls' => [1],
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('responsible_user_id')->constrained('users');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('project_type_id')->nullable();
            $table->timestamps();
        });
        Schema::create('project_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
        });
        foreach (['probability_levels', 'consequence_levels', 'controls'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedInteger('ordinal')->default(1);
            });
            DB::table($name)->insert(['id' => 1, 'name' => $name === 'controls' ? 'Control' : 'Level']);
        }
        Schema::create('risk_levels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color');
            $table->unsignedInteger('ordinal');
            $table->unsignedInteger('reassessment_days_withplans')->default(0);
            $table->unsignedInteger('reassessment_days_withoutplans')->default(0);
        });
        Schema::create('risks', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('assessed_at')->nullable();
            $table->unsignedBigInteger('replacedby_id')->nullable();
            $table->unsignedBigInteger('riskowner_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
        });
        Schema::create('risk_level_mappings', function (Blueprint $table): void {
            $table->unsignedBigInteger('probability_level_id');
            $table->unsignedBigInteger('consequence_level_id');
            $table->unsignedBigInteger('risk_level_id');
        });
        Schema::create('project_risks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('scenariodescription')->nullable();
            $table->text('consequencedescription')->nullable();
            $table->unsignedBigInteger('riskowner_id')->nullable();
            $table->dateTime('assessed_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('probability_id')->nullable();
            $table->unsignedBigInteger('consequence_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedBigInteger('post_probability_id')->nullable();
            $table->unsignedBigInteger('post_consequence_id')->nullable();
        });
        Schema::create('project_risk_controls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_risk_id')->constrained('project_risks')->cascadeOnDelete();
            $table->foreignId('control_id')->constrained('controls')->cascadeOnDelete();
            $table->unique(['project_risk_id', 'control_id']);
        });
        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('startdate')->nullable();
            $table->date('due')->nullable();
            $table->decimal('estimated_cost', 10, 2)->nullable();
            $table->unsignedInteger('estimated_hours')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('project_risk_project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_risk_id')->constrained('project_risks')->cascadeOnDelete();
            $table->foreignId('project_activity_id')->constrained('project_activities')->cascadeOnDelete();
        });
        Schema::create('custom_properties', function (Blueprint $table): void {
            $table->id();
            $table->string('context');
        });
        Schema::create('custom_property_object', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('object_id');
            $table->string('object_type');
        });
        Schema::create('object_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('object_type');
            $table->unsignedBigInteger('object_id');
        });
        Schema::create('tags', fn (Blueprint $table) => $table->id());
        Schema::create('object_tags', function (Blueprint $table): void {
            $table->unsignedBigInteger('tag_id');
            $table->unsignedBigInteger('object_tags_id');
            $table->string('object_tags_type');
        });
        Schema::create('user_notification_channels', function (Blueprint $table): void {
            $table->id();
            $table->text('scopes')->nullable();
            $table->text('events')->nullable();
        });
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->nullableMorphs('causer');
            $table->string('event')->nullable();
            $table->text('properties')->nullable();
            $table->text('attribute_changes')->nullable();
            $table->timestamps();
        });
        DB::table('users')->insert([['id' => 1, 'name' => 'Owner'], ['id' => 2, 'name' => 'Other owner']]);
        DB::table('projects')->insert([
            ['id' => 1, 'name' => 'Project', 'responsible_user_id' => 1, 'start_date' => '2026-10-24'],
            ['id' => 2, 'name' => 'Other project', 'responsible_user_id' => 2, 'start_date' => '2026-10-24'],
        ]);
        DB::table('risk_levels')->insert(['id' => 1, 'name' => 'Level', 'color' => '123456', 'ordinal' => 1]);
        DB::table('risk_level_mappings')->insert(['probability_level_id' => 1, 'consequence_level_id' => 1, 'risk_level_id' => 1]);
    }
}
