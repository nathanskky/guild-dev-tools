<?php

declare(strict_types=1);

namespace Guild\DevTools\Picker;

use Guild\DevTools\Grant\PersonaGrantSeeder;
use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\DevTools\SignIn\ReturnPath;
use Guild\DevTools\SignIn\SignedInPersona;
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The persona picker at /_dev/personas: where a guest is sent to sign in,
 * and where a developer switches persona or simulates a Grouper outage.
 *
 * @internal
 */
final readonly class PersonaPickerController
{
    public function __construct(
        private SignedInPersona $signedIn,
        private PersonaSignIn $signIn,
        private DirectoryOutage $outage,
        private PersonaGrantSeeder $seeder,
        private PersonaPickerPage $page,
    ) {
    }

    /**
     * GET /_dev/personas. Seeds the roster's grants first, so what the page
     * says each persona may do is what the application will allow.
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $this->seeder->seed();

        $stored = $this->signedIn->storedValue();
        $current = $this->signIn->signedInPersona();
        $notice = $current === null && $stored !== null
            ? sprintf("Persona '%s' no longer exists in config/personas.php. You are signed out.", $stored)
            : null;

        $return = $request->getQueryParams()['return'] ?? null;

        return new HtmlResponse($this->page->render(
            $current,
            ReturnPath::from(is_string($return) ? $return : null),
            $this->outage->isOn(),
            notice: $notice,
        ));
    }
}
