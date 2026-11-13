# Test as a persona

> For app developers writing feature tests that sign in as a persona.

This guide shows you how to write a feature test that visits your app as a persona or a guest. The visit
goes through your app's real router, Gate and grants.

## Before you start

- `phpunit.xml.dist` turns persona sign-in on, as starter's does, with these lines inside `<php>`:

  ```xml
  <env name="PERSONA_SIGN_IN_ENABLED" value="true" force="true"/>
  <env name="OIDC_CLIENT_SECRET" value="" force="true"/>
  <env name="GROUPER_PASSWORD" value="" force="true"/>
  ```
- `config/personas.php` returns your roster; to write one, follow [Write the roster](write-the-roster.md).

## Write the test

Use the `VisitsApplication` trait in a PHPUnit test case, then start each visit with `signedInAs()` or
`asGuest()`:

```php
use Guild\DevTools\Testing\VisitsApplication;
use PHPUnit\Framework\TestCase;

final class ReportsPageTest extends TestCase
{
    use VisitsApplication;

    public function testEditorsSeeTheReports(): void
    {
        $this->signedInAs('editor')->get('/reports')->assertOk()->assertSee('Reports');
    }

    public function testGuestsAreAskedToSignIn(): void
    {
        $this->asGuest()->get('/reports')->assertRedirectsToSignIn();
    }

    public function testNewcomersAreForbidden(): void
    {
        $this->signedInAs('newcomer')->get('/reports')->assertForbidden();
    }

    public function testAGrouperOutageIsReported(): void
    {
        $this->signedInAs('editor')->duringDirectoryOutage()->get('/reports')->assertServiceUnavailable();
    }
}
```

These four visits cover a page's everyday cases: allowed, not signed in, not allowed, and Grouper down.

Each test runs in a database transaction that is rolled back afterward, including rows you write in
`setUp()`. The test database has the framework's tables only; your app's own migrations
don't run.

## Submit a form

To submit a form, call `post()`, `put()` or `delete()` with the form data. The session's CSRF token is
added for you. To test a refused form, pass your own `_csrf` value.

## Sign in as a persona for one test

To use a persona that only one test needs, pass a `Persona` instead of a key:

```php
use Guild\DevTools\Persona\{Persona, PersonaGroup};

$auditors = new PersonaGroup('iu:roles:sys:acm:reports-auditors', 'Reports Auditors');

$this->signedInAs(new Persona('auditor', username: 'persona-auditor', name: 'Ari Auditor', groups: [$auditors]))
    ->get('/reports')
    ->assertForbidden();
```

The persona joins the roster for that visit, so it must follow the same rules as the roster's personas.

## Test a Grouper outage after browsing

`duringDirectoryOutage()` returns a copy of the visit with a copy of its session. Membership an earlier
request cached still answers, as in production, so the page keeps working. To test the 503 page, call
`duringDirectoryOutage()` before the visit's first request.

## Change grants during a test

To test a grant an administrator adds or revokes, write it through the framework's models during the test.
The next request reads it. The roster's grants are seeded once per test, before its first request, so a
grant you revoke stays revoked.

## Check text near the persona's name

Pages in tests carry the persona banner, as they do in your browser. So `assertDontSee('Erin Editor')`
fails on any HTML page with a `<body>` tag that the editor visits; check for something more specific.

`assertSee()` matches against the HTML with entities decoded, not against visible text. Text that markup
splits, such as `Hello <strong>Erin</strong>`, doesn't match `Hello Erin`.

## Test another app

The test kit visits the app at the Composer root package. To visit another app, override
`applicationBasePath()` in your test case to return the directory that holds its `config/app.php`.

## Related

- `VisitsApplication`, `Visit` and `VisitResponse` document every method and assertion in their docblocks.
- [How persona sign-in works](../concepts/how-persona-sign-in-works.md) explains what each request loads.
