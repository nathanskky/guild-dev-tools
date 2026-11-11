<?php

declare(strict_types=1);

namespace Guild\DevTools\Test;

use Guild\DevTools\Grant\PersonaGrantSeeder;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\DevTools\PersonaSignInExtension;
use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\PersonaGroupDirectory;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\DevTools\SignIn\SignedInPersona;
use Guild\DevTools\Test\Support\FixturePermission;
use Guild\Framework\Application;
use Guild\Framework\ApplicationBuilder;
use Guild\Framework\Authentication\SignInMethod;
use Guild\Framework\Authorization\IdentitySource;
use Guild\Framework\Authorization\Membership\GroupDirectory;
use Guild\Framework\Exception\ConfigurationException;
use Guild\Framework\Session\SessionStore;
use Guild\Framework\Testing\ArraySessionStore;
use Guild\Grouper\GroupMembership;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PersonaSignInExtension::class)]
#[UsesClass(Persona::class)]
#[UsesClass(PersonaGroup::class)]
#[UsesClass(PersonaKey::class)]
#[UsesClass(PersonaRoster::class)]
#[UsesClass(RoleGrant::class)]
#[UsesClass(PersonaSignIn::class)]
#[UsesClass(PersonaGroupDirectory::class)]
#[UsesClass(SignedInPersona::class)]
#[UsesClass(DirectoryOutage::class)]
#[UsesClass(PersonaGrantSeeder::class)]
#[UsesClass(ArraySessionStore::class)]
final class PersonaSignInExtensionTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/Fixture/app';

    private const array ENABLED = ['PERSONA_SIGN_IN_ENABLED' => 'true'];

    /**
     * @param  array<string, string>  $environment
     */
    #[DataProvider('disabled')]
    public function testWithTheFlagOffNoSlotIsClaimed(array $environment): void
    {
        $slots = self::configure($environment)->create()->claimedSlots;

        self::assertNull($slots->signInMethod, 'the app signs people in as it would without dev-tools');
        self::assertNull($slots->groupDirectory, 'groups come from Grouper');
        self::assertNull($slots->systemAdminGroup, 'and the System Admin label from the app\'s own configuration');
    }

    /**
     * @param  array<string, string>  $environment
     */
    #[DataProvider('disabled')]
    public function testWithTheFlagOffIuLoginSettingsAreStillRequired(array $environment): void
    {
        $builder = self::configure($environment);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('config/authentication.php was not found');

        $builder->addAuthentication();
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function disabled(): iterable
    {
        yield 'unset' => [[]];
        yield 'empty' => [['PERSONA_SIGN_IN_ENABLED' => '']];
        yield 'false' => [['PERSONA_SIGN_IN_ENABLED' => 'false']];
    }

    public function testAClientSecretStopsBoot(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'Persona sign-in is enabled, but OIDC_CLIENT_SECRET is set. Persona sign-in never runs alongside real IU Login '
            . 'credentials. Start the stack with `docker/up-personas.sh` for personas, or `docker/up-iu-services.sh` for '
            . 'real IU Login.'
        );

        self::configure(self::ENABLED + ['OIDC_CLIENT_SECRET' => 'secret']);
    }

    public function testAGrouperPasswordStopsBoot(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            'Persona sign-in is enabled, but GROUPER_PASSWORD is set. Persona sign-in never runs alongside real Grouper '
            . 'credentials. Start the stack with `docker/up-personas.sh` for personas, or `docker/up-iu-services.sh` for '
            . 'real IU Login and Grouper.'
        );

        self::configure(self::ENABLED + ['GROUPER_PASSWORD' => 'secret']);
    }

    public function testWhitespaceSecretsCountAsUnset(): void
    {
        $app = self::configure(self::ENABLED + ['OIDC_CLIENT_SECRET' => ' ', 'GROUPER_PASSWORD' => "\n"])
            ->addAuthentication()
            ->create();

        self::assertInstanceOf(PersonaSignIn::class, $app->get(SignInMethod::class), 'a blank secret is not a credential');
    }

    public function testTheFlagIsCaseInsensitive(): void
    {
        $app = self::configure(['PERSONA_SIGN_IN_ENABLED' => 'TRUE'])->addAuthentication()->create();

        self::assertInstanceOf(PersonaSignIn::class, $app->get(SignInMethod::class), 'TRUE turns it on as true does');
    }

    public function testTheEnvSuperglobalCounts(): void
    {
        self::assertSame(
            'true',
            PersonaSignInExtension::mergeEnvironment([], ['PERSONA_SIGN_IN_ENABLED' => 'true'])['PERSONA_SIGN_IN_ENABLED'] ?? null,
            'PHPUnit\'s <env> sets $_ENV, and that counts',
        );
    }

    public function testTheProcessEnvironmentWinsForTheFlag(): void
    {
        self::assertSame(
            'false',
            PersonaSignInExtension::mergeEnvironment(['PERSONA_SIGN_IN_ENABLED' => 'false'], ['PERSONA_SIGN_IN_ENABLED' => 'true'])['PERSONA_SIGN_IN_ENABLED'] ?? null,
            'a start script\'s choice is not overridden by $_ENV',
        );
    }

    public function testASecretInEitherSourceStopsBoot(): void
    {
        $environment = PersonaSignInExtension::mergeEnvironment(
            self::ENABLED + ['OIDC_CLIENT_SECRET' => ''],
            ['OIDC_CLIENT_SECRET' => 'secret'],
        );

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Persona sign-in is enabled, but OIDC_CLIENT_SECRET is set.');

        Application::configure(self::FIXTURE, [new PersonaSignInExtension($environment)]);
    }

    public function testAMissingRosterFileStopsBoot(): void
    {
        $basePath = sys_get_temp_dir() . '/guild-dev-tools-no-roster-' . bin2hex(random_bytes(6));
        mkdir($basePath . '/config', recursive: true);

        try {
            $this->expectException(ConfigurationException::class);
            $this->expectExceptionMessage(
                "Persona sign-in is enabled, but config/personas.php was not found. Create it, returning a PersonaRoster; "
                . "guild/starter's config/personas.php is an example."
            );

            Application::configure($basePath, [new PersonaSignInExtension(self::ENABLED)]);
        } finally {
            rmdir($basePath . '/config');
            rmdir($basePath);
        }
    }

    public function testARosterFileReturningSomethingElseStopsBoot(): void
    {
        $basePath = sys_get_temp_dir() . '/guild-dev-tools-bad-roster-' . bin2hex(random_bytes(6));
        mkdir($basePath . '/config', recursive: true);
        file_put_contents($basePath . '/config/personas.php', "<?php\n\nreturn [];\n");

        try {
            $this->expectException(ConfigurationException::class);
            $this->expectExceptionMessage('config/personas.php must return a PersonaRoster.');

            Application::configure($basePath, [new PersonaSignInExtension(self::ENABLED)]);
        } finally {
            unlink($basePath . '/config/personas.php');
            rmdir($basePath . '/config');
            rmdir($basePath);
        }
    }

    public function testCasWithPersonaSignInStopsBoot(): void
    {
        $builder = self::configure(self::ENABLED)->addIlluminateDatabase();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            PersonaSignIn::class . ' was chosen as the sign-in method by ' . PersonaSignInExtension::class
            . ', but addAuthorization() was given IdentitySource::Cas.'
        );

        $builder->addAuthorization(IdentitySource::Cas, FixturePermission::class);
    }

    public function testEnabledTheAppBootsWithoutCredentials(): void
    {
        $app = self::configure(self::ENABLED)
            ->addIlluminateDatabase()
            ->addAuthentication()
            ->addAuthorization(IdentitySource::Oidc, FixturePermission::class)
            ->addRouting()
            ->create();

        self::assertInstanceOf(PersonaSignIn::class, $app->get(SignInMethod::class), 'people sign in as personas');
        self::assertInstanceOf(PersonaGroupDirectory::class, $app->get(GroupDirectory::class), 'and their groups come from the roster');
        self::assertInstanceOf(PersonaGrantSeeder::class, $app->get(PersonaGrantSeeder::class), 'and the seeder is ready for the picker and the test kit');
    }

    public function testAnOverriddenRosterReachesSignInAndTheDirectory(): void
    {
        $app = self::configure(self::ENABLED)
            ->addIlluminateDatabase()
            ->addAuthentication()
            ->addAuthorization(IdentitySource::Oidc, FixturePermission::class)
            ->create();
        $inline = new Persona('inline', username: 'persona-inline', name: 'Ines Inline', groups: [new PersonaGroup('iu:inline', 'Inline')]);
        $app->addShared(PersonaRoster::class, new PersonaRoster([$inline]), overwrite: true);
        $app->addShared(SessionStore::class, new ArraySessionStore(), overwrite: true);

        $signIn = $app->get(SignInMethod::class);
        self::assertInstanceOf(PersonaSignIn::class, $signIn);
        $signIn->signInAs($inline);
        $directory = $app->get(GroupDirectory::class);
        self::assertInstanceOf(GroupDirectory::class, $directory);
        $membership = $directory->groupsFor('persona-inline');

        self::assertSame('persona-inline', $signIn->read()?->username, 'a test kit can sign in as a one-off persona');
        self::assertInstanceOf(GroupMembership::class, $membership);
        self::assertCount(1, $membership->groups, 'and the directory knows its groups');
    }

    /**
     * @param  array<string, string>  $environment
     */
    private static function configure(array $environment): ApplicationBuilder
    {
        return Application::configure(self::FIXTURE, [new PersonaSignInExtension($environment)])->withLogger(new NullLogger());
    }
}
