<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Application;
use Ledningssystemet\Ledningssystemet\Models\AccessGroup;
use Ledningssystemet\Ledningssystemet\Models\Role;
use Ledningssystemet\Ledningssystemet\Models\User;
use PHPUnit\Framework\TestCase;

class UserOrgRolesTest extends TestCase
{
    private ?ConnectionResolverInterface $previousConnectionResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository([
            'auth' => ['defaults' => ['guard' => 'web']],
            'permission' => require dirname(__DIR__, 2).'/config/permission.php',
        ]));
        $this->previousConnectionResolver = Model::getConnectionResolver();
        $resolver = new ConnectionResolver(['default' => new Connection(null)]);
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);
    }

    protected function tearDown(): void
    {
        if ($this->previousConnectionResolver !== null) {
            Model::setConnectionResolver($this->previousConnectionResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_org_roles_are_read_and_serialized_separately_from_spatie_roles(): void
    {
        $role = new Role;
        $role->setRawAttributes(['id' => 7, 'name' => 'Organizational role']);
        $accessGroup = new AccessGroup(['name' => 'Administrator', 'guard_name' => 'web']);
        $user = new User;
        $user->setRelation('int_roles', new Collection([$role]));
        $permissionRoles = new Collection([$accessGroup]);
        $user->setRelation('roles', $permissionRoles);

        $this->assertContains('org_roles', $user->getAppends());
        $this->assertNotContains('roles', $user->getAppends());
        $this->assertTrue($user->isFillable('org_roles'));
        $this->assertFalse($user->isFillable('roles'));

        $user->setAppends(['org_roles']);
        $expected = [['id' => 7, 'name' => 'Organizational role']];
        $this->assertSame($expected, $user->org_roles);
        $this->assertSame(['org_roles' => $expected], $user->toArray());
        $this->assertSame($permissionRoles, $user->roles);
        $this->assertTrue($user->hasRole('Administrator'));
        $this->assertFalse($user->hasRole('Organizational role'));
    }

    public function test_updating_org_roles_syncs_the_existing_organizational_relation(): void
    {
        $relation = $this->createMock(BelongsToMany::class);
        $relation->expects($this->once())->method('sync')->with([7, 9])->willReturn([]);
        $user = $this->getMockBuilder(User::class)->onlyMethods(['int_roles'])->getMock();
        $user->expects($this->once())->method('int_roles')->willReturn($relation);
        $user->exists = true;
        $permissionRoles = new Collection;
        $user->setRelation('roles', $permissionRoles);

        $user->fill(['org_roles' => [7, 9]]);

        $this->assertSame($permissionRoles, $user->roles);
        $this->assertArrayNotHasKey('org_roles', $user->getAttributes());
        $this->assertArrayNotHasKey('roles', $user->getAttributes());
    }

    public function test_new_users_defer_org_role_sync_until_they_exist(): void
    {
        $relation = $this->createMock(BelongsToMany::class);
        $relation->expects($this->once())->method('sync')->with([9])->willReturn([]);
        $user = $this->getMockBuilder(User::class)->onlyMethods(['int_roles'])->getMock();
        $user->expects($this->once())->method('int_roles')->willReturnCallback(function () use ($user, $relation) {
            $this->assertTrue($user->exists);
            $this->assertSame(42, $user->id);

            return $relation;
        });

        $user->fill(['org_roles' => [7]]);
        $user->fill(['org_roles' => [9]]);
        $user->id = 42;
        $user->exists = true;
        $user->flushPendingRelationAttributeSyncs();
        $user->flushPendingRelationAttributeSyncs();

        $this->assertArrayNotHasKey('org_roles', $user->getAttributes());
    }

    public function test_org_roles_can_be_cleared(): void
    {
        $relation = $this->createMock(BelongsToMany::class);
        $relation->expects($this->once())->method('sync')->with([])->willReturn([]);
        $user = $this->getMockBuilder(User::class)->onlyMethods(['int_roles'])->getMock();
        $user->expects($this->once())->method('int_roles')->willReturn($relation);
        $user->exists = true;

        $user->org_roles = [];
    }
}
