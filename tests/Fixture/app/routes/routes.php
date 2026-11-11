<?php

declare(strict_types=1);

use Guild\DevTools\Test\Support\DocumentsController;
use Guild\Framework\Middleware\RequireSignIn;
use Guild\Framework\Router;

return static function (Router $router): void {
    $router->map('GET', '/documents', [DocumentsController::class, 'index'])->lazyMiddleware(RequireSignIn::class);
};
