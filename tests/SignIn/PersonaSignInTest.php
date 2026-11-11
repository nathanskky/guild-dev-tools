<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\SignIn;

use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\SignIn\LocalHost;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\DevTools\SignIn\ReturnPath;
use Guild\DevTools\SignIn\SignedInPersona;
use Guild\Framework\Admin\Csrf;
use Guild\Framework\Testing\ArraySessionStore;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(PersonaSignIn::class)]
#[CoversClass(SignedInPersona::class)]
#[UsesClass(LocalHost::class)]
#[UsesClass(ReturnPath::class)]
#[UsesClass(Persona::class)]
#[UsesClass(PersonaKey::class)]
#[UsesClass(PersonaRoster::class)]
#[UsesClass(ArraySessionStore::class)]
#[UsesClass(Csrf::class)]
final class PersonaSignInTest extends TestCase
{
    private const string EDITOR = 'editor';

    private const string EDITOR_USERNAME = 'persona-editor';

    private ArraySessionStore $session;

    private TestHandler $log;

    private PersonaRoster $roster;

    protected function setUp(): void
    {
        $this->session = new ArraySessionStore();
        $this->log = new TestHandler();
        $this->roster = new PersonaRoster([new Persona(self::EDITOR, username: self::EDITOR_USERNAME, name: 'Erin Editor')]);
    }

    public function testAGuestReadsAsNobody(): void
    {
        self::assertNull($this->signIn()->read(), 'nobody is signed in until a persona is chosen');
    }

    public function testSigningInMakesThePersonaTheIdentity(): void
    {
        $csrf = new Csrf($this->session);
        $tokenBefore = $csrf->token();
        $signIn = $this->signIn();

        $signIn->signInAs($this->editor());

        self::assertSame(self::EDITOR_USERNAME, $signIn->read()?->username, 'the persona is who is making requests');
        self::assertSame(1, $this->session->regenerations, 'a session ID known before the switch is worthless after');
        self::assertNotSame($tokenBefore, $csrf->token(), 'and so is a form issued before it');
        self::assertTrue(
            $this->log->hasRecord("Signed in as persona 'editor' (persona-editor).", Level::Notice),
            'every switch is logged',
        );
    }

    public function testAPersonaNoLongerInTheRosterReadsAsAGuest(): void
    {
        $this->session->put('guild_dev_tools_persona', 'retired');

        self::assertNull($this->signIn()->read(), 'a key the roster no longer has is a guest, not an error');
        self::assertNull($this->session->get('guild_dev_tools_persona'), 'the stale key is cleared');
        self::assertTrue(
            $this->log->hasRecord("Persona 'retired' is no longer in config/personas.php; the session was signed out.", Level::Notice),
            'and the developer is told why they were signed out',
        );
    }

    public function testSignedInPersonaIsTheRosterPersona(): void
    {
        $signIn = $this->signIn();
        self::assertNull($signIn->signedInPersona(), 'a guest has no persona');

        $signIn->signInAs($this->editor());

        self::assertSame($this->editor(), $signIn->signedInPersona(), 'the roster\'s own Persona, so the picker and banner show its details');
    }

    public function testSignedInPersonaForgetsAPersonaNoLongerInTheRoster(): void
    {
        $this->session->put('guild_dev_tools_persona', 'ghost');

        self::assertNull($this->signIn()->signedInPersona(), 'a key the roster no longer has is a guest');
        self::assertNull($this->session->get('guild_dev_tools_persona'), 'the stale key is cleared');
        self::assertCount(1, $this->log->getRecords(), 'one notice');
        self::assertTrue(
            $this->log->hasRecord("Persona 'ghost' is no longer in config/personas.php; the session was signed out.", Level::Notice),
            'naming the persona that went missing',
        );
    }

    public function testAnInvalidStoredKeyReadsAsAGuest(): void
    {
        $this->session->put('guild_dev_tools_persona', 'Not A Key');

        self::assertNull($this->signIn()->read(), 'whatever is in the session, a bad key is nobody');
    }

