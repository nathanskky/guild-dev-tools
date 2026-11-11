<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Support;

use Guild\Framework\Authorization\Gate;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * The fixture application's one page: it answers only to people who hold
 * FixturePermission::DocumentsView.
 */
final readonly class DocumentsController
{
    public function __construct(private Gate $gate)
    {
    }

    public function index(): ResponseInterface
    {
        $this->gate->authorize(FixturePermission::DocumentsView);

        return new TextResponse('documents');
    }
}
