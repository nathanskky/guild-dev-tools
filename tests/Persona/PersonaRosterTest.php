<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Persona;

use Guild\DevTools\Persona\DirectoryStatus;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\DevTools\Test\Support\FixturePermission;
use Guild\DevTools\Test\Support\FixtureRoster;
use Guild\Framework\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonaRoster::class)]
#[CoversClass(RoleGrant::class)]
#[UsesClass(Persona::class)]
#[UsesClass(PersonaKey::class)]
#[UsesClass(PersonaGroup::class)]
final class PersonaRosterTest extends TestCase
{
    public function testTheSpecExampleRosterBuilds(): void
    {
        $roster = self::exampleRoster();

        self::assertCount(4, $roster->personas, 'all four personas are kept');
        self::assertSame(FixtureRoster::ADMINS_LABEL, $roster->systemAdminGroup?->label, 'the roster names the System Admin group');
        self::assertSame([FixturePermission::DocumentsView], $roster->grants[0]->permissions, 'grants keep their permissions');
    }

    public function testAPersonaIsFoundByKeyAndByUsername(): void
    {
        $roster = self::exampleRoster();

        self::assertSame(FixtureRoster::EDITOR_NAME, $roster->find(new PersonaKey(FixtureRoster::EDITOR))?->name, 'by the key tests and the session use');
        self::assertSame(FixtureRoster::EDITOR_NAME, $roster->findByUsername(FixtureRoster::EDITOR_USERNAME)?->name, 'by the username the group directory is asked about');
        self::assertNull($roster->find(new PersonaKey('nobody')), 'an unknown key finds nothing');
        self::assertNull($roster->findByUsername('jdoe'), 'an unknown username finds nothing');
    }

    public function testAnEmptyPrefixAcceptsAnyUsername(): void
    {
        $roster = new PersonaRoster([new Persona('member', username: 'contract-member', name: 'Member')], usernamePrefix: '');

        self::assertNotNull($roster->findByUsername('contract-member'), 'a test roster may use its own usernames');
    }

    public function testARosterNeedsAPersona(): void
    {
        $this->expectRejection('config/personas.php declares no personas. Add at least one Persona to personas:.');

        new PersonaRoster([]);
    }

    public function testTwoPersonasCannotShareAKey(): void
    {
        $this->expectRejection("Two personas use the key 'editor'. Give each persona its own key.");

        new PersonaRoster([
            new Persona('editor', username: 'persona-editor', name: 'Erin Editor'),
            new Persona('editor', username: 'persona-other', name: 'Other Editor'),
        ]);
    }

    public function testTwoPersonasCannotShareAUsername(): void
    {
        $this->expectRejection("Personas 'editor' and 'writer' share the username 'persona-editor'. Give each its own username.");

        new PersonaRoster([
            new Persona('editor', username: 'persona-editor', name: 'Erin Editor'),
            new Persona('writer', username: 'persona-editor', name: 'Wren Writer'),
        ]);
    }

    public function testAUsernameWithoutThePrefixIsRejected(): void
    {
        $this->expectRejection(
            "Persona 'editor' has the username 'jdoe', which does not start with 'persona-'. Persona usernames must not be "
            . "real IU usernames; use e.g. 'persona-editor'."
        );

        new PersonaRoster([new Persona('editor', username: 'jdoe', name: 'Erin Editor')]);
    }

    public function testAGroupDeclaredWithTwoLabelsIsRejected(): void
    {
        $this->expectRejection("Group 'iu:x' is declared with two labels, 'A' and 'B'. Declare each group once and reuse the variable.");

        new PersonaRoster(
            [new Persona('editor', username: 'persona-editor', name: 'Erin Editor', groups: [new PersonaGroup('iu:x', 'A')])],
            grants: [new RoleGrant('Editor', groups: [new PersonaGroup('iu:x', 'B')])],
        );
    }

