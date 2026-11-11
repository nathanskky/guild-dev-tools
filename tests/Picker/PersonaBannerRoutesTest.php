<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Picker;

use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Picker\PersonaBanner;
use Guild\DevTools\Test\Support\FixtureRoster;
use Guild\DevTools\Testing\VisitsApplication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The banner on the fixture application's pages, through the test kit.
 */
#[CoversClass(PersonaBanner::class)]
final class PersonaBannerRoutesTest extends TestCase
{
    use VisitsApplication;

    private const string BANNER_OPENS = '<div id="guild-persona-banner" role="status"';

    protected function applicationBasePath(): string
    {
        return dirname(__DIR__) . '/Fixture/app';
    }

    public function testASignedInPageSaysWhoYouAre(): void
    {
        $response = $this->signedInAs(FixtureRoster::EDITOR)->get('/documents/page')
            ->assertOk()
            ->assertSee(sprintf('Persona sign-in: signed in as <strong>%s</strong> (%s)', FixtureRoster::EDITOR_NAME, FixtureRoster::EDITOR));

        self::assertStringContainsString('<body class="fixture">' . self::BANNER_OPENS, $response->body(), 'right after <body>, before the page\'s own content');
    }

    public function testTheBannerOffersSwitchingSignOutAndTheOutage(): void
    {
        $body = $this->signedInAs(FixtureRoster::EDITOR)->get('/documents/page')->body();

        foreach ([
            'href="/_dev/personas?return=%2Fdocuments%2Fpage"',
            'action="/framework/sign-out"',
            'action="/_dev/personas/outage"',
            'name="_csrf"',
            'Switch persona',
            'Simulate a Grouper outage',
        ] as $markup) {
            self::assertStringContainsString($markup, $body, sprintf('the banner has %s', $markup));
        }
    }

    public function testAGuestSeesTheyAreNotSignedIn(): void
    {
        $this->asGuest()->get('/welcome')->assertSee('Persona sign-in: not signed in')->assertSee('Switch persona')->assertDontSee('Sign out');
        $this->signedInAs(FixtureRoster::EDITOR)->get('/welcome')->assertSee('Sign out');
    }

    public function testAForbiddenPageStillShowsTheBanner(): void
    {
        $this->signedInAs(FixtureRoster::NEWCOMER)->get('/documents/page')
            ->assertForbidden()
            ->assertSee(sprintf('signed in as <strong>%s</strong>', FixtureRoster::NEWCOMER_NAME));
    }

    public function testAnUnavailablePageStillShowsTheBanner(): void
    {
        $this->signedInAs(FixtureRoster::OUTAGE)->get('/documents/page')
            ->assertServiceUnavailable()
            ->assertSee(sprintf('signed in as <strong>%s</strong>', FixtureRoster::OUTAGE_NAME));
    }

    public function testTheOutageIsShownWhileOn(): void
    {
        $visit = $this->signedInAs(FixtureRoster::EDITOR);

        $visit->duringDirectoryOutage()->get('/welcome')->assertSee('Grouper outage simulated')->assertSee('End the Grouper outage');
        $visit->get('/welcome')->assertDontSee('Grouper outage simulated')->assertSee('Simulate a Grouper outage');
    }

    public function testNonHtmlIsUntouched(): void
    {
        $visit = $this->signedInAs(FixtureRoster::EDITOR);

        $visit->get('/documents')->assertOk()->assertDontSee('Persona sign-in');
        $visit->get('/welcome')->assertSee('Persona sign-in');
    }

    public function testAFragmentWithoutBodyIsUntouched(): void
    {
        self::assertSame('<p>fragment</p>', $this->asGuest()->get('/fragment')->body(), 'a fragment has no <body> to follow');
    }

    public function testAStaleContentLengthIsDropped(): void
    {
        $response = $this->asGuest()->get('/sized');

        self::assertStringContainsString(self::BANNER_OPENS, $response->body(), 'the page grew by the banner');
        self::assertSame('', $response->header('Content-Length'), 'so the length it stated is gone');
    }

    public function testAnotherHostGetsNoBanner(): void
    {
        $visit = $this->asGuest();

        $visit->get('http://example.edu/welcome')->assertOk()->assertDontSee('Persona sign-in');
        $visit->get('/welcome')->assertSee('Persona sign-in');
    }

    public function testAPersonaNameIsEscapedInTheBanner(): void
    {
        $body = $this->signedInAs(new Persona('eve', username: 'persona-eve', name: 'Eve <b>Bold</b>'))->get('/welcome')->body();

        self::assertStringContainsString('<strong>Eve &lt;b&gt;Bold&lt;/b&gt;</strong>', $body, 'a name is shown as written');
        self::assertStringNotContainsString('<b>Bold</b>', $body, 'never as markup');
    }

    public function testThePickerHasTheBannerToo(): void
    {
        $this->asGuest()->get('/_dev/personas')->assertSee('Persona sign-in: not signed in');
    }
}
