<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\SignIn;

use Guild\DevTools\Persona\DirectoryStatus;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\PersonaGroupDirectory;
use Guild\Framework\Authorization\Membership\GroupDirectory;
use Guild\Framework\Testing\ArraySessionStore;
use Guild\Framework\Testing\GroupDirectoryContract;
use Guild\Grouper\GroupMembership;
use Guild\Grouper\GrouperUnavailable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(PersonaGroupDirectory::class)]
#[CoversClass(DirectoryOutage::class)]
#[UsesClass(GroupDirectoryContract::class)]
#[UsesClass(ArraySessionStore::class)]
#[UsesClass(Persona::class)]
#[UsesClass(PersonaKey::class)]
#[UsesClass(PersonaGroup::class)]
#[UsesClass(PersonaRoster::class)]
final class PersonaGroupDirectoryTest extends GroupDirectoryContract
{
    public function testAnUnavailablePersonaIsAnOutage(): void
    {
        $outage = new Persona('outage', username: 'persona-outage', name: 'Grouper Down', directory: DirectoryStatus::Unavailable);
        $roster = new PersonaRoster([$outage]);

        $result = new PersonaGroupDirectory($roster, new DirectoryOutage(new ArraySessionStore()))->groupsFor($outage->username);

        self::assertInstanceOf(GrouperUnavailable::class, $result, 'the persona exists to show the 503 path');
        self::assertSame("persona 'outage' is configured with DirectoryStatus::Unavailable", $result->reason, 'the logged reason says why');
    }

    public function testTheOutageToggleMakesEveryoneUnavailable(): void
    {
        $outage = new DirectoryOutage(new ArraySessionStore());
        $editor = new Persona('editor', username: 'persona-editor', name: 'Erin Editor', groups: [new PersonaGroup('iu:x', 'X Editors')]);
        $newcomer = new Persona('newcomer', username: 'persona-newcomer', name: 'Nico Newcomer');
        $directory = new PersonaGroupDirectory(new PersonaRoster([$editor, $newcomer]), $outage);

        $outage->turnOn();
        $editorDuring = $directory->groupsFor($editor->username);
        $newcomerDuring = $directory->groupsFor($newcomer->username);
        $outage->turnOff();
        $after = $directory->groupsFor($editor->username);

        self::assertInstanceOf(GrouperUnavailable::class, $editorDuring, 'with the toggle on, Grouper is down for a persona with groups');
        self::assertInstanceOf(GrouperUnavailable::class, $newcomerDuring, 'and for one without');
        self::assertSame("persona sign-in's directory outage toggle is on", $editorDuring->reason, 'and the reason names the toggle');
        self::assertInstanceOf(GroupMembership::class, $after, 'turning it off brings Grouper back');
    }

    public function testTheToggleIsOffInAFreshSession(): void
    {
        self::assertFalse(new DirectoryOutage(new ArraySessionStore())->isOn(), 'Grouper is up until a developer says otherwise');
    }

    #[Override]
    protected function directoryWhere(string $username, string $identifier, string $label): GroupDirectory
    {
        return new PersonaGroupDirectory(self::rosterWith($username, $identifier, $label), new DirectoryOutage(new ArraySessionStore()));
    }

    #[Override]
    protected function directoryWithout(string $username): GroupDirectory
    {
        return new PersonaGroupDirectory(self::rosterWith('contract-someone', 'iu:y', 'Y'), new DirectoryOutage(new ArraySessionStore()));
    }

    #[Override]
    protected function directoryDuringOutage(): GroupDirectory
    {
        $outage = new DirectoryOutage(new ArraySessionStore());
        $outage->turnOn();

        return new PersonaGroupDirectory(self::rosterWith('contract-member', 'iu:x', 'X'), $outage);
    }

    private static function rosterWith(string $username, string $identifier, string $label): PersonaRoster
    {
        return new PersonaRoster(
            [new Persona('member', username: $username, name: 'Member', groups: [new PersonaGroup($identifier, $label)])],
            usernamePrefix: '',
        );
    }
}
