<?php

declare(strict_types=1);

namespace Guild\DevTools\SignIn;

use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\Framework\Admin\Csrf;
use Guild\Framework\Authentication\SignInMethod;
use Guild\Framework\Authorization\Identity\Identity;
use Guild\Framework\Session\SessionStore;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Signs people in as a persona from the roster instead of through IU Login.
 * A guest is sent to the persona picker; requests not addressed to this
 * machine get 404.
 */
final readonly class PersonaSignIn implements SignInMethod
{
    public const string PICKER_PATH = '/_dev/personas';

    public function __construct(
        private PersonaRoster $roster,
        private SignedInPersona $signedIn,
        private SessionStore $session,
        private Csrf $csrf,
        private LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function read(): ?Identity
    {
        return $this->signedInPersona()?->identity;
    }

    /**
     * The persona this visitor is signed in as, or null for a guest. A key
     * the roster no longer has is cleared, with a notice, and reads as a guest.
     */
    public function signedInPersona(): ?Persona
    {
        $key = $this->signedIn->key();
        $persona = $key === null ? null : $this->roster->find($key);

        if ($persona !== null) {
            return $persona;
        }

        $stored = $this->signedIn->storedValue();

        if ($stored !== null) {
            $this->signedIn->forget();
            $this->logger->notice(sprintf(
                "Persona '%s' is no longer in config/personas.php; the session was signed out.",
                $stored,
            ));
        }

        return null;
    }

    #[Override]
    public function requireSignIn(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        if (! LocalHost::is($request)) {
            return new EmptyResponse(404);
        }

        if ($this->read() === null) {
            return $this->challenge($request);
        }

        return $next->handle($request);
    }

    #[Override]
    public function challenge(ServerRequestInterface $request): ResponseInterface
    {
        if (! LocalHost::is($request)) {
            return new EmptyResponse(404);
        }

        return new RedirectResponse(self::PICKER_PATH . '?return=' . rawurlencode(self::returnTarget($request)));
    }

    #[Override]
    public function signOut(ServerRequestInterface $request): ResponseInterface
    {
        $key = $this->signedIn->key();
        $this->signedIn->forget();
        $this->session->regenerateId();

        if ($key !== null) {
            $this->logger->notice(sprintf("Signed out of persona '%s'.", $key->value));
        }

        return new RedirectResponse(self::PICKER_PATH);
    }

    /**
     * Sign this visitor in as $persona, with a new session ID and CSRF token.
     * Used by the persona picker and the test kit.
     */
    public function signInAs(Persona $persona): void
    {
        $this->signedIn->remember($persona->key);
        $this->session->regenerateId();
        $this->csrf->rotate();
        $this->logger->notice(sprintf("Signed in as persona '%s' (%s).", $persona->key->value, $persona->username));
    }

    /**
     * Where to send the visitor once they have chosen: the page they asked
     * for, when it is a GET to a page of this site, else the root.
     */
    private static function returnTarget(ServerRequestInterface $request): string
    {
        if (! in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)) {
            return '/';
        }

        $path = $request->getUri()->getPath();
        $query = $request->getUri()->getQuery();

        return ReturnPath::from($query === '' ? $path : $path . '?' . $query);
    }
}
