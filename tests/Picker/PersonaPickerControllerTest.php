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

    private function controller(): PersonaPickerController
    {
        $roster = self::roster();
        $signedIn = new SignedInPersona($this->session);
        $csrf = new Csrf($this->session);
        $logger = new Logger('test', [$this->log]);

        return new PersonaPickerController(
            $signedIn,
            new PersonaSignIn($roster, $signedIn, $this->session, $csrf, $logger),
            new DirectoryOutage($this->session),
            new PersonaGrantSeeder($roster),
            new PersonaPickerPage($roster, $csrf),
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
