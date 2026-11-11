<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Support;

use Guild\Framework\Authorization\Gate;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The fixture application's documents: viewing them needs
 * FixturePermission::DocumentsView, storing one DocumentsEdit.
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

    /**
     * Storing a document: only people who hold FixturePermission::DocumentsEdit.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $this->gate->authorize(FixturePermission::DocumentsEdit);
        $body = $request->getParsedBody();
        $title = is_array($body) && is_string($body['title'] ?? null) ? $body['title'] : '';

        return new TextResponse('stored ' . $title, 201);
    }
}
