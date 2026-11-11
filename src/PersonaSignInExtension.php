<?php

declare(strict_types=1);

namespace Guild\DevTools;

use Error;
use Guild\DevTools\Grant\PersonaGrantSeeder;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Picker\PersonaBanner;
use Guild\DevTools\Picker\PersonaPickerController;
use Guild\DevTools\Picker\PersonaPickerPage;
use Guild\DevTools\Picker\RequireLocalHost;
use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\PersonaGroupDirectory;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\DevTools\SignIn\SignedInPersona;
use Guild\Framework\Admin\Csrf;
use Guild\Framework\Application;
use Guild\Framework\ApplicationBuilder;
use Guild\Framework\Exception\ConfigurationException;
use Guild\Framework\Extension\ApplicationExtension;
use Guild\Framework\Middleware\VerifyCsrfToken;
use Guild\Framework\Router;
use League\Route\RouteGroup;
use Guild\Framework\Session\SessionStore;
use Override;
use Psr\Log\LoggerInterface;

/**
 * Turns on persona sign-in when PERSONA_SIGN_IN_ENABLED is true (or 1): people sign in
 * as personas from config/personas.php, their groups come from the same
 * roster, and the roster names the System Admin group.
 *
 * Discovered by guild/framework because this package has Composer type
 * guild-extension. It is required with require-dev, so a --no-dev build
 * does not contain it. It refuses to start alongside real IU Login or
 * Grouper credentials.
 */
final class PersonaSignInExtension implements ApplicationExtension
{
    private const string FLAG = 'PERSONA_SIGN_IN_ENABLED';

    private const array CREDENTIALS = [
        'OIDC_CLIENT_SECRET' => ['real IU Login credentials', 'real IU Login'],
        'GROUPER_PASSWORD' => ['real Grouper credentials', 'real IU Login and Grouper'],
    ];

    /** @var array<string, string> */
    private readonly array $environment;

    /** Loaded by configure() when persona sign-in is on; null while it is off. */
    private ?PersonaRoster $roster = null;

    /**
     * @param  array<string, string>|null  $environment  Null reads the process environment and $_ENV (mergeEnvironment()).
     */
    public function __construct(?array $environment = null)
    {
        $this->environment = $environment ?? self::mergeEnvironment(getenv(), $_ENV);
    }

    #[Override]
    public function configure(ApplicationBuilder $builder): void
    {
        if (! self::isEnabledIn($this->environment)) {
            return;
        }

        $this->refuseRealCredentials();

        $roster = $this->loadRoster((string) $builder->path('config') . '/personas.php');

        $builder->useSignInMethod(PersonaSignIn::class)->useGroupDirectory(PersonaGroupDirectory::class);

        if ($roster->systemAdminGroup !== null) {
            $builder->useSystemAdminGroup($roster->systemAdminGroup->label);
        }

        $this->roster = $roster;
    }

    #[Override]
    public function boot(Application $app): void
    {
        $roster = $this->roster;

        if ($roster === null) {
            return;
        }

        // Every factory resolves the roster, so a test that overrides it reaches all of them.
        $app->addShared(PersonaRoster::class, $roster);
        $app->addShared(SignedInPersona::class, static fn (): SignedInPersona => new SignedInPersona(self::service($app, SessionStore::class)));
        $app->addShared(DirectoryOutage::class, static fn (): DirectoryOutage => new DirectoryOutage(self::service($app, SessionStore::class)));
        $app->addShared(PersonaSignIn::class, static fn (): PersonaSignIn => new PersonaSignIn(
            self::service($app, PersonaRoster::class),
            self::service($app, SignedInPersona::class),
            self::service($app, SessionStore::class),
            self::service($app, Csrf::class),
            self::service($app, LoggerInterface::class),
        ));
        $app->addShared(PersonaGroupDirectory::class, static fn (): PersonaGroupDirectory => new PersonaGroupDirectory(
            self::service($app, PersonaRoster::class),
            self::service($app, DirectoryOutage::class),
        ));
        $app->addShared(
            PersonaGrantSeeder::class,
            static fn (): PersonaGrantSeeder => new PersonaGrantSeeder(self::service($app, PersonaRoster::class)),
        );
        $app->addShared(RequireLocalHost::class, static fn (): RequireLocalHost => new RequireLocalHost());
        $app->addShared(PersonaPickerPage::class, static fn (): PersonaPickerPage => new PersonaPickerPage(
            self::service($app, PersonaRoster::class),
            self::service($app, Csrf::class),
        ));
        $app->addShared(PersonaPickerController::class, static fn (): PersonaPickerController => new PersonaPickerController(
            self::service($app, PersonaRoster::class),
            self::service($app, SignedInPersona::class),
            self::service($app, PersonaSignIn::class),
            self::service($app, DirectoryOutage::class),
            self::service($app, PersonaGrantSeeder::class),
            self::service($app, PersonaPickerPage::class),
            self::service($app, LoggerInterface::class),
        ));

        $app->addShared(PersonaBanner::class, static fn (): PersonaBanner => new PersonaBanner(
            self::service($app, PersonaSignIn::class),
            self::service($app, DirectoryOutage::class),
            self::service($app, Csrf::class),
        ));

        $router = self::service($app, Router::class);
        self::mountPicker($router);
        // Prepended, so it wraps the AuthorizationFailureResponder that addAuthorization() already added.
        $router->lazyPrependMiddleware(PersonaBanner::class);
    }

