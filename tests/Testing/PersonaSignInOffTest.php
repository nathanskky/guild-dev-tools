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
use Throwable;

/**
 * An IU Login application shaped like guild/starter's, whose phpunit.xml.dist
 * does not turn persona sign-in on. Loading it fails inside its own IU Login
 * configuration, and the kit says which line turns persona sign-in on.
 */
#[CoversClass(ApplicationUnderTest::class)]
#[UsesClass(Visit::class)]
#[UsesClass(TestDatabase::class)]
#[UsesClass(PersonaSignInExtension::class)]
#[UsesClass(ArraySessionStore::class)]
final class PersonaSignInOffTest extends TestCase
{
    use VisitsApplication;

    private const string FLAG = 'PERSONA_SIGN_IN_ENABLED';

    private const array OIDC = ['OIDC_ISSUER', 'OIDC_CLIENT_ID', 'OIDC_CLIENT_SECRET', 'OIDC_REDIRECT_URI'];

    /** @var array<string, mixed> */
    private array $savedEnv = [];

    private string|false $savedFlag = false;

    protected function setUp(): void
    {
        // This repository's phpunit.xml.dist turns the flag on; this application's tests do not.
        $this->savedEnv = $_ENV;
        $this->savedFlag = getenv(self::FLAG);
        putenv(self::FLAG);
        unset($_ENV[self::FLAG]);

        foreach (self::OIDC as $name) {
            unset($_ENV[$name]);
        }
    }

    protected function tearDown(): void
    {
        $_ENV = $this->savedEnv;
        putenv($this->savedFlag === false ? self::FLAG : self::FLAG . '=' . $this->savedFlag);
    }

    protected function applicationBasePath(): string
    {
        return dirname(__DIR__) . '/Fixture/persona-off';
    }

    public function testWithNoIuLoginSettingsTheKitSaysHowToTurnPersonaSignInOn(): void
    {
        self::assertKitAsksForTheEnvLine(fn () => $this->asGuest()->get('/'));
    }

    public function testWithTheSecretBlankedForTestsTheKitSaysHowToTurnPersonaSignInOn(): void
    {
        // Inside a container started for real IU Login, with phpunit.xml.dist forcing the secret blank.
        $_ENV['OIDC_ISSUER'] = 'https://idp.login.iu.edu';
        $_ENV['OIDC_CLIENT_ID'] = 'starter';
        $_ENV['OIDC_CLIENT_SECRET'] = '';
        $_ENV['OIDC_REDIRECT_URI'] = 'https://localhost:8443/signin-oidc';

        self::assertKitAsksForTheEnvLine(fn () => $this->asGuest()->get('/'));
    }

    private static function assertKitAsksForTheEnvLine(callable $visit): void
    {
        try {
            $visit();
        } catch (ConfigurationException $exception) {
            self::assertStringContainsString(
                '<env name="PERSONA_SIGN_IN_ENABLED" value="true" force="true"/>',
                $exception->getMessage(),
                'the message names the line to add',
            );
            self::assertInstanceOf(Throwable::class, $exception->getPrevious(), 'and keeps the application\'s own error');

            return;
        }

        self::fail('the visit was expected to fail');
    }
}
