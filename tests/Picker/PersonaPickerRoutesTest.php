<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Picker;

use Guild\DevTools\Picker\PersonaPickerController;
use Guild\DevTools\Picker\RequireLocalHost;
use Guild\DevTools\Test\Support\FixtureRoster;
use Guild\DevTools\Testing\VisitsApplication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The picker's routes on the fixture application, through the test kit.
 * The kit, the extension and the framework run for real; their own tests
 * cover them.
 */
#[CoversClass(PersonaPickerController::class)]
#[CoversClass(RequireLocalHost::class)]
final class PersonaPickerRoutesTest extends TestCase
{
    use VisitsApplication;

    protected function applicationBasePath(): string
    {
        return dirname(__DIR__) . '/Fixture/app';
    }

    public function testThePickerAnswersOnALocalHost(): void
    {
        $response = $this->asGuest()->get('/_dev/personas')->assertOk()->assertSee('Sign in as ' . FixtureRoster::EDITOR_NAME);

        self::assertStringStartsWith('text/html', $response->header('Content-Type'), 'a page for a browser');
    }

    public function testThePickerIsNotFoundOnAnotherHost(): void
    {
        $this->asGuest()->get('http://example.edu/_dev/personas')->assertNotFound()->assertDontSee(FixtureRoster::EDITOR_NAME);
        $this->asGuest()->get('/_dev/personas')->assertSee(FixtureRoster::EDITOR_NAME);
    }

    public function testChoosingAPersonaSignsInAndReturns(): void
    {
        $visit = $this->asGuest();
        $visit->get('/documents')->assertRedirectsToSignIn();

        $visit->post('/_dev/personas', ['persona' => FixtureRoster::EDITOR, 'return' => '/documents'])->assertRedirect('/documents');

        $visit->get('/documents')->assertOk()->assertSee('documents');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function returnsOffThisSite(): iterable
    {
        yield 'protocol-relative' => ['//evil.example'];
        yield 'absolute URL' => ['https://evil.example'];
        yield 'backslash' => ['/\\evil.example'];
        yield 'a tab browsers strip' => ["/\t/evil.example"];
        yield 'a newline' => ["/\n/evil.example"];
    }

    #[DataProvider('returnsOffThisSite')]
    public function testAReturnOffThisSiteLandsOnTheRoot(string $return): void
    {
        $this->asGuest()->post('/_dev/personas', ['persona' => FixtureRoster::EDITOR, 'return' => $return])->assertRedirect('/');
    }

    public function testAnUnknownPersonaIsRejected(): void
    {
        $visit = $this->asGuest();

        $response = $visit->post('/_dev/personas', ['persona' => 'ghost', 'return' => '/documents']);

        self::assertSame(422, $response->status(), 'the form is refused');
        $response->assertSee("No persona 'ghost' in config/personas.php.")->assertSee('Sign in as ' . FixtureRoster::EDITOR_NAME);
        $visit->get('/documents')->assertRedirectsToSignIn();
    }

    public function testAStaleFormIsRejected(): void
    {
        $visit = $this->asGuest();

        $visit->post('/_dev/personas', ['persona' => FixtureRoster::EDITOR, '_csrf' => 'stale'])->assertForbidden();

        $visit->get('/documents')->assertRedirectsToSignIn();
    }

    public function testAnotherHostCannotSignIn(): void
    {
        $visit = $this->asGuest();

        $visit->post('http://example.edu/_dev/personas', ['persona' => FixtureRoster::EDITOR])->assertNotFound();

        $visit->get('/documents')->assertRedirectsToSignIn();
    }

    public function testSwitchingPersonaChangesWhatThePageAllows(): void
    {
        $visit = $this->asGuest();
        $visit->post('/_dev/personas', ['persona' => FixtureRoster::EDITOR, 'return' => '/documents']);
        $visit->get('/documents')->assertOk();

        $visit->post('/_dev/personas', ['persona' => FixtureRoster::NEWCOMER, 'return' => '/documents']);

        $visit->get('/documents')->assertForbidden();
    }

    public function testTheOutageToggleTurnsTheDirectoryOff(): void
    {
        $visit = $this->asGuest();

        $visit->post('/_dev/personas/outage', ['outage' => 'on', 'return' => '/_dev/personas'])->assertRedirect('/');
        $visit->post('/_dev/personas', ['persona' => FixtureRoster::EDITOR]);
        $visit->get('/documents')->assertServiceUnavailable();

        $visit->post('/_dev/personas/outage', ['outage' => 'off', 'return' => '/documents'])->assertRedirect('/documents');
        $visit->get('/documents')->assertOk();
    }

    public function testAnUnknownOutageSettingIsRejected(): void
    {
        $response = $this->asGuest()->post('/_dev/personas/outage', ['outage' => 'maybe']);

        self::assertSame(422, $response->status(), 'the form is refused');
        $response->assertSee("Unknown outage setting 'maybe'. Use on or off.");
    }
}
