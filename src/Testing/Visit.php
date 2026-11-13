<?php

declare(strict_types=1);

namespace Guild\DevTools\Testing;

use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\SignedInPersona;
use Guild\Framework\Admin\Csrf;
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
 * database across the whole test. Two visits have separate sessions.
 * withHeaders() and duringDirectoryOutage() return a copy with a copy of the
 * session as it is then, and leave the original unchanged.
 *
 * An exception the application doesn't handle fails the test with that
 * exception; it isn't turned into a 500 response.
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
     * Send a GET.
     *
     * @param  string  $uri  A path starting with '/', sent to http://localhost, or an absolute URL, sent as given.
     *
     * @throws ConfigurationException When persona sign-in is off, config/app.php is missing, or the visit's
     *                                 persona key isn't in config/personas.php.
     */
    public function get(string $uri): VisitResponse
    {
        return $this->send('GET', $uri, null);
    }

    /**
     * Submit a form: a POST with $data as the form body. The session's CSRF
     * token is added as _csrf unless $data already has one, so a test can
     * send a wrong token on purpose.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ConfigurationException When persona sign-in is off, config/app.php is missing, or the visit's
     *                                 persona key isn't in config/personas.php.
     */
    public function post(string $uri, array $data = []): VisitResponse
    {
        return $this->send('POST', $uri, $data);
    }

    /**
     * A PUT with $data as the form body, and the CSRF token added as post() adds it.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ConfigurationException When persona sign-in is off, config/app.php is missing, or the visit's
     *                                 persona key isn't in config/personas.php.
     */
    public function put(string $uri, array $data = []): VisitResponse
    {
        return $this->send('PUT', $uri, $data);
    }

    /**
     * A DELETE with $data as the form body, and the CSRF token added as post() adds it.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ConfigurationException When persona sign-in is off, config/app.php is missing, or the visit's
     *                                 persona key isn't in config/personas.php.
     */
    public function delete(string $uri, array $data = []): VisitResponse
    {
        return $this->send('DELETE', $uri, $data);
    }

    /**
     * This visitor with Grouper down. The copy takes the session as it is
     * now, so membership an earlier request cached is still served within
     * its TTL, as in production; start the outage before the first request
     * to see the 503.
     */
    #[NoDiscard]
    public function duringDirectoryOutage(): self
    {
        $copy = clone($this, ['session' => clone $this->session]);
        new DirectoryOutage($copy->session)->turnOn();

        return $copy;
    }

    /**
     * This visitor sending $headers, over any it already sends, with every request.
     *
     * @param  array<non-empty-string, string>  $headers
     */
    #[NoDiscard]
    public function withHeaders(array $headers): self
    {
        return clone($this, ['session' => clone $this->session, 'headers' => [...$this->headers, ...$headers]]);
    }

    /**
     * @param  array<string, mixed>|null  $data  The form body; null for a GET.
     */
    private function send(string $method, string $uri, ?array $data): VisitResponse
    {
        $absolute = str_starts_with($uri, '/') ? self::ORIGIN . $uri : $uri;
        parse_str((string) parse_url($absolute, PHP_URL_QUERY), $query);

        $request = new ServerRequest(uri: $absolute, method: $method, headers: $this->headers, queryParams: $query);

        if ($data !== null) {
            // The token lives in the visit's session, the one the application will read.
            $data += [Csrf::FIELD => new Csrf($this->session)->token()];
            $request = $request->withParsedBody($data);
        }

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
