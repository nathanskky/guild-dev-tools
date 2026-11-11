<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Picker;

use Guild\DevTools\Grant\PersonaGrantSeeder;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\DevTools\Picker\PersonaPickerController;
use Guild\DevTools\Picker\PersonaPickerPage;
use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\DevTools\SignIn\ReturnPath;
use Guild\DevTools\SignIn\SignedInPersona;
use Guild\DevTools\Test\Support\FixtureRoster;
use Guild\DevTools\Testing\TestDatabase;
use Guild\Framework\Admin\Csrf;
use Guild\Framework\Model\Role;
use Guild\Framework\Testing\ArraySessionStore;
use Laminas\Diactoros\ServerRequest;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonaPickerController::class)]
#[UsesClass(PersonaPickerPage::class)]
#[UsesClass(PersonaGrantSeeder::class)]
#[UsesClass(Persona::class)]
#[UsesClass(PersonaGroup::class)]
#[UsesClass(PersonaKey::class)]
#[UsesClass(PersonaRoster::class)]
#[UsesClass(RoleGrant::class)]
#[UsesClass(DirectoryOutage::class)]
#[UsesClass(PersonaSignIn::class)]
#[UsesClass(ReturnPath::class)]
#[UsesClass(SignedInPersona::class)]
#[UsesClass(TestDatabase::class)]
#[UsesClass(ArraySessionStore::class)]
#[UsesClass(Csrf::class)]
final class PersonaPickerControllerTest extends TestCase
{
    private ArraySessionStore $session;

    private TestHandler $log;

    protected function setUp(): void
    {
        TestDatabase::activate();
        TestDatabase::begin();
        $this->session = new ArraySessionStore();
        $this->log = new TestHandler();
    }

    protected function tearDown(): void
    {
        TestDatabase::rollBack();
    }

    public function testAMissingPersonaIsReportedAndSignedOut(): void
    {
        $this->session->put('guild_dev_tools_persona', 'ghost');

        $response = $this->controller()->show(self::request('GET', '/_dev/personas'));

        self::assertSame(200, $response->getStatusCode(), 'the picker still opens');
        self::assertStringContainsString(
            "Persona 'ghost' no longer exists in config/personas.php. You are signed out.",
            html_entity_decode((string) $response->getBody(), ENT_QUOTES | ENT_HTML5),
            'and says why the developer was signed out',
        );
        self::assertNull(new SignedInPersona($this->session)->storedValue(), 'the stale key is gone');
    }

    public function testASignedInPersonaIsNotReportedMissing(): void
    {
        new SignedInPersona($this->session)->remember(new PersonaKey(FixtureRoster::EDITOR));

        $body = (string) $this->controller()->show(self::request('GET', '/_dev/personas'))->getBody();

        self::assertStringNotContainsString('no longer exists', $body, 'a persona in the roster is not missing');
        self::assertStringContainsString('Signed in', $body, 'it is the one signed in');
    }

    public function testLoadingThePickerSeedsTheGrants(): void
    {
        $controller = $this->controller();
        $controller->show(self::request('GET', '/_dev/personas'));
        $role = Role::query()->where('name', FixtureRoster::EDITOR_ROLE)->firstOrFail();
        $role->active = false;
        $role->updated_by = 'jdoe';
        $role->save();

        $controller->show(self::request('GET', '/_dev/personas'));

        self::assertTrue($role->refresh()->active, 'opening the picker puts the roster\'s grants back');
    }

    public function testAReturnOffThisSiteBecomesTheRoot(): void
    {
        $body = (string) $this->controller()->show(self::request('GET', '/_dev/personas?return=%2F%2Fevil.example'))->getBody();

        self::assertStringNotContainsString('evil.example', $body, 'another site is never a return target');
        self::assertStringContainsString('name="return" value="/"', $body, 'the root is');
    }

    public function testAReturnOnThisSiteIsKept(): void
    {
        $body = (string) $this->controller()->show(self::request('GET', '/_dev/personas?return=%2Fdocuments'))->getBody();

        self::assertStringContainsString('name="return" value="/documents"', $body, 'the page the visitor wanted');
    }

    public function testTheOutageToggleIsLogged(): void
    {
        $this->controller()->setOutage(self::request('POST', '/_dev/personas/outage', ['outage' => 'on']));
        $this->controller()->setOutage(self::request('POST', '/_dev/personas/outage', ['outage' => 'off']));

        self::assertTrue($this->log->hasNoticeThatContains('Directory outage simulation turned on.'), 'starting an outage is logged');
        self::assertTrue($this->log->hasNoticeThatContains('Directory outage simulation turned off.'), 'and so is ending it');
    }

    public function testSigningInIsLogged(): void
    {
        $this->controller()->signIn(self::request('POST', '/_dev/personas', ['persona' => FixtureRoster::EDITOR]));

        self::assertTrue(
            $this->log->hasNoticeThatContains(sprintf("Signed in as persona '%s' (%s).", FixtureRoster::EDITOR, FixtureRoster::EDITOR_USERNAME)),
            'every switch from the picker is logged with key and username',
        );
    }

    public function testALongInvalidPersonaValueIsShownShortened(): void
    {
        $value = str_repeat('Not a key ', 20);

        $response = $this->controller()->signIn(self::request('POST', '/_dev/personas', ['persona' => $value]));
        $body = html_entity_decode((string) $response->getBody(), ENT_QUOTES | ENT_HTML5);

        self::assertSame(422, $response->getStatusCode(), 'an invalid key is refused');
        self::assertStringContainsString(sprintf("No persona '%s' in config/personas.php.", substr($value, 0, 40)), $body, 'naming what was sent, cut to a key\'s length');
        self::assertStringNotContainsString($value, $body, 'not all of it');
    }

    public function testAMissingPersonaFieldIsRejected(): void
    {
        $response = $this->controller()->signIn(self::request('POST', '/_dev/personas', []));

        self::assertSame(422, $response->getStatusCode(), 'a form without a persona is refused');
        self::assertStringContainsString("No persona '' in config/personas.php.", html_entity_decode((string) $response->getBody(), ENT_QUOTES | ENT_HTML5), 'and says so');
    }

    private function controller(): PersonaPickerController
    {
        $roster = self::roster();
        $signedIn = new SignedInPersona($this->session);
        $csrf = new Csrf($this->session);
        $logger = new Logger('test', [$this->log]);

        return new PersonaPickerController(
            $roster,
            $signedIn,
            new PersonaSignIn($roster, $signedIn, $this->session, $csrf, $logger),
            new DirectoryOutage($this->session),
            new PersonaGrantSeeder($roster),
            new PersonaPickerPage($roster, $csrf),
            $logger,
        );
    }

    private static function roster(): PersonaRoster
    {
        $roster = require dirname(__DIR__) . '/Fixture/app/config/personas.php';
        self::assertInstanceOf(PersonaRoster::class, $roster);

        return $roster;
    }

    /**
     * @param  array<string, string>|null  $body
     */
    private static function request(string $method, string $path, ?array $body = null): ServerRequest
    {
        $request = new ServerRequest(uri: 'http://localhost' . $path, method: $method);
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query);

        return $body === null ? $request : $request->withParsedBody($body);
    }
}
