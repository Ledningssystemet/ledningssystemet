<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Ledningssystemet\Ledningssystemet\Http\Controllers\Api\LegacyTableController;
use Ledningssystemet\Ledningssystemet\Http\Controllers\Api\TableController;
use Ledningssystemet\Ledningssystemet\Models\Project;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeeting;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeetingAgendaTemplate;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeetingMention;
use Ledningssystemet\Ledningssystemet\Models\ProjectMeetingAgendaTemplateItem;
use Ledningssystemet\Ledningssystemet\Models\User;
use Ledningssystemet\Ledningssystemet\Support\MeetingDocument;
use Ledningssystemet\Ledningssystemet\Support\ProjectMeetingAgenda;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProjectMeetingTest extends TestCase
{
    private User $user;

    private bool $administrator = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('The SQLite PDO driver is required.');
        }
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true],
            'cache.default' => 'array',
            'activitylog.enabled' => false,
        ]);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
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
        Schema::create('project_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('Project');
            $table->unsignedBigInteger('responsible_user_id');
            $table->unsignedBigInteger('project_type_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
        Schema::create('project_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('readonly')->default(false);
        });
        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->timestamp('completed_at')->nullable();
        });
        $migration = require base_path('database/migrations/2026_10_25_080000_create_project_meetings.php');
        $migration->up();
        DB::table('users')->insert([['id' => 1, 'name' => 'Owner'], ['id' => 2, 'name' => 'Participant'], ['id' => 3, 'name' => 'Outsider']]);
        DB::table('projects')->insert([['id' => 1, 'responsible_user_id' => 1], ['id' => 2, 'responsible_user_id' => 3]]);
        DB::table('project_user')->insert(['project_id' => 1, 'user_id' => 2, 'readonly' => true]);
        DB::table('project_activities')->insert([['id' => 1, 'project_id' => 1], ['id' => 2, 'project_id' => 2]]);
        $this->asUser(1);
        $this->request('GET');
    }

    protected function tearDown(): void
    {
        DB::purge('sqlite');
        parent::tearDown();
    }

    private function asUser(int $id): void
    {
        $this->user = Mockery::mock(User::class)->makePartial();
        $this->user->forceFill(['id' => $id, 'name' => 'User '.$id]);
        $this->user->shouldReceive('hasPermissionTo')->andReturn(false);
        $this->user->shouldReceive('hasAnyPermission')->andReturnUsing(fn () => $this->administrator);
        auth()->setUser($this->user);
    }

    private function request(string $method, array $data = []): void
    {
        $request = Request::create('/', $method, $data);
        $request->setUserResolver(fn () => $this->user);
        $this->app->instance('request', $request);
    }

    private function agenda(int $userId = 2, string $text = 'Follow up '): array
    {
        return [[
            'id' => 'section-1', 'title' => 'Follow-up', 'activity_ids' => [1],
            'document' => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => $text, 'marks' => [['type' => 'bold']]],
                    ['type' => 'mention', 'attrs' => ['id' => $userId, 'label' => 'Participant']],
                    ['type' => 'mention', 'attrs' => ['id' => $userId, 'label' => 'Participant']],
                ]],
            ]],
        ]];
    }

    private function meeting(): ProjectMeeting
    {
        return ProjectMeeting::create(['project_id' => 1, 'name' => 'Planning', 'date' => '2026-10-25', 'agenda' => $this->agenda()]);
    }

    public function test_meeting_crud_round_trips_tiptap_json_and_creates_deduplicated_mentions(): void
    {
        $controller = new TableController;
        $this->request('POST', ['project_id' => 1, 'name' => 'Planning', 'date' => '2026-10-25', 'agenda' => $this->agenda()]);
        $created = $controller->create('ProjectMeeting')->getData(true);
        $this->assertSame($this->agenda(), $created['agenda']);
        $this->assertSame(1, $created['revision']);
        $this->assertSame(1, ProjectMeetingMention::count());
        $this->assertSame('Follow up @Participant@Participant', ProjectMeetingMention::first()->excerpt);
        $this->request('GET', ['filter' => ['project_id' => 1], 'sort' => '-date']);
        $rows = $controller->index('ProjectMeeting')->getData(true);
        $this->assertSame([$created['id']], array_column($rows, 'id'));
        $this->request('PATCH', ['revision' => $created['revision'], 'location' => 'Room A']);
        $updated = $controller->update('ProjectMeeting', $created['id'])->getData(true);
        $this->assertSame('Room A', $updated['location']);
        $this->assertSame(2, $updated['revision']);
        $this->request('DELETE');
        $controller->delete('ProjectMeeting', $created['id']);
        $this->assertSame(0, ProjectMeetingMention::count());
        $this->assertSame(2, DB::table('project_activities')->count());
    }

    public function test_meeting_accepts_null_optional_agenda_description_but_rejects_non_string_values(): void
    {
        $meeting = $this->meeting();
        $agenda = $meeting->agenda;
        $agenda[0]['description'] = null;
        $meeting->update(['agenda' => $agenda]);

        $this->assertNull($meeting->fresh()->agenda[0]['description']);

        $agenda[0]['description'] = ['invalid'];
        try {
            $meeting->fresh()->update(['agenda' => $agenda]);
            $this->fail('A non-string agenda description should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('agenda.0.description', $exception->errors());
        }
    }

    public function test_meeting_mentions_are_personal_acknowledgements_are_idempotent_and_changes_reset_them(): void
    {
        $meeting = $this->meeting();
        $mention = ProjectMeetingMention::firstOrFail();
        $this->assertFalse(Gate::allows('view', $mention));
        $this->assertFalse(Gate::allows('create', ProjectMeetingMention::class));
        $this->assertSame([], ProjectMeetingMention::visibleTo($this->user)->pluck('id')->all());
        $this->asUser(2);
        $this->request('GET', ['filter' => ['project_id' => 1]]);
        $rows = (new TableController)->index('ProjectMeetingMention')->getData(true);
        $this->assertSame([$mention->id], array_column($rows, 'id'));
        $this->request('POST');
        (new TableController)->customAction('ProjectMeetingMention', $mention->id, 'acknowledge');
        $stamp = $mention->fresh()->acknowledged_at;
        $this->assertNotNull($stamp);
        $this->travel(1)->hours();
        $mention->acknowledge();
        $this->assertEquals($stamp, $mention->fresh()->acknowledged_at);
        $this->asUser(1);
        $this->request('GET');
        $meeting->update(['name' => 'Renamed']);
        $this->assertEquals($stamp, $mention->fresh()->acknowledged_at);
        $meeting->update(['agenda' => $this->agenda(2, 'Changed notes ')]);
        $this->assertNull($mention->fresh()->acknowledged_at);
        $meeting->update(['agenda' => []]);
        $this->assertSame(0, ProjectMeetingMention::count());
    }

    public function test_header_can_list_personal_mentions_across_projects_with_navigation_details(): void
    {
        $meeting = $this->meeting();
        DB::table('project_user')->insert(['project_id' => 2, 'user_id' => 2, 'readonly' => true]);
        $this->asUser(3);
        $agenda = $this->agenda(2, 'Other project ');
        $agenda[0]['activity_ids'] = [2];
        ProjectMeeting::create(['project_id' => 2, 'name' => 'Other planning', 'date' => '2026-10-25', 'agenda' => $agenda]);
        $this->asUser(2);
        $this->request('GET', ['sort' => '-created_at']);
        $controller = new TableController;
        $rows = $controller->index('ProjectMeetingMention')->getData(true);
        $this->assertCount(2, $rows);
        $byProject = array_column($rows, null, 'project_id');
        $this->assertSame(1, $byProject[1]['project_id']);
        $this->assertSame('Project', $byProject[1]['project_name']);
        $this->assertSame('Planning', $byProject[1]['meeting_name']);
        $this->assertSame($meeting->id, $byProject[1]['project_meeting_id']);
        $this->assertSame('section-1', $byProject[1]['agenda_item_id']);
        $this->assertNull($byProject[1]['acknowledged_at']);
        $this->assertArrayNotHasKey('int_meeting', $byProject[1]);
        $this->assertSame('Other planning', $byProject[2]['meeting_name']);

        $mention = ProjectMeetingMention::firstOrFail();
        $this->assertFalse(Gate::allows('update', $mention));
        $this->assertFalse(Gate::allows('delete', $mention));
        $this->asUser(1);
        $this->request('GET');
        $this->assertSame([], $controller->index('ProjectMeetingMention')->getData(true));
        $this->assertHttp(403, fn () => $controller->show('ProjectMeetingMention', $mention->id));
        $this->asUser(2);
        DB::table('project_user')->delete();
        $this->assertSame([], $controller->index('ProjectMeetingMention')->getData(true));
        $this->assertHttp(403, fn () => $mention->acknowledge());
    }

    public function test_meeting_permissions_scope_before_pagination_and_allow_readonly_acknowledgement(): void
    {
        $meeting = $this->meeting();
        $this->asUser(3);
        $this->request('POST');
        $other = ProjectMeeting::create(['project_id' => 2, 'name' => 'Other', 'date' => '2026-10-25']);
        $this->assertFalse(Gate::allows('view', $meeting));
        $this->asUser(2);
        $this->request('GET', ['per_page' => 1, 'fields' => ['project_meetings' => 'id']]);
        $rows = (new TableController)->index('ProjectMeeting')->getData(true);
        $this->assertSame(1, $rows['total']);
        $this->assertSame([$meeting->id], array_column($rows['data'], 'id'));
        $this->assertTrue(Gate::allows('view', $meeting));
        $this->assertFalse(Gate::allows('view', $other));
        $this->assertFalse(Gate::allows('update', $meeting));
        $this->assertFalse(Gate::allows('delete', $meeting));
        $this->assertHttp(403, fn () => $meeting->update(['name' => 'Forbidden']));
        $this->assertHttp(403, fn () => $this->meeting());
        $this->asUser(1);
        $this->request('POST');
        $mention = ProjectMeetingMention::firstOrFail();
        $this->assertHttp(403, fn () => $mention->acknowledge());
        $this->asUser(2);
        DB::table('project_user')->delete();
        $this->assertHttp(403, fn () => $mention->acknowledge());
    }

    public function test_meeting_completion_and_project_archival_are_enforced_on_server(): void
    {
        $meeting = $this->meeting();
        $meeting->update(['completed_at' => '2000-01-01 00:00:00']);
        $this->assertNotSame('2000-01-01 00:00:00', $meeting->completed_at);
        $this->assertFalse($meeting->access['update']);
        $this->assertHttp(422, fn () => $meeting->update(['completed_at' => null]));
        $this->assertHttp(422, fn () => $meeting->delete());
        $this->asUser(2);
        ProjectMeetingMention::firstOrFail()->acknowledge();
        $this->assertNotNull(ProjectMeetingMention::first()->acknowledged_at);
        $this->asUser(1);
        $open = $this->meeting();
        DB::table('projects')->where('id', 1)->update(['archived_at' => now()]);
        $this->assertHttp(422, fn () => $open->update(['name' => 'Forbidden']));
        $this->assertHttp(422, fn () => $open->delete());
    }

    public function test_meeting_rejects_stale_revision_without_overwriting_saved_document(): void
    {
        $meeting = $this->meeting();
        $stale = $meeting->fresh();
        $meeting->update(['name' => 'Saved first']);
        $this->assertHttp(409, fn () => $stale->update(['name' => 'Stale']));
        $this->assertSame('Saved first', $meeting->fresh()->name);
        $this->assertSame(2, $meeting->fresh()->revision);
    }

    public function test_meeting_rejects_outsiders_cross_project_links_moves_and_invalid_json_atomically(): void
    {
        $meeting = $this->meeting();
        $badLink = $this->agenda();
        $badLink[0]['activity_ids'] = [2];
        $badNode = $this->agenda();
        $badNode[0]['document']['content'][] = ['type' => 'raw', 'text' => '<script>'];
        $badList = $this->agenda();
        $badList[0]['activity_ids'] = ['not-an-index' => 1];
        foreach ([$this->agenda(3), $badLink, $badNode, $badList, ['not-an-index' => $this->agenda()[0]], [...$this->agenda(), ...$this->agenda()]] as $agenda) {
            try {
                $meeting->fresh()->update(['name' => 'Invalid', 'agenda' => $agenda]);
                $this->fail('Invalid meeting payload should fail.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
            $this->assertSame('Planning', $meeting->fresh()->name);
            $this->assertSame(1, $meeting->fresh()->revision);
            $this->assertSame(1, ProjectMeetingMention::count());
        }
        $this->assertHttp(422, fn () => $meeting->update(['project_id' => 2]));
    }

    public function test_meeting_document_rejects_oversized_and_deep_documents(): void
    {
        $large = $this->agenda();
        $large[0]['document']['content'][0]['content'][0]['text'] = str_repeat('a', 500001);
        $deep = ['type' => 'paragraph'];
        for ($i = 0; $i < 22; $i++) {
            $deep = ['type' => 'blockquote', 'content' => [$deep]];
        }
        $nested = $this->agenda();
        $nested[0]['document']['content'] = [$deep];
        foreach ([$large, $nested] as $agenda) {
            try {
                MeetingDocument::mentions($agenda);
                $this->fail('Document limits should be enforced.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('agenda', $exception->errors());
            }
        }
    }

    public function test_meeting_project_deletion_cascades_and_migration_is_reversible(): void
    {
        $this->meeting();
        DB::table('projects')->where('id', 1)->delete();
        $this->assertSame(0, ProjectMeeting::count());
        $this->assertSame(0, ProjectMeetingMention::count());
        $migration = require base_path('database/migrations/2026_10_25_080000_create_project_meetings.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('project_meetings'));
        $this->assertFalse(Schema::hasTable('project_meeting_mentions'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('project_meetings'));
    }

    private function template(string $name = 'Construction'): ProjectMeetingAgendaTemplate
    {
        $template = ProjectMeetingAgendaTemplate::create(['name' => $name]);
        ProjectMeetingAgendaTemplateItem::create(['project_meeting_agenda_template_id' => $template->id, 'name' => 'Safety', 'description' => 'Review incidents', 'ordinal' => 2]);
        ProjectMeetingAgendaTemplateItem::create(['project_meeting_agenda_template_id' => $template->id, 'name' => 'Opening', 'ordinal' => 1]);

        return $template;
    }

    private function fromTemplate(ProjectMeetingAgendaTemplate $template, string $date = '2026-10-25', bool $followUp = false): ProjectMeeting
    {
        $preview = ProjectMeetingAgenda::forProject(Project::findOrFail(1), $date, $template->id);

        return ProjectMeeting::create([
            'project_id' => 1, 'project_meeting_agenda_template_id' => $template->id, 'name' => $template->name, 'date' => $date,
            'follow_up_meeting_id' => $preview['previous_meeting']['id'] ?? null, 'include_follow_up' => $followUp,
            'agenda' => [...($followUp && $preview['follow_up_section'] ? [$preview['follow_up_section']] : []), ...$preview['sections']],
        ]);
    }

    public function test_meeting_templates_support_multiple_agendas_and_snapshot_mandatory_points(): void
    {
        $template = $this->template();
        $other = $this->template('Steering group');
        $preview = ProjectMeetingAgenda::forProject(Project::findOrFail(1), '2026-10-25', $template->id);
        $this->assertCount(2, $preview['templates']);
        $this->assertSame(['Opening', 'Safety'], array_column($preview['sections'], 'title'));
        $meeting = $this->fromTemplate($template);
        $this->assertSame($template->id, $meeting->project_meeting_agenda_template_id);
        $this->assertSame('template', $meeting->agenda[0]['source']);
        $this->assertNull(ProjectMeetingAgenda::forProject(Project::findOrFail(1), '2026-10-26', $other->id)['previous_meeting']);
        $required = $meeting->agenda;
        foreach ([
            array_slice($required, 1),
            [array_replace($required[0], ['title' => 'Renamed']), $required[1]],
            [array_replace($required[0], ['source' => 'custom']), $required[1]],
            [array_replace($required[0], ['description' => 'Changed']), $required[1]],
        ] as $agenda) {
            $this->assertValidation(fn () => $meeting->fresh()->update(['agenda' => $agenda]));
        }
        ProjectMeetingAgendaTemplateItem::where('project_meeting_agenda_template_id', $template->id)->update(['name' => 'Changed template']);
        $meeting->update(['agenda' => array_reverse($required)]);
        $this->assertSame(['Safety', 'Opening'], array_column($meeting->fresh()->agenda, 'title'));
        $this->assertHttp(422, fn () => $meeting->update(['project_meeting_agenda_template_id' => $other->id]));
    }

    public function test_meeting_follow_up_uses_same_agenda_and_only_open_activities_with_explicit_confirmation(): void
    {
        $template = $this->template();
        $first = $this->fromTemplate($template);
        DB::table('project_activities')->insert(['id' => 3, 'project_id' => 1, 'completed_at' => now()]);
        $agenda = $first->agenda;
        $agenda[0]['activity_ids'] = [1, 3];
        $first->update(['agenda' => $agenda]);
        $this->fromTemplate($this->template('Different agenda'), '2026-10-26');
        $project = Project::findOrFail(1);
        $this->request('POST', ['date' => '2026-10-27', 'project_meeting_agenda_template_id' => $template->id]);
        $preview = (new TableController)->customAction('Project', 1, 'meetingagenda')->getData(true);
        $this->assertSame($first->id, $preview['previous_meeting']['id']);
        $this->assertSame([1], $preview['follow_up_section']['activity_ids']);
        $this->assertSame($first->id, $preview['follow_up_section']['previous_meeting_id']);
        $payload = [
            'project_id' => 1, 'project_meeting_agenda_template_id' => $template->id, 'name' => 'Next', 'date' => '2026-10-27',
            'agenda' => $preview['sections'], 'follow_up_meeting_id' => $first->id,
        ];
        $this->assertHttp(422, fn () => ProjectMeeting::create($payload));
        $this->assertValidation(fn () => ProjectMeeting::create([...$payload, 'include_follow_up' => true]));
        $next = $this->fromTemplate($template, '2026-10-27', true);
        $this->assertSame($first->id, $next->previous_meeting_id);
        $this->assertSame('followup', $next->agenda[0]['source']);
        $this->assertSame([1], $next->agenda[0]['activity_ids']);
        $this->assertValidation(fn () => $next->fresh()->update(['agenda' => array_slice($next->agenda, 1)]));
        $without = $this->fromTemplate($template, '2026-10-28', false);
        $this->assertNull($without->previous_meeting_id);
        $this->assertSame(['template', 'template'], array_column($without->agenda, 'source'));
        $this->assertNull(ProjectMeetingAgenda::forProject($project, '2026-10-24', $template->id)['previous_meeting']);
    }

    public function test_meeting_templates_are_available_to_all_projects_and_stale_previews_are_rejected(): void
    {
        $template = $this->template();
        $preview = ProjectMeetingAgenda::forProject(Project::findOrFail(1), '2026-10-26', $template->id);
        $this->fromTemplate($template);
        $this->assertHttp(409, fn () => ProjectMeeting::create([
            'project_id' => 1, 'project_meeting_agenda_template_id' => $template->id, 'name' => 'Stale preview',
            'date' => '2026-10-26', 'agenda' => $preview['sections'], 'include_follow_up' => false,
        ]));
        $this->assertContains($template->id, array_column(ProjectMeetingAgenda::forProject(Project::findOrFail(2), '2026-10-26')['templates'], 'id'));
        $this->asUser(3);
        $this->request('POST', ['date' => '2026-10-26']);
        $this->assertHttp(403, fn () => (new TableController)->customAction('Project', 1, 'meetingagenda'));
        $this->assertFalse(Gate::allows('create', ProjectMeetingAgendaTemplate::class));
        $this->assertFalse(Gate::allows('create', ProjectMeetingAgendaTemplateItem::class));
    }

    public function test_meeting_template_deletion_preserves_existing_required_points_and_schema_rolls_back(): void
    {
        $template = $this->template();
        $meeting = $this->fromTemplate($template);
        $template->delete();
        $this->assertNull($meeting->fresh()->project_meeting_agenda_template_id);
        $this->assertCount(2, $meeting->fresh()->agenda);
        $this->assertValidation(fn () => $meeting->fresh()->update(['agenda' => []]));
        $this->assertSame(0, ProjectMeetingAgendaTemplateItem::count());
        $migration = require base_path('database/migrations/2026_10_25_080000_create_project_meetings.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('project_meeting_agenda_templates'));
        $this->assertFalse(Schema::hasColumn('project_meetings', 'project_meeting_agenda_template_id'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('project_meeting_agenda_templates'));
    }

    public function test_meeting_agenda_configuration_uses_existing_admin_permissions_and_filtered_legacy_api(): void
    {
        $this->administrator = true;
        $controller = new LegacyTableController;
        $this->request('POST', ['name' => 'Construction meeting']);
        $template = $controller->create('ProjectMeetingAgendaTemplate')->getData(true);
        $this->request('POST', ['project_meeting_agenda_template_id' => $template['id'], 'name' => 'Safety', 'ordinal' => '2']);
        $item = $controller->create('ProjectMeetingAgendaTemplateItem')->getData(true);
        $this->assertSame(2, $item['ordinal']);
        $this->request('POST', ['project_meeting_agenda_template_id' => $template['id'], 'name' => 'Opening', 'ordinal' => 1]);
        $controller->create('ProjectMeetingAgendaTemplateItem');
        $this->request('GET', ['project_meeting_agenda_template_id' => $template['id']]);
        $rows = $controller->index('ProjectMeetingAgendaTemplateItem')->getData(true);
        $this->assertSame(['Opening', 'Safety'], array_column($rows['data'], 'name'));
        $second = ProjectMeetingAgendaTemplate::create(['name' => 'Another agenda']);
        $this->request('GET', ['project_type_id' => 1]);
        $rows = $controller->index('ProjectMeetingAgendaTemplate')->getData(true);
        $this->assertSame([$template['id'], $second->id], array_column($rows['data'], 'id'));
        $this->assertValidation(fn () => ProjectMeetingAgendaTemplateItem::findOrFail($item['id'])->update(['name' => '']));
        $this->assertValidation(fn () => ProjectMeetingAgendaTemplateItem::findOrFail($item['id'])->update(['ordinal' => -1]));
        $this->administrator = false;
        $this->request('POST');
        $this->assertHttp(403, fn () => $controller->delete('ProjectMeetingAgendaTemplate', $template['id']));
    }

    public function test_meeting_agenda_items_are_added_at_the_end_and_can_be_reordered_after_another_item(): void
    {
        $this->administrator = true;
        $template = ProjectMeetingAgendaTemplate::create(['name' => 'Planning']);
        $opening = ProjectMeetingAgendaTemplateItem::create(['project_meeting_agenda_template_id' => $template->id, 'name' => 'Opening']);
        $discussion = ProjectMeetingAgendaTemplateItem::create(['project_meeting_agenda_template_id' => $template->id, 'name' => 'Discussion']);
        $closing = ProjectMeetingAgendaTemplateItem::create(['project_meeting_agenda_template_id' => $template->id, 'name' => 'Closing']);
        $this->assertSame([2, 4, 6], [$opening->ordinal, $discussion->ordinal, $closing->ordinal]);

        $this->request('POST', ['after' => $opening->id]);
        $closing->reorder();

        $ordered = ProjectMeetingAgendaTemplateItem::where('project_meeting_agenda_template_id', $template->id)->orderBy('ordinal')->pluck('name', 'ordinal')->all();
        $this->assertSame([0 => 'Opening', 2 => 'Closing', 4 => 'Discussion'], $ordered);
    }

    public function test_meeting_writable_participants_can_create_and_update_template_meetings(): void
    {
        $template = $this->template();
        DB::table('project_user')->where('user_id', 2)->update(['readonly' => false]);
        $this->asUser(2);
        $this->request('POST');
        $meeting = $this->fromTemplate($template);
        $this->assertTrue(Gate::allows('update', $meeting));
        $meeting->update(['name' => 'Updated by participant']);
        $this->assertSame('Updated by participant', $meeting->fresh()->name);
    }

    private function assertValidation(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected validation failure.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    private function assertHttp(int $status, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected HTTP '.$status);
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }
}
