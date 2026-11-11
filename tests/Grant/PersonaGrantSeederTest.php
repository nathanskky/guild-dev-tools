<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Grant;

use Guild\DevTools\Grant\PersonaGrantSeeder;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\DevTools\Test\Support\FixturePermission;
use Guild\DevTools\Test\Support\FixtureRoster;
use Guild\Framework\Model\Group;
use Guild\Framework\Model\Role;
use Guild\Framework\Model\RolePermission;
use Guild\Framework\Testing\FrameworkSchema;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonaGrantSeeder::class)]
#[UsesClass(Persona::class)]
#[UsesClass(PersonaGroup::class)]
#[UsesClass(PersonaKey::class)]
#[UsesClass(PersonaRoster::class)]
#[UsesClass(RoleGrant::class)]
final class PersonaGrantSeederTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $connection = $capsule->getConnection();
        self::assertInstanceOf(Connection::class, $connection);
        $this->db = $connection;

        FrameworkSchema::migrate($this->db);
    }

    public function testTheRostersGrantsAreWritten(): void
    {
        self::seeder()->seed();

        $group = Group::query()->where('group_identifier', FixtureRoster::EDITORS_GROUP)->firstOrFail();
        $role = Role::query()->where('name', FixtureRoster::EDITOR_ROLE)->firstOrFail();

        self::assertSame(FixtureRoster::EDITORS_LABEL, $group->name, 'a new group is named by its label');
        self::assertTrue($group->active, 'and is active');
        self::assertSame(PersonaGrantSeeder::ACTOR, $group->created_by, 'and says the roster wrote it');
        self::assertTrue($role->active, 'the role is active');
        self::assertSame([$role->id], $group->roles()->pluck('framework_roles.id')->all(), 'the group is mapped to the role');
        self::assertSame(
            [FixturePermission::DocumentsView->value],
            RolePermission::query()->where('role_id', $role->id)->where('active', true)->pluck('name')->all(),
            'the role holds the permission',
        );
    }

    public function testSeedingTwiceAddsNothing(): void
    {
        self::seeder()->seed();
        $before = $this->counts();

        self::seeder()->seed();

        self::assertSame($before, $this->counts(), 'every row is found by its natural key the second time');
    }

    public function testGrantsAddedThroughTheAdminUiSurvive(): void
    {
        self::seeder()->seed();
        $editor = Role::query()->where('name', FixtureRoster::EDITOR_ROLE)->firstOrFail();
        // Written through the models, as the administration pages write them.
        $permission = new RolePermission();
        $permission->role_id = $editor->id;
        $permission->name = FixturePermission::DocumentsEdit->value;
        $permission->active = true;
        $permission->created_by = 'jdoe';
        $permission->save();
        $reviewer = new Role();
        $reviewer->name = 'Reviewer';
        $reviewer->active = true;
        $reviewer->created_by = 'jdoe';
        $reviewer->save();

        self::seeder()->seed();

        self::assertSame(1, RolePermission::query()->where('name', FixturePermission::DocumentsEdit->value)->count(), 'a permission the roster does not mention is kept');
        self::assertSame(1, Role::query()->where('name', 'Reviewer')->count(), 'and so is a role');
    }

    public function testARenamedGroupKeepsItsName(): void
    {
        self::seeder()->seed();
        $this->db->table('framework_groups')->where('group_identifier', FixtureRoster::EDITORS_GROUP)->update(['name' => 'My Editors']);

        self::seeder()->seed();

        self::assertSame('My Editors', Group::query()->where('group_identifier', FixtureRoster::EDITORS_GROUP)->value('name'), 'the local label is the developer\'s to change');
    }

    public function testALabelTakenByAnotherGroupFallsBackToTheIdentifier(): void
    {
        $this->db->table('framework_groups')->insert(['name' => FixtureRoster::EDITORS_LABEL, 'group_identifier' => 'iu:other', 'active' => true, 'created_by' => 'jdoe']);

        self::seeder()->seed();

        self::assertSame(FixtureRoster::EDITORS_GROUP, Group::query()->where('group_identifier', FixtureRoster::EDITORS_GROUP)->value('name'), 'names are unique, so the identifier stands in');
    }

    public function testADeactivatedGrantIsReactivated(): void
    {
        self::seeder()->seed();
        foreach (['framework_groups', 'framework_roles', 'framework_role_permissions'] as $table) {
            $this->db->table($table)->update(['active' => false]);
        }

        self::seeder()->seed();

        $group = Group::query()->where('group_identifier', FixtureRoster::EDITORS_GROUP)->firstOrFail();
        self::assertTrue($group->active, 'the roster\'s group is active again');
        self::assertSame(PersonaGrantSeeder::ACTOR, $group->updated_by, 'and says who changed it');
        self::assertTrue(Role::query()->where('name', FixtureRoster::EDITOR_ROLE)->firstOrFail()->active, 'so is its role');
        self::assertSame(1, RolePermission::query()->where('name', FixturePermission::DocumentsView->value)->where('active', true)->count(), 'and its permission');
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $counts = [];

        foreach (['framework_groups', 'framework_roles', 'framework_groups_roles', 'framework_role_permissions'] as $table) {
            $counts[$table] = $this->db->table($table)->count();
        }

        return $counts;
    }

    private static function seeder(): PersonaGrantSeeder
    {
        $editors = new PersonaGroup(FixtureRoster::EDITORS_GROUP, FixtureRoster::EDITORS_LABEL);

        return new PersonaGrantSeeder(new PersonaRoster(
            [new Persona(FixtureRoster::EDITOR, username: FixtureRoster::EDITOR_USERNAME, name: FixtureRoster::EDITOR_NAME, groups: [$editors])],
            grants: [new RoleGrant(FixtureRoster::EDITOR_ROLE, groups: [$editors], permissions: [FixturePermission::DocumentsView])],
        ));
    }
}