    /**
     * The picker's routes under /_dev/personas. The host check runs before
     * the CSRF check, so another host gets 404, never the expired-form page.
     */
    private static function mountPicker(Router $router): void
    {
        $router->group(PersonaSignIn::PICKER_PATH, static function (RouteGroup $group): void {
            $group->map('GET', '/', [PersonaPickerController::class, 'show']);
            $group->map('POST', '/', [PersonaPickerController::class, 'signIn']);
            $group->map('POST', '/outage', [PersonaPickerController::class, 'setOutage']);
        })
            ->lazyMiddleware(RequireLocalHost::class)
            ->lazyMiddleware(VerifyCsrfToken::class);
    }

    /**
     * Whether $environment turns persona sign-in on: PERSONA_SIGN_IN_ENABLED
     * is true or 1, in any case. 1 because phpunit.xml's <env value="true">
     * reaches PHP as 1.
     *
     * @param  array<string, string>  $environment  As mergeEnvironment() returns it.
     */
    public static function isEnabledIn(array $environment): bool
    {
        return in_array(strtolower(trim($environment[self::FLAG] ?? '')), ['true', '1'], true);
    }

    private function refuseRealCredentials(): void
    {
        foreach (self::CREDENTIALS as $variable => [$credentials, $mode]) {
            if (trim($this->environment[$variable] ?? '') !== '') {
                throw new ConfigurationException(sprintf(
                    'Persona sign-in is enabled, but %s is set. Persona sign-in never runs alongside %s. Start the stack '
                    . 'with `docker/up-personas.sh` for personas, or `docker/up-iu-services.sh` for %s.',
                    $variable,
                    $credentials,
                    $mode,
                ));
            }
        }
    }

    private function loadRoster(string $file): PersonaRoster
    {
        if (! is_file($file)) {
            throw new ConfigurationException(
                'Persona sign-in is enabled, but config/personas.php was not found. Create it, returning a PersonaRoster; '
                . "guild/starter's config/personas.php is an example."
            );
        }

        try {
            $roster = require $file;
        } catch (Error $error) {
            throw new ConfigurationException(
                'Problem encountered while reading config/personas.php: ' . $error->getMessage(),
                previous: $error,
            );
        }

        if (! $roster instanceof PersonaRoster) {
            throw new ConfigurationException('config/personas.php must return a PersonaRoster.');
        }

        return $roster;
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

    /**
     * The process environment over $_ENV, where PHPUnit's <env> also writes.
     * For the flag the process wins, so a start script's choice stands. For a
     * credential, a non-blank value in either source counts, because the
     * application may read the one this check would otherwise not see.
     *
     * @param  array<mixed>  $process  As getenv() returns it.
     * @param  array<mixed>  $env  As $_ENV holds it.
     * @return array<string, string>
     */
    public static function mergeEnvironment(array $process, array $env): array
    {
        $merged = [];

        foreach ([$env, $process] as $source) {
            foreach ($source as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $merged[$name] = $value;
                }
            }
        }

        foreach (array_keys(self::CREDENTIALS) as $credential) {
            $fromEnv = $env[$credential] ?? null;

            if (is_string($fromEnv) && trim($fromEnv) !== '' && trim($merged[$credential] ?? '') === '') {
                $merged[$credential] = $fromEnv;
            }
        }

        return $merged;
    }
}
