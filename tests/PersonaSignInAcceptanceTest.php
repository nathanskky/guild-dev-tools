<?php

declare(strict_types=1);

namespace Guild\DevTools\Test;

use Guild\DevTools\Grant\PersonaGrantSeeder;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\PersonaSignInExtension;
use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\DevTools\Test\Support\FixturePermission;
use Guild\Framework\Application;
use Guild\Framework\Authorization\IdentitySource;
use Guild\Framework\Router;
use Guild\Framework\Session\SessionStore;
use Guild\Framework\Testing\ArraySessionStore;
use Guild\Framework\Testing\FrameworkSchema;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;

/**
 * The fixture application booted with persona sign-in, with no IU Login or
 * Grouper settings and no System Admin label, driven through the real Router,
 * Gate and grant tables.
 *
 * Integration coverage across many classes, so it declares none; the units
 * are covered by their own tests.
 */
#[CoversNothing]
final class PersonaSignInAcceptanceTest extends TestCase
{
    public function testPersonasDriveRealAuthorization(): void
    {
        $guest = $this->visit('http://localhost/documents', null);
        self::assertSame(302, $guest->getStatusCode(), 'a guest is challenged');
        self::assertSame('/_dev/personas?return=%2Fdocuments', $guest->getHeaderLine('Location'), 'by the persona picker');

        $editor = $this->visit('http://localhost/documents', 'editor');
        self::assertSame(200, $editor->getStatusCode(), 'the editor holds the seeded grant');
        self::assertSame('documents', (string) $editor->getBody(), 'and sees the page');

        self::assertSame(403, $this->visit('http://localhost/documents', 'newcomer')->getStatusCode(), 'a persona in no group is refused');
        self::assertSame(503, $this->visit('http://localhost/documents', 'outage')->getStatusCode(), 'a persona Grouper cannot answer for gets 503');
        self::assertSame(200, $this->visit('http://localhost/framework/authorization', 'admin')->getStatusCode(), 'the roster\'s System Admin group opens the administration pages');
        self::assertSame(403, $this->visit('http://localhost/framework/authorization', 'editor')->getStatusCode(), 'which are closed to everyone else');
        self::assertSame(503, $this->visit('http://localhost/documents', 'editor', outage: true)->getStatusCode(), 'with the outage toggle on and nothing cached, the editor gets 503');
        self::assertSame(404, $this->visit('https://example.org/documents', 'editor')->getStatusCode(), 'a request to any other host is not found, signed in or not');
    }

    private function visit(string $uri, ?string $persona, bool $outage = false): ResponseInterface
    {
        $app = Application::configure(__DIR__ . '/Fixture/app', [new PersonaSignInExtension(['PERSONA_SIGN_IN_ENABLED' => 'true'])])
            ->withLogger(new NullLogger())
            ->enableAutoWiring()
            ->addIlluminateDatabase()
            ->addAuthentication()
            ->addAuthorization(IdentitySource::Oidc, FixturePermission::class)
            ->addRouting()
            ->create();

        $request = new ServerRequest(uri: $uri, method: 'GET');
        $app->addShared(ServerRequestInterface::class, $request, overwrite: true);
        $app->addShared(SessionStore::class, new ArraySessionStore(), overwrite: true);

        $capsule = $app->get(Capsule::class);
        self::assertInstanceOf(Capsule::class, $capsule);
        $connection = $capsule->getConnection();
        self::assertInstanceOf(Connection::class, $connection);
        FrameworkSchema::migrate($connection);

        $seeder = $app->get(PersonaGrantSeeder::class);
        self::assertInstanceOf(PersonaGrantSeeder::class, $seeder);
        $seeder->seed();

        if ($persona !== null) {
            $roster = $app->get(PersonaRoster::class);
            $signIn = $app->get(PersonaSignIn::class);
            self::assertInstanceOf(PersonaRoster::class, $roster);
            self::assertInstanceOf(PersonaSignIn::class, $signIn);
            $chosen = $roster->find(new PersonaKey($persona));
            self::assertNotNull($chosen, $persona . ' is in the fixture roster');
            $signIn->signInAs($chosen);
        }

        if ($outage) {
            $toggle = $app->get(DirectoryOutage::class);
            self::assertInstanceOf(DirectoryOutage::class, $toggle);
            $toggle->turnOn();
        }

        $router = $app->get(Router::class);
        self::assertInstanceOf(Router::class, $router);

        return $router->dispatch($request);
    }
}
