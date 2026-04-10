<?php

namespace App\Mcp;

use App\Models\Department;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use PhpMcp\Server\Attributes\McpTool;

class OrganizationTools
{
    /**
     * List all departments with their parent department.
     *
     * @return array List of departments.
     */
    #[McpTool(name: 'list_departments')]
    public function listDepartments(): array
    {
        return Department::with(['int_parent_department:id,name'])
            ->select(['id', 'name', 'parent_department_id'])
            ->orderBy('name')
            ->get()
            ->map(fn (Department $d) => [
                'id'                => $d->id,
                'name'              => $d->name,
                'parent_department' => $d->int_parent_department?->name,
            ])
            ->toArray();
    }

    /**
     * List users with optional search.
     *
     * @param  string  $search  Optional search term to filter by name or email.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of users with name and email.
     */
    #[McpTool(name: 'list_users')]
    public function listUsers(string $search = '', int $limit = 50): array
    {
        $query = User::select(['id', 'name', 'email']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->limit($limit)->get()->toArray();
    }

    /**
     * List all roles with linked competences.
     *
     * @param  string  $search  Optional search term to filter by name or description.
     * @return array List of roles.
     */
    #[McpTool(name: 'list_roles')]
    public function listRoles(string $search = ''): array
    {
        $query = Role::withCount('int_role_competence')
            ->select(['id', 'name', 'description']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->get()->map(fn (Role $r) => [
            'id'               => $r->id,
            'name'             => $r->name,
            'description'      => $r->description,
            'competences_count' => $r->int_role_competence_count,
        ])->toArray();
    }

    /**
     * List suppliers with optional search and category filter.
     *
     * @param  string  $search  Optional search term to filter by name or description.
     * @param  int  $limit  Maximum number of results to return (default 50).
     * @return array List of suppliers.
     */
    #[McpTool(name: 'list_suppliers')]
    public function listSuppliers(string $search = '', int $limit = 50): array
    {
        $query = Supplier::select(['id', 'name', 'description']);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->limit($limit)->get()->toArray();
    }
}

