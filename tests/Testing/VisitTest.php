<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Testing;

use Guild\DevTools\Grant\PersonaGrantSeeder;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\DevTools\PersonaSignInExtension;
use Guild\DevTools\SignIn\DirectoryOutage;
use Guild\DevTools\SignIn\LocalHost;
use Guild\DevTools\SignIn\PersonaGroupDirectory;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\DevTools\SignIn\SignedInPersona;
use Guild\DevTools\Testing\ApplicationUnderTest;
use Guild\DevTools\Testing\TestDatabase;
use Guild\DevTools\Testing\Visit;
use Guild\DevTools\Testing\VisitResponse;
use Guild\DevTools\Testing\VisitsApplication;
use Guild\Framework\Exception\ConfigurationException;
use Guild\Framework\Model\Role;
use Guild\Framework\Model\RolePermission;
use Guild\Framework\Testing\ArraySessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Visit::class)]
#[UsesClass(ApplicationUnderTest::class)]
#[UsesClass(TestDatabase::class)]
#[UsesClass(VisitResponse::class)]
#[UsesClass(Persona::class)]
#[UsesClass(PersonaGroup::class)]
#[UsesClass(PersonaKey::class)]
#[UsesClass(PersonaRoster::class)]
#[UsesClass(RoleGrant::class)]
#[UsesClass(PersonaSignInExtension::class)]
#[UsesClass(PersonaSignIn::class)]
#[UsesClass(PersonaGroupDirectory::class)]
#[UsesClass(PersonaGrantSeeder::class)]
#[UsesClass(SignedInPersona::class)]
#[UsesClass(DirectoryOutage::class)]
#[UsesClass(LocalHost::class)]
#[UsesClass(ArraySessionStore::class)]
final class VisitTest extends TestCase
{
    use VisitsApplication;

    private int $transactionLevelInSetUp = 0;

    protected function setUp(): void
    {
        $this->transactionLevelInSetUp = TestDatabase::capsule()->getConnection()->transactionLevel();
    }

    protected function applicationBasePath(): string
    {
        return dirname(__DIR__) . '/Fixture/app';
    }

    public function testAnEditorSeesTheDocuments(): void
    {
        $this->signedInAs('editor')->get('/documents')->assertOk()->assertSee('documents');
    }

    public function testAGuestIsAskedToSignIn(): void
    {
        $this->asGuest()->get('/documents')->assertRedirectsToSignIn();
    }

    public function testANewcomerIsForbidden(): void
    {
        $this->signedInAs('newcomer')->get('/documents')->assertForbidden();
    }

    public function testAPersonaGrouperCannotAnswerForGetsServiceUnavailable(): void
    {
        $this->signedInAs('outage')->get('/documents')->assertServiceUnavailable();
    }

    public function testTheSystemAdminPersonaOpensTheAdministrationPages(): void
    {
        $this->signedInAs('admin')->get('/framework/authorization')->assertOk();
        $this->signedInAs('editor')->get('/framework/authorization')->assertForbidden();
    }

    public function testAnotherHostIsNotFound(): void
    {
        $this->signedInAs('editor')->get('https://example.org/documents')->assertNotFound();
    }

    public function testAnInlinePersonaCombinesTheRostersGroups(): void
    {
        $editorAdmin = $this->signedInAs(new Persona('editor-admin', username: 'persona-editor-admin', name: 'Eda Editor-Admin', groups: [
            new PersonaGroup('iu:roles:sys:acm:starter-editors', 'Starter Editors'),
            new PersonaGroup('iu:roles:sys:acm:starter-admins', 'Starter Admins'),
        ]));

        $editorAdmin->get('/documents')->assertOk();
        $editorAdmin->get('/framework/authorization')->assertOk();
    }

    public function testAPersonaNotInTheRosterIsReported(): void
    {
        $visit = $this->signedInAs('editr');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches("/'editr'.*signedInAs\\(\\)/");

        $visit->get('/documents');
    }

    public function testTheTransactionIsOpenBeforeSetUp(): void
    {
        self::assertGreaterThanOrEqual(1, $this->transactionLevelInSetUp, 'rows a test writes in setUp() are rolled back too');
    }

    public function testAVisitSeedsTheRostersGrants(): void
    {
        TestDatabase::activate();
        self::assertFalse(Role::query()->where('name', 'Editor')->exists(), 'the previous test\'s seeding was rolled back');

        $this->asGuest()->get('/documents');

        $editor = Role::query()->where('name', 'Editor')->first();
        self::assertNotNull($editor, 'the visit wrote the roster\'s role');
        self::assertTrue(
            RolePermission::query()->where('role_id', $editor->id)->where('name', 'documents.view')->exists(),
            'with its permission',
        );
    }
}
