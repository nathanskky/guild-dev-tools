<?php

declare(strict_types=1);

namespace Guild\DevTools\Testing;

use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\SignIn\SignedInPersona;
use Guild\Framework\Application;
use Guild\Framework\Exception\ConfigurationException;
use Guild\Framework\Router;
use Guild\Framework\Testing\ArraySessionStore;
use Laminas\Diactoros\ServerRequest;
use NoDiscard;

/**
 * One simulated visitor to the application: signed in as a persona, or a
 * guest, with their own session.
 *
 * Each request loads the application afresh from config/app.php, as a real
 * request would; the session carries across the visit's requests, and the
 * database across the whole test.
 */
final readonly class Visit
{
    private const string ORIGIN = 'http://localhost';

    /**
     * @param  PersonaKey|Persona|null  $persona  A persona from the roster, a one-off persona, or null for a guest.
     * @param  array<non-empty-string, string>  $headers
     */
    private function __construct(
        private string $basePath,
        private PersonaKey|Persona|null $persona,
        private ArraySessionStore $session,
        private array $headers = [],
    ) {
    }

    /**
     * A visitor signed in as $persona: a key from config/personas.php, or a
     * Persona built for one test, which joins the roster for this visit.
     *
     * @internal Tests use VisitsApplication::signedInAs().
     */
    #[NoDiscard]
    public static function signedInAs(string $basePath, string|Persona $persona): self
    {
        $persona = is_string($persona) ? new PersonaKey($persona) : $persona;
        $session = new ArraySessionStore();
        new SignedInPersona($session)->remember($persona instanceof Persona ? $persona->key : $persona);

        return new self($basePath, $persona, $session);
    }

    /**
     * @internal Tests use VisitsApplication::asGuest().
     */
    #[NoDiscard]
    public static function asGuest(string $basePath): self
    {
        return new self($basePath, null, new ArraySessionStore());
    }

    /**
     * @param  string  $uri  A path on this site, or an absolute URL to visit another host.
     */
    public function get(string $uri): VisitResponse
    {
        return $this->send('GET', $uri);
    }

    private function send(string $method, string $uri): VisitResponse
    {
        $absolute = str_starts_with($uri, '/') ? self::ORIGIN . $uri : $uri;
        parse_str((string) parse_url($absolute, PHP_URL_QUERY), $query);

        $request = new ServerRequest(uri: $absolute, method: $method, headers: $this->headers, queryParams: $query);
        $app = ApplicationUnderTest::boot(
            $this->basePath,
            $this->session,
            $request,
            $this->persona instanceof Persona ? $this->persona : null,
        );

        if ($this->persona instanceof PersonaKey) {
            $this->requireInRoster($app, $this->persona);
        }

        $router = $app->get(Router::class);

        if (! $router instanceof Router) {
            throw new ConfigurationException('The container did not return a Router. Call addRouting() in config/app.php.');
        }

        return new VisitResponse($router->dispatch($request));
    }

    private function requireInRoster(Application $app, PersonaKey $key): void
    {
        $roster = $app->get(PersonaRoster::class);

        if ($roster instanceof PersonaRoster && $roster->find($key) !== null) {
            return;
        }

        throw new ConfigurationException(sprintf(
            "Persona '%s' is not in config/personas.php. Add it there, or pass a Persona to signedInAs() for a one-off.",
            $key->value,
        ));
    }
}
