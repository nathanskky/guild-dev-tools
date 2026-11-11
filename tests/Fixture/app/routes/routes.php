<?php

declare(strict_types=1);

use Guild\DevTools\Test\Support\DocumentsController;
use Guild\Framework\Middleware\RequireSignIn;
use Guild\Framework\Middleware\VerifyCsrfToken;
use Guild\Framework\Router;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

return static function (Router $router): void {
    $router->map('GET', '/documents', [DocumentsController::class, 'index'])->lazyMiddleware(RequireSignIn::class);
    $router->map('POST', '/documents', [DocumentsController::class, 'store'])
        ->lazyMiddlewares([VerifyCsrfToken::class, RequireSignIn::class]);

    // Answers with the X-Test request header, so tests can see which headers a visit sent.
    $router->map('GET', '/echo-header', static fn (ServerRequestInterface $request): ResponseInterface => new TextResponse(
        $request->getHeaderLine('X-Test'),
    ));
};
