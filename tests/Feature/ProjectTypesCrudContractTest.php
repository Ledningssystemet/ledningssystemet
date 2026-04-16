<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectTypesCrudContractTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		if (! Schema::hasTable('users')) {
			Schema::create('users', function (Blueprint $table): void {
				$table->id();
				$table->string('name');
				$table->string('email');
				$table->timestamp('email_verified_at')->nullable();
				$table->string('password');
				$table->boolean('enabled')->default(true);
				$table->string('remember_token', 100)->nullable();
				$table->timestamps();
			});
		}

		if (! Schema::hasTable('project_types')) {
			Schema::create('project_types', function (Blueprint $table): void {
				$table->id();
				$table->string('name');
				$table->text('description')->nullable();
				$table->unsignedBigInteger('partner_id')->nullable();
				$table->string('partner_name')->nullable();
				$table->timestamps();
			});
		}

		if (! Schema::hasTable('project_type_risk_templates')) {
			Schema::create('project_type_risk_templates', function (Blueprint $table): void {
				$table->id();
				$table->unsignedBigInteger('project_type_id')->nullable();
				$table->string('name');
				$table->text('scenariodescription')->nullable();
				$table->text('consequencedescription')->nullable();
				$table->unsignedBigInteger('probability_id')->nullable();
				$table->unsignedBigInteger('consequence_id')->nullable();
				$table->unsignedBigInteger('partner_id')->nullable();
				$table->timestamps();
			});
		}

		if (! Schema::hasTable('controls')) {
			Schema::create('controls', function (Blueprint $table): void {
				$table->id();
				$table->string('name');
				$table->timestamps();
			});
		}

		if (! Schema::hasTable('control_project_type_risk_template')) {
			Schema::create('control_project_type_risk_template', function (Blueprint $table): void {
				$table->unsignedBigInteger('control_id');
				$table->unsignedBigInteger('project_type_risk_template_id');
				$table->timestamps();
			});
		}

		DB::statement('SET FOREIGN_KEY_CHECKS=0');
		DB::table('control_project_type_risk_template')->delete();
		DB::table('project_type_risk_templates')->delete();
		DB::table('project_types')->delete();
		DB::table('controls')->delete();
		DB::table('users')->delete();
		DB::statement('SET FOREIGN_KEY_CHECKS=1');
	}

	public function test_project_types_and_templates_support_expected_crud_contract(): void
	{
		Gate::before(static fn (): bool => true);

		$user = $this->createUser('Risk Manager', 'risk.manager@example.com');
		$this->actingAs($user, 'sanctum');

		$projectTypeResponse = $this->postJson('/api/crud/risk-project-types', [
			'name' => 'Operational project',
			'description' => 'Operational risk project type',
		]);

		$projectTypeResponse->assertCreated();
		$projectTypeId = (int) $projectTypeResponse->json('id');

		$projectTypeIndex = $this->getJson('/api/crud/risk-project-types?paginate=0&%24select=id,name,description');
		$projectTypeIndex->assertOk();
		$this->assertTrue(collect($projectTypeIndex->json())->contains(
			fn (array $row): bool => (int) $row['id'] === $projectTypeId
				&& ($row['name'] ?? null) === 'Operational project'
		));

		$otherProjectTypeId = DB::table('project_types')->insertGetId([
			'name' => 'Other type',
			'description' => 'Other',
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$controlA = DB::table('controls')->insertGetId([
			'name' => 'Control A',
			'description' => 'A',
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$controlB = DB::table('controls')->insertGetId([
			'name' => 'Control B',
			'description' => 'B',
			'created_at' => now(),
			'updated_at' => now(),
		]);

		$templateResponse = $this->postJson('/api/crud/risk-project-type-risk-templates', [
			'project_type_id' => $projectTypeId,
			'name' => 'Phishing template',
			'scenariodescription' => 'Phishing scenario',
			'consequencedescription' => 'Major consequence',
			'controls' => [$controlA, $controlB],
		]);

		$templateResponse->assertCreated();
		$templateId = (int) $templateResponse->json('id');

		$templateIndex = $this->getJson(
			'/api/crud/risk-project-type-risk-templates?paginate=0&filter[project_type_id]='.$projectTypeId.'&%24select=id,name,project_type_id,controls'
		);

		$templateIndex->assertOk();
		$rows = collect($templateIndex->json());
		$this->assertCount(1, $rows);

		$row = $rows->first();
		$this->assertSame($templateId, (int) ($row['id'] ?? 0));
		$this->assertSame($projectTypeId, (int) ($row['project_type_id'] ?? 0));

		$returnedControls = collect($row['controls'] ?? [])->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
		$this->assertSame([$controlA, $controlB], $returnedControls);

		$this->patchJson('/api/crud/risk-project-type-risk-templates/'.$templateId, [
			'controls' => [$controlB],
		])->assertOk();

		$updated = $this->getJson('/api/crud/risk-project-type-risk-templates/'.$templateId.'?%24select=id,controls');
		$updated->assertOk();
		$this->assertSame([$controlB], collect($updated->json('controls'))->map(fn (mixed $id): int => (int) $id)->values()->all());

		$filteredOtherType = $this->getJson(
			'/api/crud/risk-project-type-risk-templates?paginate=0&filter[project_type_id]='.$otherProjectTypeId.'&%24select=id'
		);
		$filteredOtherType->assertOk();
		$this->assertCount(0, $filteredOtherType->json());
	}

	private function createUser(string $name, string $email): User
	{
		$id = DB::table('users')->insertGetId([
			'name' => $name,
			'email' => $email,
			'password' => Hash::make('password'),
			'enabled' => true,
			'created_at' => now(),
			'updated_at' => now(),
		]);

		return User::query()->findOrFail($id);
	}
}

