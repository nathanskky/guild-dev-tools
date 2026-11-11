<?php

declare(strict_types=1);

namespace Guild\DevTools\Picker;

use Guild\DevTools\Grant\PersonaGrantSeeder;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\DevTools\SignIn\ReturnPath;
use Guild\DevTools\SignIn\SignedInPersona;
use Guild\Framework\Exception\ConfigurationException;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * The persona picker at /_dev/personas: where a guest is sent to sign in,
 * and where a developer switches persona or simulates a Grouper outage.
 *
 * @internal
 */
final readonly class PersonaPickerController
{
    /** How much of a refused persona value the error repeats: a key's maximum length. */
    private const int SHOWN_LENGTH = 40;

    public function __construct(
        private PersonaRoster $roster,
        private SignedInPersona $signedIn,
        private PersonaSignIn $signIn,
        private DirectoryOutage $outage,
        private PersonaGrantSeeder $seeder,
        private PersonaPickerPage $page,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * GET /_dev/personas. Seeds the roster's grants first, so what the page
     * says each persona may do is what the application will allow.
     */
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        $this->seeder->seed();

        // Reading the persona first lets this request notice a missing one too.
        $current = $this->signIn->signedInPersona();
        $missing = $this->signedIn->takeMissing();
        $notice = $missing === null
            ? null
            : sprintf("Persona '%s' no longer exists in config/personas.php. You are signed out.", $missing);

        $return = $request->getQueryParams()['return'] ?? null;

        return new HtmlResponse($this->page->render(
            $current,
            ReturnPath::from(is_string($return) ? $return : null),
            $this->outage->isOn(),
            notice: $notice,
        ));
    }

    /**
     * POST /_dev/personas: sign in as the chosen persona and go back to the
     * page the visitor wanted. PersonaSignIn::signInAs() replaces the session
     * ID and CSRF token and logs the switch.
     */
    public function signIn(ServerRequestInterface $request): ResponseInterface
    {
        $value = self::field($request, 'persona');
        $persona = null;

        try {
            $persona = $this->roster->find(new PersonaKey($value));
        } catch (ConfigurationException) {
            // Not a valid key, so no persona has it.
        }

        if ($persona === null) {
            return $this->refuse($request, sprintf(
                "No persona '%s' in config/personas.php.",
                mb_substr($value, 0, self::SHOWN_LENGTH),
            ));
        }

        $this->signIn->signInAs($persona);

        return self::back($request);
    }

    /**
     * POST /_dev/personas/outage: start or end a simulated Grouper outage
     * for this visitor.
     */
    public function setOutage(ServerRequestInterface $request): ResponseInterface
    {
        $setting = self::field($request, 'outage');

        if ($setting === 'on') {
            $this->outage->turnOn();
        } elseif ($setting === 'off') {
            $this->outage->turnOff();
        } else {
            return $this->refuse($request, sprintf(
                "Unknown outage setting '%s'. Use on or off.",
                mb_substr($setting, 0, self::SHOWN_LENGTH),
            ));
        }

        $this->logger->notice(sprintf('Directory outage simulation turned %s.', $setting));

        return self::back($request);
    }

    private function refuse(ServerRequestInterface $request, string $error): ResponseInterface
    {
        return new HtmlResponse($this->page->render(
            $this->signIn->signedInPersona(),
            ReturnPath::from(self::field($request, 'return')),
            $this->outage->isOn(),
            error: $error,
        ), 422);
    }

    private static function back(ServerRequestInterface $request): ResponseInterface
    {
        return new RedirectResponse(ReturnPath::from(self::field($request, 'return')), 303);
    }

    /**
     * A posted form field as a string; '' when it is missing or not a string.
     */
    private static function field(ServerRequestInterface $request, string $name): string
    {
        $body = $request->getParsedBody();
        $value = is_array($body) ? ($body[$name] ?? null) : null;

        return is_string($value) ? $value : '';
    }
}
