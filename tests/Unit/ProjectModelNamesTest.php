<?php

namespace Tests\Unit;

use Ledningssystemet\Ledningssystemet\Models\Project;
use Ledningssystemet\Ledningssystemet\Models\ProjectType;
use Ledningssystemet\Ledningssystemet\Models\ProjectTypeRiskTemplate;
use Ledningssystemet\Ledningssystemet\Models\Risk;
use Tests\TestCase;

class ProjectModelNamesTest extends TestCase
{
    public function testRenamedModelsUseProjectTablesAndRelations(): void
    {
        $project = new Project;
        $projectType = new ProjectType;
        $template = new ProjectTypeRiskTemplate;
        $risk = new Risk;

        $this->assertSame('projects', $project->getTable());
        $this->assertSame('project_types', $projectType->getTable());
        $this->assertSame('project_type_risk_templates', $template->getTable());
        $this->assertSame('project_user', $project->int_users()->getTable());
        $this->assertSame('control_project_type_risk_template', $template->int_controls()->getTable());
        $this->assertSame('project_id', $risk->int_project()->getForeignKeyName());
        $this->assertSame('project_id', $project->int_risks()->getForeignKeyName());
        $this->assertSame('project_type_id', $project->int_project_type()->getForeignKeyName());
        $this->assertSame('project_type_id', $projectType->int_projects()->getForeignKeyName());
        $this->assertSame('project_type_id', $template->int_project_type()->getForeignKeyName());
    }
}
