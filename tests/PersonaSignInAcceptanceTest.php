<?php

declare(strict_types=1);

namespace Guild\DevTools\Test;

use Guild\DevTools\Test\Support\FixtureRoster;
use Guild\DevTools\Testing\VisitsApplication;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The fixture application booted from its own config/app.php, which
 * discovers this package as an extension, with no IU Login or Grouper
 * settings and no System Admin label, driven through the real Router, Gate
 * and grant tables by the test kit.
 *
 * Integration coverage across many classes, so it declares none; the units
 * are covered by their own tests.
 */
#[CoversNothing]
final class PersonaSignInAcceptanceTest extends TestCase
{
    use VisitsApplication;

    protected function applicationBasePath(): string
    {
        return __DIR__ . '/Fixture/app';
    }

    public function testAGuestIsChallengedByThePersonaPicker(): void
    {
        $this->asGuest()->get('/documents')->assertRedirect('/_dev/personas?return=%2Fdocuments');
    }

    public function testTheEditorHoldsTheSeededGrantAndSeesThePage(): void
    {
        $this->signedInAs(FixtureRoster::EDITOR)->get('/documents')->assertOk()->assertSee('documents');
    }

    public function testAPersonaInNoGroupIsRefused(): void
    {
        $this->signedInAs(FixtureRoster::NEWCOMER)->get('/documents')->assertForbidden();
    }

    public function testAPersonaGrouperCannotAnswerForGetsServiceUnavailable(): void
    {
        $this->signedInAs(FixtureRoster::OUTAGE)->get('/documents')->assertServiceUnavailable();
    }

    public function testTheRostersSystemAdminGroupOpensTheAdministrationPages(): void
    {
        $this->signedInAs(FixtureRoster::ADMIN)->get('/framework/authorization')->assertOk();
    }

    public function testTheAdministrationPagesAreClosedToEveryoneElse(): void
    {
        $this->signedInAs(FixtureRoster::EDITOR)->get('/framework/authorization')->assertForbidden();
    }

    public function testWithTheOutageToggleOnAndNothingCachedTheEditorGetsServiceUnavailable(): void
    {
        $this->signedInAs(FixtureRoster::EDITOR)->duringDirectoryOutage()->get('/documents')->assertServiceUnavailable();
    }

    public function testARequestToAnyOtherHostIsNotFoundSignedInOrNot(): void
    {
        $this->signedInAs(FixtureRoster::EDITOR)->get('https://example.org/documents')->assertNotFound();
        $this->asGuest()->get('https://example.org/documents')->assertNotFound();
    }

    public function testADeveloperSwitchesPersonasByHand(): void
    {
        $visit = $this->asGuest();

        $visit->get('/documents/page')->assertRedirect('/_dev/personas?return=%2Fdocuments%2Fpage');
        $visit->get('/_dev/personas?return=%2Fdocuments%2Fpage')->assertOk()->assertSee('Sign in as ' . FixtureRoster::EDITOR_NAME);

        $visit->post('/_dev/personas', ['persona' => FixtureRoster::EDITOR, 'return' => '/documents/page'])->assertRedirect('/documents/page');
        $visit->get('/documents/page')->assertOk()->assertSee(sprintf('signed in as <strong>%s</strong>', FixtureRoster::EDITOR_NAME));

        $visit->post('/_dev/personas', ['persona' => FixtureRoster::NEWCOMER, 'return' => '/documents/page']);
        $visit->get('/documents/page')->assertForbidden()->assertSee(sprintf('signed in as <strong>%s</strong>', FixtureRoster::NEWCOMER_NAME));

        $visit->post('/_dev/personas', ['persona' => FixtureRoster::ADMIN, 'return' => '/framework/authorization']);
        $visit->get('/framework/authorization')->assertOk()->assertSee(sprintf('signed in as <strong>%s</strong>', FixtureRoster::ADMIN_NAME));

        $visit->post('/framework/sign-out')->assertRedirect('/_dev/personas');
        $visit->get('/documents/page')->assertRedirectsToSignIn();
    }
}