    public function testAGuestIsSentToThePickerWithTheReturnPath(): void
    {
        $response = $this->signIn()->requireSignIn(self::request('GET', 'http://localhost/documents?page=2'), self::handler());

        self::assertSame(302, $response->getStatusCode(), 'a guest is redirected');
        self::assertSame('/_dev/personas?return=%2Fdocuments%3Fpage%3D2', $response->getHeaderLine('Location'), 'to the picker, remembering the page');
    }

    public function testAProtocolRelativeTargetReturnsToTheRoot(): void
    {
        $request = self::request('GET', 'http://localhost/')->withUri(new Uri('http://localhost')->withPath('//evil.example/x'));

        self::assertReturnStaysOnThisSite($this->signIn()->challenge($request), 'a protocol-relative path never becomes an off-site return');
    }

    public function testABackslashTargetReturnsToTheRoot(): void
    {
        $request = self::request('GET', 'http://localhost/')->withUri(new Uri('http://localhost')->withPath('/\\evil.example/x'));

        self::assertReturnStaysOnThisSite($this->signIn()->challenge($request), 'browsers read /\\ as //, so it must not survive');
    }

    public function testAPostIsReturnedToTheRoot(): void
    {
        $response = $this->signIn()->challenge(self::request('POST', 'http://localhost/documents'));

        self::assertSame('/_dev/personas?return=%2F', $response->getHeaderLine('Location'), 'a form submission cannot be replayed as a GET');
    }

    public function testANonLocalHostGetsNotFound(): void
    {
        $signIn = $this->signIn();
        $signIn->signInAs($this->editor());
        $request = self::request('GET', 'https://example.org/documents');

        self::assertSame(404, $signIn->requireSignIn($request, self::handler())->getStatusCode(), 'even signed in, persona sign-in answers only this machine');
        self::assertSame(404, $signIn->challenge($request)->getStatusCode(), 'and asks no one elsewhere to choose a persona');
    }

    public function testASignedInVisitorReachesTheHandler(): void
    {
        $signIn = $this->signIn();
        $signIn->signInAs($this->editor());

        $response = $signIn->requireSignIn(self::request('GET', 'http://localhost/documents'), self::handler());

        self::assertSame('handled', (string) $response->getBody(), 'the route runs');
    }

    public function testSigningOutForgetsThePersona(): void
    {
        $signIn = $this->signIn();
        $signIn->signInAs($this->editor());

        $response = $signIn->signOut(self::request('POST', 'http://localhost/framework/sign-out'));

        self::assertNull($signIn->read(), 'the visitor is a guest again');
        self::assertSame(2, $this->session->regenerations, 'with a new session ID');
        self::assertSame('/_dev/personas', $response->getHeaderLine('Location'), 'and lands on the picker');
        self::assertTrue($this->log->hasRecord("Signed out of persona 'editor'.", Level::Notice), 'the sign-out is logged');
    }

    private static function assertReturnStaysOnThisSite(ResponseInterface $response, string $message): void
    {
        parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        $return = $query['return'] ?? null;

        self::assertIsString($return, $message);
        self::assertMatchesRegularExpression('#\A/(?![/\\\\])#', $return, $message);
    }

    private function signIn(): PersonaSignIn
    {
        return new PersonaSignIn(
            $this->roster,
            new SignedInPersona($this->session),
            $this->session,
            new Csrf($this->session),
            new Logger('test', [$this->log]),
        );
    }

    private function editor(): Persona
    {
        $editor = $this->roster->find(new PersonaKey(self::EDITOR));
        self::assertNotNull($editor);

        return $editor;
    }

    private static function request(string $method, string $uri): ServerRequestInterface
    {
        return new ServerRequest(uri: $uri, method: $method);
    }

    private static function handler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new TextResponse('handled');
            }
        };
    }
}