    public function testTwoGroupsCannotShareALabel(): void
    {
        $this->expectRejection("Groups 'iu:x' and 'iu:y' share the label 'Editors'. Labels must be unique, because the System Admin check matches on them.");

        new PersonaRoster(
            [new Persona('editor', username: 'persona-editor', name: 'Erin Editor', groups: [new PersonaGroup('iu:x', 'Editors')])],
            systemAdminGroup: new PersonaGroup('iu:y', 'Editors'),
        );
    }

    public function testTwoGrantsCannotNameTheSameRole(): void
    {
        $this->expectRejection("Two grants name the role 'Editor'. Merge them into one RoleGrant.");

        new PersonaRoster(
            [new Persona('editor', username: 'persona-editor', name: 'Erin Editor')],
            grants: [new RoleGrant('Editor'), new RoleGrant('Editor')],
        );
    }

    public function testABlankRoleIsRejected(): void
    {
        $this->expectRejection('A role grant has a blank role name. Name the role, e.g. \'Editor\'.');

        new RoleGrant(' ');
    }

    public function testGrantsForGivesTheGrantsOfThePersonasGroups(): void
    {
        $editors = new PersonaGroup('iu:roles:sys:acm:editors', 'Editors');
        $readers = new PersonaGroup('iu:roles:sys:acm:readers', 'Readers');
        $editor = new Persona('editor', username: 'persona-editor', name: 'Erin Editor', groups: [$editors]);
        $newcomer = new Persona('newcomer', username: 'persona-newcomer', name: 'Nico Newcomer');
        $roster = new PersonaRoster([$editor, $newcomer], grants: [
            new RoleGrant('Editor', groups: [$editors]),
            new RoleGrant('Reader', groups: [$readers, $editors]),
            new RoleGrant('Auditor', groups: [$readers]),
        ]);

        self::assertSame(
            ['Editor', 'Reader'],
            array_map(static fn (RoleGrant $grant): string => $grant->role, $roster->grantsFor($editor)),
            'every grant naming one of the persona\'s groups, in roster order',
        );
        self::assertSame([], $roster->grantsFor($newcomer), 'a persona in no group holds no grant');
    }

    public function testIsSystemAdminMatchesTheSystemAdminGroup(): void
    {
        $roster = self::exampleRoster();
        $admin = $roster->find(new PersonaKey(FixtureRoster::ADMIN));
        $editor = $roster->find(new PersonaKey(FixtureRoster::EDITOR));
        self::assertNotNull($admin);
        self::assertNotNull($editor);

        self::assertTrue($roster->isSystemAdmin($admin), 'a persona in the System Admin group is a System Admin');
        self::assertFalse($roster->isSystemAdmin($editor), 'one outside it is not');
        self::assertFalse(
            new PersonaRoster([$admin])->isSystemAdmin($admin),
            'a roster that names no System Admin group has no System Admin',
        );
    }

    private function expectRejection(string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);
    }

    private static function exampleRoster(): PersonaRoster
    {
        $editors = new PersonaGroup(FixtureRoster::EDITORS_GROUP, FixtureRoster::EDITORS_LABEL);
        $admins = new PersonaGroup(FixtureRoster::ADMINS_GROUP, FixtureRoster::ADMINS_LABEL);

        return new PersonaRoster(
            personas: [
                new Persona(FixtureRoster::EDITOR, username: FixtureRoster::EDITOR_USERNAME, name: FixtureRoster::EDITOR_NAME, groups: [$editors]),
                new Persona(FixtureRoster::NEWCOMER, username: FixtureRoster::NEWCOMER_USERNAME, name: FixtureRoster::NEWCOMER_NAME),
                new Persona(FixtureRoster::ADMIN, username: FixtureRoster::ADMIN_USERNAME, name: FixtureRoster::ADMIN_NAME, groups: [$admins]),
                new Persona(FixtureRoster::OUTAGE, username: FixtureRoster::OUTAGE_USERNAME, name: FixtureRoster::OUTAGE_NAME, directory: DirectoryStatus::Unavailable),
            ],
            grants: [
                new RoleGrant(FixtureRoster::EDITOR_ROLE, groups: [$editors], permissions: [FixturePermission::DocumentsView]),
            ],
            systemAdminGroup: $admins,
        );
    }
}
