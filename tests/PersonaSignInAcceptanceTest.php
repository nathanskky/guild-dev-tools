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
}
