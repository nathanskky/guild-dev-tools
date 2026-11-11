<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Testing;

use Guild\DevTools\PersonaSignInExtension;
use Guild\DevTools\Testing\ApplicationUnderTest;
use Guild\DevTools\Testing\TestDatabase;
use Guild\DevTools\Testing\Visit;
use Guild\DevTools\Testing\VisitsApplication;
use Guild\Framework\Exception\ConfigurationException;
use Guild\Framework\Testing\ArraySessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * An IU Login application whose phpunit.xml.dist does not turn persona
 * sign-in on.
 */
#[CoversClass(ApplicationUnderTest::class)]
#[UsesClass(Visit::class)]
#[UsesClass(TestDatabase::class)]
#[UsesClass(PersonaSignInExtension::class)]
#[UsesClass(ArraySessionStore::class)]
final class PersonaSignInOffTest extends TestCase
{
    use VisitsApplication;

    protected function applicationBasePath(): string
    {
        return dirname(__DIR__) . '/Fixture/persona-off';
    }

    public function testTheKitSaysHowToTurnPersonaSignInOn(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/PERSONA_SIGN_IN_ENABLED.*force="true"/');

        $this->asGuest()->get('/');
    }
}
