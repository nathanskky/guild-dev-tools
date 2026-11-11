<?php

declare(strict_types=1);

namespace Guild\DevTools\Testing;

use Guild\DevTools\Grant\PersonaGrantSeeder;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\PersonaSignInExtension;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\Framework\Application;
use Guild\Framework\Exception\ConfigurationException;
use Guild\Framework\Session\SessionStore;
use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Loads the application from its config/app.php for one request of a
 * visit, and swaps in the visit's session and request, the test database
 * and a silent logger before anything resolves them.
 *
 * @internal
 */
final class ApplicationUnderTest
{
    private function __construct()
    {
    }

    public static function boot(
        string $basePath,
        SessionStore $session,
        ServerRequestInterface $request,
        ?Persona $inlinePersona,
    ): Application {
        $app = self::load(rtrim($basePath, '/\\') . '/config/app.php');

        $app->addShared(SessionStore::class, $session, overwrite: true);
        $app->addShared(ServerRequestInterface::class, $request, overwrite: true);
        $app->addShared(LoggerInterface::class, new NullLogger(), overwrite: true);
        $app->addShared(Capsule::class, TestDatabase::activate(), overwrite: true);

        // Read from the claim, not by resolving the sign-in method: that
        // would build it with the roster before an inline persona joins it.
        if ($app->claimedSlots->signInMethod?->value !== PersonaSignIn::class) {
            throw self::personaSignInIsOff();
        }

        if ($inlinePersona !== null) {
            $roster = self::service($app, PersonaRoster::class);
            $app->addShared(PersonaRoster::class, new PersonaRoster(
                [...$roster->personas, $inlinePersona],
                $roster->grants,
                $roster->systemAdminGroup,
                $roster->usernamePrefix,
            ), overwrite: true);
        }

        if (TestDatabase::seedOnce()) {
            self::service($app, PersonaGrantSeeder::class)->seed();
        }

        return $app;
    }

    /**
     * Require config/app.php with error_log pointed at the null device, so
     * the notices create() logs before the logger can be replaced stay out
     * of the test output.
     */
    private static function load(string $file): Application
    {
        if (! is_file($file)) {
            throw new ConfigurationException(sprintf(
                'No config/app.php under %s. The test kit loads the application from it; override '
                . 'applicationBasePath() in the test case to point at the application root.',
                dirname($file, 2),
            ));
        }

        $previous = ini_get('error_log');
        ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');

        try {
            $app = require $file;
        } catch (Throwable $failure) {
            // With persona sign-in off, an application fails inside its own IU
            // Login or Grouper settings, which tests do not have.
            if (! PersonaSignInExtension::isEnabledIn(PersonaSignInExtension::mergeEnvironment(getenv(), $_ENV))) {
                throw self::personaSignInIsOff($failure);
            }

            throw $failure;
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        if (! $app instanceof Application) {
            throw new ConfigurationException('config/app.php must return the Application that ->create() builds.');
        }

        return $app;
    }

    private static function personaSignInIsOff(?Throwable $previous = null): ConfigurationException
    {
        return new ConfigurationException(
            'The test kit signs in through persona sign-in, which is off. Turn it on for tests in phpunit.xml.dist: '
            . '<env name="PERSONA_SIGN_IN_ENABLED" value="true" force="true"/>.',
            previous: $previous,
        );
    }

    /**
     * @template T of object
     * @param  class-string<T>  $class
     * @return T
     */
    private static function service(Application $app, string $class): object
    {
        $service = $app->get($class);

        if (! $service instanceof $class) {
            throw new ConfigurationException(sprintf('The container did not return a %s.', $class));
        }

        return $service;
    }
}
