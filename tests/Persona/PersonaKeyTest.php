<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Persona;

use Guild\DevTools\Persona\PersonaKey;
use Guild\Framework\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonaKey::class)]
final class PersonaKeyTest extends TestCase
{
    #[DataProvider('validKeys')]
    public function testASlugIsAValidKey(string $key): void
    {
        self::assertSame($key, new PersonaKey($key)->value, 'the key is kept as given');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validKeys(): iterable
    {
        yield 'a word' => ['editor'];
        yield 'with a hyphen and a digit' => ['grouper-down2'];
        yield 'forty characters' => ['a' . str_repeat('b', 39)];
    }

    #[DataProvider('invalidKeys')]
    public function testAnythingElseIsRejectedWithTheRule(string $key): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "Persona key '{$key}' is not valid. Use lower-case letters, digits and hyphens, starting with a letter, "
            . "at most 40 characters (e.g. 'editor')."
        );

        new PersonaKey($key);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'upper case' => ['Editor'];
        yield 'leading digit' => ['1st'];
        yield 'a space' => ['has space'];
        yield 'forty-one characters' => [str_repeat('a', 41)];
        yield 'empty' => [''];
        yield 'a trailing newline' => ["editor\n"];
    }

    public function testKeysWithTheSameValueAreEqual(): void
    {
        self::assertTrue(new PersonaKey('editor')->equals(new PersonaKey('editor')), 'same value, same persona');
        self::assertFalse(new PersonaKey('editor')->equals(new PersonaKey('viewer')), 'different value, different persona');
    }
}
