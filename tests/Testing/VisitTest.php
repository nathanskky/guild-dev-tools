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
use Guild\DevTools\Test\Support\FixturePermission;
use Guild\DevTools\Test\Support\FixtureRoster;
use Guild\DevTools\Testing\ApplicationUnderTest;
use Guild\DevTools\Testing\TestDatabase;
use Guild\DevTools\Testing\Visit;
use Guild\DevTools\Testing\VisitResponse;
use Guild\DevTools\Testing\VisitsApplication;
use Guild\Framework\Exception\ConfigurationException;
use Guild\Framework\Model\Group;
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

    protected function applicationBasePath(): string
    {
        return dirname(__DIR__) . '/Fixture/app';
    }

    public function testAnInlinePersonaCombinesTheRostersGroups(): void
    {
        $editorAdmin = $this->signedInAs(new Persona('editor-admin', username: 'persona-editor-admin', name: 'Eda Editor-Admin', groups: [
            new PersonaGroup(FixtureRoster::EDITORS_GROUP, FixtureRoster::EDITORS_LABEL),
            new PersonaGroup(FixtureRoster::ADMINS_GROUP, FixtureRoster::ADMINS_LABEL),
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

    public function testPostFillsInTheCsrfToken(): void
    {
        $response = $this->signedInAs(FixtureRoster::EDITOR)->post('/documents', ['title' => 'Minutes']);

        self::assertSame(201, $response->status(), 'the form was accepted without the test handling the token');
        $response->assertSee('stored Minutes');
    }

    public function testAWrongCsrfTokenIsRefused(): void
    {
        $this->signedInAs(FixtureRoster::EDITOR)->post('/documents', ['_csrf' => 'wrong'])->assertForbidden()->assertSee('Form expired');
    }

    public function testADirectoryOutageAppliesOnlyToTheDerivedVisit(): void
    {
        $visit = $this->signedInAs(FixtureRoster::EDITOR);

        $visit->duringDirectoryOutage()->get('/documents')->assertServiceUnavailable();
        $visit->get('/documents')->assertOk();
    }

    public function testAnOutageAfterBrowsingIsAnsweredFromTheCache(): void
    {
        $visit = $this->signedInAs(FixtureRoster::EDITOR);
        $visit->get('/documents')->assertOk();

        // The copy keeps the membership the first request cached, which is within its TTL, as in production.
        $visit->duringDirectoryOutage()->get('/documents')->assertOk();
    }

    public function testTwoVisitsAreSeparateVisitors(): void
    {
        $editor = $this->signedInAs(FixtureRoster::EDITOR);
        $guest = $this->asGuest();

        $editor->get('/documents')->assertOk();
        $guest->get('/documents')->assertRedirectsToSignIn();
        $editor->get('/documents')->assertOk();
    }

    public function testAGrantWrittenMidTestAppliesOnTheNextRequest(): void
    {
        $reviewers = new PersonaGroup('iu:roles:sys:acm:starter-reviewers', 'Starter Reviewers');
        $visit = $this->signedInAs(new Persona('reviewer', username: 'persona-reviewer', name: 'Rey Reviewer', groups: [$reviewers]));
        $visit->get('/documents')->assertForbidden();

        // An administrator maps the group to a role that may view documents.
        $group = new Group();
        $group->name = $reviewers->label;
        $group->group_identifier = $reviewers->identifier;
        $group->active = true;
        $group->created_by = 'jdoe';
        $group->save();
        $role = new Role();
        $role->name = 'Reviewer';
        $role->active = true;
        $role->created_by = 'jdoe';
        $role->save();
        $group->roles()->attach($role->id, ['created_by' => 'jdoe']);
        $permission = new RolePermission();
        $permission->role_id = $role->id;
        $permission->name = FixturePermission::DocumentsView->value;
        $permission->active = true;
        $permission->created_by = 'jdoe';
        $permission->save();

        $visit->get('/documents')->assertOk();
    }

    public function testAGrantRevokedMidTestStaysRevoked(): void
    {
        $visit = $this->signedInAs(FixtureRoster::EDITOR);
        $visit->get('/documents')->assertOk();

        // An administrator deactivates the Editor role.
        $editor = Role::query()->where('name', FixtureRoster::EDITOR_ROLE)->firstOrFail();
        $editor->active = false;
        $editor->updated_by = 'jdoe';
        $editor->save();

        $visit->get('/documents')->assertForbidden();
    }

    public function testASessionCarriesAcrossAVisitsRequests(): void
    {
        $visit = $this->signedInAs(FixtureRoster::EDITOR);
        $visit->get('/documents')->assertOk();

        $visit->post('/framework/sign-out')->assertRedirect('/_dev/personas');
        $visit->get('/documents')->assertRedirectsToSignIn();
    }

    public function testHeadersAreSentWithEveryRequest(): void
    {
        $visit = $this->asGuest()->withHeaders(['X-Test' => 'yes']);

        $visit->get('/echo-header')->assertSee('yes');
        $visit->get('/echo-header')->assertSee('yes');
    }

    public function testAVisitSeedsTheRostersGrants(): void
    {
        self::assertFalse(Role::query()->where('name', FixtureRoster::EDITOR_ROLE)->exists(), 'before the first visit, the roster\'s role is not there');

        $this->asGuest()->get('/documents');

        $editor = Role::query()->where('name', FixtureRoster::EDITOR_ROLE)->first();
        self::assertNotNull($editor, 'the visit wrote the roster\'s role');
        self::assertTrue(
            RolePermission::query()->where('role_id', $editor->id)->where('name', FixturePermission::DocumentsView->value)->exists(),
            'with its permission',
        );
    }
}
