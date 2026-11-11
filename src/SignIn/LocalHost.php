<?php

declare(strict_types=1);

namespace Guild\DevTools\SignIn;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Whether a request was addressed to this machine. Persona sign-in answers
 * nothing else, so an application reached under a real host name never offers
 * it. The host comes from the client's Host header, so this stops accidents,
 * not a crafted request; keeping dev-tools out of production builds does that.
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
