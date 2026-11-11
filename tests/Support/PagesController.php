<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Support;

use Guild\Framework\Authorization\Gate;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * The fixture application's HTML pages, which the persona banner is
 * inserted into.
 */
final readonly class PagesController
{
    public function __construct(private Gate $gate)
    {
    }

    /** A page anyone may see. */
    public function welcome(): ResponseInterface
    {
        return new HtmlResponse(self::page('Welcome'));
    }

    /** The documents page: only people who hold FixturePermission::DocumentsView. */
    public function documents(): ResponseInterface
    {
        $this->gate->authorize(FixturePermission::DocumentsView);

        return new HtmlResponse(self::page('Documents'));
    }

    /** Part of a page, as an htmx request would fetch: no <body>. */
    public function fragment(): ResponseInterface
    {
        return new HtmlResponse('<p>fragment</p>');
    }

    /** A page that states its own length. */
    public function sized(): ResponseInterface
    {
        $html = self::page('Sized');

        return new HtmlResponse($html, headers: ['Content-Length' => (string) strlen($html)]);
    }

    private static function page(string $heading): string
    {
        return sprintf(
            '<!doctype html><html><head><title>%1$s</title></head><body class="fixture"><h1>%1$s</h1></body></html>',
            $heading,
        );
    }
}
