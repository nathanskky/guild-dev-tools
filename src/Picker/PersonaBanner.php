<?php

declare(strict_types=1);

namespace Guild\DevTools\Picker;

use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\LocalHost;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\Framework\Admin\Csrf;
use Guild\Rivet\Html\Html;
use Laminas\Diactoros\Stream;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Puts a banner at the top of every HTML page while persona sign-in is on,
 * saying who the visitor is signed in as, with controls to switch persona,
 * sign out and simulate a Grouper outage. Nobody can forget they are not
 * themselves.
 *
 * Prepended to the router, so it wraps the framework's 403 and 503 pages
 * too: a persona refused a page still sees who they are and how to switch.
 * Pages on another host and responses that are not HTML, or have no <body>,
 * are left as they are.
 *
 * @internal
 */
final readonly class PersonaBanner implements MiddlewareInterface
{
    public const string ID = 'guild-persona-banner';

    /** Inline, so the banner is visible on pages that do not load Rivet's CSS. */
    private const string STYLE = 'position:sticky;top:0;z-index:10000;display:flex;flex-wrap:wrap;align-items:center;'
        . 'gap:.5rem 1rem;padding:.5rem 1rem;background:#fff3cd;color:#1e1e1e;border-bottom:2px solid #b8860b;'
        . 'font:14px/1.4 system-ui,sans-serif';

    private const string FORM_STYLE = 'display:inline;margin:0';

    public function __construct(private PersonaSignIn $signIn, private DirectoryOutage $outage, private Csrf $csrf)
    {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (! LocalHost::is($request) || ! self::isHtml($response)) {
            return $response;
        }

        $html = self::insertAfterBodyTag((string) $response->getBody(), $this->banner($request));

        if ($html === null) {
            return $response;
        }

        $body = new Stream('php://temp', 'wb+');
        $body->write($html);
        $body->rewind();

        return $response->withBody($body)->withoutHeader('Content-Length');
    }

    /**
     * $html with $banner right after its first <body> tag, or null when it
     * has none.
     */
    public static function insertAfterBodyTag(string $html, string $banner): ?string
    {
        if (preg_match('/<body(?:\s[^>]*)?>/i', $html, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $end = $match[0][1] + strlen($match[0][0]);

        return substr($html, 0, $end) . $banner . substr($html, $end);
    }

    private static function isHtml(ResponseInterface $response): bool
    {
        $mediaType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));

        return $mediaType === 'text/html';
    }

    private function banner(ServerRequestInterface $request): string
    {
        $persona = $this->signIn->signedInPersona();
        $outageOn = $this->outage->isOn();
        $here = self::pathOf($request);

        $status = Html::el('span');

        if ($persona === null) {
            $status->text('Persona sign-in: not signed in');
        } else {
            $status->text('Persona sign-in: signed in as ')
                ->children(Html::el('strong')->text($persona->name))
                ->text(sprintf(' (%s)', $persona->key->value));
        }

        $banner = Html::el('div')->attr('id', self::ID)->attr('role', 'status')->attr('style', self::STYLE)->children(
            $status,
            Html::el('a')->attr('href', PersonaSignIn::PICKER_PATH . '?return=' . rawurlencode($here))->text('Switch persona'),
        );

        if ($persona !== null) {
            $banner->children(Html::el('form')->attr('method', 'post')->attr('action', '/framework/sign-out')->attr('style', self::FORM_STYLE)->children(
                $this->csrf->field(),
                self::button('Sign out'),
            ));
        }

        if ($outageOn) {
            $banner->children(Html::el('strong')->text('Grouper outage simulated'));
        }

        $banner->children(Html::el('form')->attr('method', 'post')->attr('action', PersonaPickerPage::OUTAGE_PATH)->attr('style', self::FORM_STYLE)->children(
            $this->csrf->field(),
            Html::el('input')->attr('type', 'hidden')->attr('name', 'outage')->attr('value', $outageOn ? 'off' : 'on'),
            Html::el('input')->attr('type', 'hidden')->attr('name', 'return')->attr('value', $here),
            self::button($outageOn ? 'End the Grouper outage' : 'Simulate a Grouper outage'),
        ));

        return $banner->render();
    }

    private static function button(string $label): Html
    {
        return Html::el('button')->attr('type', 'submit')->class('rvt-button', 'rvt-button--secondary', 'rvt-button--small')->text($label);
    }

    private static function pathOf(ServerRequestInterface $request): string
    {
        $query = $request->getUri()->getQuery();

        return $request->getUri()->getPath() . ($query === '' ? '' : '?' . $query);
    }
}
