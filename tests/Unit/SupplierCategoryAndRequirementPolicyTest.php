<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\SupplierCategoryPolicy;
use App\Policies\SupplierRequirementPolicy;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SupplierCategoryPolicy::class)]
#[CoversClass(SupplierRequirementPolicy::class)]
class SupplierCategoryAndRequirementPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_supplier_category_policy_requires_managementtools_edit_when_suppliers_enabled(): void
    {
        config(['ledningssystemet.disable_supplier' => false]);

        $policy = new SupplierCategoryPolicy();

        $allowedUser = Mockery::mock(User::class);
        $allowedUser->shouldReceive('haveAnyAccessRights')
            ->with(['managementtools.edit', 'superadmin.edit'])
            ->andReturn(true);

        $deniedUser = Mockery::mock(User::class);
        $deniedUser->shouldReceive('haveAnyAccessRights')
            ->with(['managementtools.edit', 'superadmin.edit'])
            ->andReturn(false);

        $this->assertTrue($policy->viewAny($allowedUser));
        $this->assertTrue($policy->view($allowedUser));
        $this->assertTrue($policy->create($allowedUser));
        $this->assertTrue($policy->update($allowedUser));
        $this->assertTrue($policy->delete($allowedUser));

        $this->assertFalse($policy->viewAny($deniedUser));
        $this->assertFalse($policy->view($deniedUser));
        $this->assertFalse($policy->create($deniedUser));
        $this->assertFalse($policy->update($deniedUser));
        $this->assertFalse($policy->delete($deniedUser));
    }

    public function test_supplier_requirement_policy_requires_managementtools_edit_when_suppliers_enabled(): void
    {
        config(['ledningssystemet.disable_supplier' => false]);

        $policy = new SupplierRequirementPolicy();

        $allowedUser = Mockery::mock(User::class);
        $allowedUser->shouldReceive('haveAnyAccessRights')
            ->with(['managementtools.edit', 'superadmin.edit'])
            ->andReturn(true);

        $deniedUser = Mockery::mock(User::class);
        $deniedUser->shouldReceive('haveAnyAccessRights')
            ->with(['managementtools.edit', 'superadmin.edit'])
            ->andReturn(false);

        $this->assertTrue($policy->viewAny($allowedUser));
        $this->assertTrue($policy->view($allowedUser));
        $this->assertTrue($policy->create($allowedUser));
        $this->assertTrue($policy->update($allowedUser));
        $this->assertTrue($policy->delete($allowedUser));

        $this->assertFalse($policy->viewAny($deniedUser));
        $this->assertFalse($policy->view($deniedUser));
        $this->assertFalse($policy->create($deniedUser));
        $this->assertFalse($policy->update($deniedUser));
        $this->assertFalse($policy->delete($deniedUser));
    }

    public function test_supplier_related_policies_deny_all_access_when_supplier_module_is_disabled(): void
    {
        config(['ledningssystemet.disable_supplier' => true]);

        $user = Mockery::mock(User::class);

        $categoryPolicy = new SupplierCategoryPolicy();
        $this->assertFalse($categoryPolicy->viewAny($user));
        $this->assertFalse($categoryPolicy->view($user));
        $this->assertFalse($categoryPolicy->create($user));
        $this->assertFalse($categoryPolicy->update($user));
        $this->assertFalse($categoryPolicy->delete($user));

        $requirementPolicy = new SupplierRequirementPolicy();
        $this->assertFalse($requirementPolicy->viewAny($user));
        $this->assertFalse($requirementPolicy->view($user));
        $this->assertFalse($requirementPolicy->create($user));
        $this->assertFalse($requirementPolicy->update($user));
        $this->assertFalse($requirementPolicy->delete($user));
    }
}

