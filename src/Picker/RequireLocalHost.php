<?php

declare(strict_types=1);

namespace Guild\DevTools\Picker;

use Guild\DevTools\SignIn\LocalHost;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Answers 404 to any request not addressed to this machine, so the picker
 * does not exist under a real host name. Runs before the CSRF check, so a
 * request from elsewhere learns nothing about the forms.
 *
 * @internal
 */
final readonly class RequireLocalHost implements MiddlewareInterface
{
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return LocalHost::is($request) ? $handler->handle($request) : new EmptyResponse(404);
    }
}
