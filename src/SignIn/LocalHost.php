<?php

declare(strict_types=1);

namespace Guild\DevTools\SignIn;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Whether a request was addressed to this machine. Persona sign-in answers
 * nothing else, as a last line of defence should it ever be reachable from
 * a real host name.
 *
 * @internal
 */
final class LocalHost
{
    private const array HOSTS = ['localhost', '127.0.0.1', '::1'];

    public static function is(ServerRequestInterface $request): bool
    {
        $host = trim(strtolower($request->getUri()->getHost()), '[]');

        return in_array($host, self::HOSTS, true);
    }
}
