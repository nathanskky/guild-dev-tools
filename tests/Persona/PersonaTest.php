<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Persona;

use Guild\DevTools\Persona\DirectoryStatus;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaKey;
use Guild\Framework\Authorization\Identity\Identity;
use Guild\Framework\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Persona::class)]
#[UsesClass(PersonaKey::class)]
#[UsesClass(PersonaGroup::class)]
final class PersonaTest extends TestCase
{
    public function testTheIdentityCarriesTheUsernameClaim(): void
    {
        $persona = new Persona('editor', username: 'persona-editor', name: 'Erin Editor', attributes: ['email' => 'erin@example.edu']);

        self::assertEquals(
            new Identity('persona-editor', ['email' => 'erin@example.edu', 'username' => 'persona-editor', 'name' => 'Erin Editor']),
            $persona->identity,
            'the identity looks like IU Login userinfo, with username taken from the one source',
        );
    }

    public function testAKeyMayBeGivenAsAString(): void
    {
        $persona = new Persona('editor', username: 'persona-editor', name: 'Erin Editor');

        self::assertTrue(new PersonaKey('editor')->equals($persona->key), 'the string becomes a validated key');
        self::assertSame([], $persona->groups, 'a persona is in no groups unless given some');
        self::assertSame(DirectoryStatus::Available, $persona->directory, 'and Grouper answers for it unless told otherwise');
    }

    public function testABlankUsernameIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Persona 'editor' has a blank username. Give it one, e.g. 'persona-editor'.");

        new Persona('editor', username: ' ', name: 'Erin Editor');
    }

    public function testABlankNameIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage("Persona 'editor' has a blank name. Give it the display name IU Login would release, e.g. 'Erin Editor'.");

        new Persona('editor', username: 'persona-editor', name: '');
    }

    public function testTheUsernameCannotAlsoComeFromAttributes(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "Persona 'editor' sets 'username' in attributes:. Use the username: argument; it is the only source of the username claim."
        );

        new Persona('editor', username: 'persona-editor', name: 'Erin Editor', attributes: ['username' => 'jdoe']);
    }

    public function testTheNameCannotAlsoComeFromAttributes(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "Persona 'editor' sets 'name' in attributes:. Use the name: argument; it is the only source of the name claim."
        );

        new Persona('editor', username: 'persona-editor', name: 'Erin Editor', attributes: ['name' => 'Someone Else']);
    }
}
