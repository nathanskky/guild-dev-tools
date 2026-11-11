# guild/dev-tools

Persona sign-in for local development and tests of Guild applications. A persona is a made-up user with a
username, IU Login-style attributes and Grouper groups. With persona sign-in on, people sign in as personas
instead of through IU Login, and their groups come from the same list instead of Grouper. Everything after
that is the application's real code: the framework's membership cache, the grant tables, the Gate and the
policies.

**Install it with `require-dev` only.** It is how a local stack lets anyone become anyone, so it must never
be in a production build.

## Requirements

- PHP `~8.5`
- `guild/framework` `^2.1`, with `addAuthentication()` and `addAuthorization(IdentitySource::Oidc, ...)`

## Installation

```bash
composer require --dev guild/dev-tools
```

Composer needs the VCS repositories for `guild/dev-tools`, `guild/framework`, `guild/access`, `guild/grouper`
and `guild/rivet` in the application's `composer.json`; `guild/starter` lists them.

The package has Composer type `guild-extension`, so `guild/framework` finds it with no change to
`config/app.php`. It does nothing until it is turned on.

## Turning it on

Set `PERSONA_SIGN_IN_ENABLED=true` (or `1`) in the environment. Any other value, or none, leaves the
application signing in through IU Login.

While it is on, the application:

- signs people in as personas from `config/personas.php` (`/framework/sign-out` signs them out)
- reads their groups from that file instead of Grouper, so `config/grouper.php` is not read
- takes the System Admin group label from the file, when it names one
- does not read `config/authentication.php`

## The roster: `config/personas.php`

```php
<?php

use Guild\DevTools\Persona\{DirectoryStatus, Persona, PersonaGroup, PersonaRoster, RoleGrant};
use Guild\Starter\Authorization\AppPermission;

$editors = new PersonaGroup('iu:roles:sys:acm:starter-editors', 'Starter Editors');
$admins  = new PersonaGroup('iu:roles:sys:acm:starter-admins', 'Starter Admins');

return new PersonaRoster(
    personas: [
        new Persona('editor', username: 'persona-editor', name: 'Erin Editor', groups: [$editors]),
        new Persona('newcomer', username: 'persona-newcomer', name: 'Nico Newcomer'),
        new Persona('admin', username: 'persona-admin', name: 'Ada Admin', groups: [$admins]),
        new Persona('outage', username: 'persona-outage', name: 'Grouper Down', directory: DirectoryStatus::Unavailable),
    ],
    grants: [
        new RoleGrant('Editor', groups: [$editors], permissions: [AppPermission::ExampleView]),
    ],
    systemAdminGroup: $admins,
);
```

- A persona's key (`'editor'`) is lower-case letters, digits and hyphens, at most 40 characters.
- `attributes:` adds IU Login claims such as `email`; `username` and `name` come from their own arguments.
- Usernames start with `persona-`, so none is a real IU username. `usernamePrefix:` changes the prefix.
- Groups name real Grouper system names and ACM labels, so the same grants work in production.
- Each group has one label, and no two groups share one.
- `DirectoryStatus::Unavailable` makes Grouper unreachable for that persona, to show the 503 page.

`grants:` are written into the framework's grant tables by `PersonaGrantSeeder`, through the same models the
administration pages use, each time the persona picker opens and before each test visit. It adds and
reactivates and never deletes, so grants added through `/framework/authorization` survive. Rows it writes say
`persona-roster` in `created_by`. A roster with no `grants:` writes nothing, so an application with
authorization off needs no database.

A roster that breaks a rule fails at boot with a message naming the persona or group and the fix.

## The persona picker

Open `/_dev/personas` on the local stack. A guest on a page that needs sign-in is sent there, and returns to
that page after choosing.

Each persona is listed with its key, username, groups, and the roles and permissions those groups grant, so
you can see what it should be able to do before choosing. `System Admin` marks the persona in the roster's
System Admin group, and `Grouper unavailable` one with `DirectoryStatus::Unavailable`. `Continue as a guest`
signs out.

Choosing a persona signs you in with a new session ID and CSRF token, logs the switch, and takes you back to
the page you were going to, when it is a page of this site; otherwise to `/`.

`Simulate a Grouper outage` makes Grouper unavailable for your session, whoever you are signed in as.
Membership already cached keeps answering until it expires, then stale membership is served, then pages that
need it give 503, as in production. Lower `membershipTtl` in `config/authorization.php` to see this sooner.
`End the Grouper outage` turns it off.

## Safety

Persona sign-in lets anyone become anyone. The first three layers each keep it out of production on their own;
the rest limit what it can do where it does run:

| Layer | What it does |
|---|---|
| Absent from production | Installed with `require-dev`, so a `--no-dev` build does not contain it |
| Off unless chosen | Inactive unless `PERSONA_SIGN_IN_ENABLED` is `true` or `1` |
| Refuses real credentials | Boot fails if `OIDC_CLIENT_SECRET` or `GROUPER_PASSWORD` is set while it is on |
| Local hosts only | Signing in and the picker answer 404 unless the request's host is `localhost`, `127.0.0.1` or `::1`, so an app reached under a real host name never offers it. The host comes from the client's `Host` header, so this is no defence against a crafted request |
| Session hygiene | The session ID and CSRF token are replaced on every switch and sign-out |
| Audited | Every switch and sign-out is logged at `notice` with the persona's key |

## Testing as a persona

A feature test signs in as a persona in one call and asserts on the response, through the application's
real router, Gate and grants:

```php
use Guild\DevTools\Testing\VisitsApplication;
use PHPUnit\Framework\TestCase;

final class ExamplePageTest extends TestCase
{
    use VisitsApplication;

    public function testEditorsSeeTheExample(): void
    {
        $this->signedInAs('editor')->get('/')->assertOk()->assertSee('Example');
    }

    public function testGuestsAreAskedToSignIn(): void
    {
        $this->asGuest()->get('/')->assertRedirectsToSignIn();
    }

    public function testNewcomersAreForbidden(): void
    {
        $this->signedInAs('newcomer')->get('/')->assertForbidden();
    }

    public function testAGrouperOutageIsReported(): void
    {
        $this->signedInAs('editor')->duringDirectoryOutage()->get('/')->assertServiceUnavailable();
    }
}
```

`phpunit.xml.dist` turns persona sign-in on and blanks the real secrets. `force="true"` matters: without it,
a variable already set in the environment (inside a container started for real IU Login, say) wins, and
persona sign-in either stays off or refuses to start.

```xml
<php>
    <env name="PERSONA_SIGN_IN_ENABLED" value="true" force="true"/>
    <env name="OIDC_CLIENT_SECRET" value="" force="true"/>
    <env name="GROUPER_PASSWORD" value="" force="true"/>
</php>
```

Each request a visit makes loads the application from `config/app.php`, as a real request would, and swaps in:

- the visit's own session, in memory, which carries across the visit's requests
- an in-memory SQLite database, built once per PHPUnit run by the framework's migrations
- a logger that discards everything
- the request itself

The roster's grants are seeded before a test's first request, so a grant the test revokes stays revoked. Each test runs in a database transaction that is rolled
back after it, including rows written in `setUp()`. Only the framework's tables exist; the application's own
migrations are not run yet.

### Visits

- `signedInAs('editor')` takes a key from `config/personas.php`. A key that is not there fails the test with
  a message saying so.
- `signedInAs(new Persona(...))` takes a persona built for one test. It joins the roster's personas, grants
  and System Admin group for that visit.
- `asGuest()` is a visitor who has not signed in.
- `get()`, `post()`, `put()` and `delete()` send a request. A path is sent to `http://localhost`; an absolute
  URL visits another host. The write methods fill in the session's CSRF token unless the data has a `_csrf`
  entry.
- `withHeaders([...])` returns a visitor who sends those headers with every request.
- `duringDirectoryOutage()` returns a visitor for whom Grouper is down.

Methods that return a visitor return a copy with a copy of the session, and the original is unchanged. So
`duringDirectoryOutage()` after a successful request still has that request's cached membership, and is
answered from it within the membership TTL, as in production. To test the 503 page, start the outage before
the first request.

An exception the application does not handle fails the test with that exception; it is not turned into a 500.

### Assertions

`assertOk()`, `assertForbidden()`, `assertNotFound()`, `assertServiceUnavailable()`, `assertRedirect($to)`,
`assertRedirectsToSignIn()`, `assertSee($text)` and `assertDontSee($text)`. Each returns the response, so they
chain. `assertSee()` compares against the page as a reader sees it, with HTML entities decoded. A failure
names the actual status, the redirect target and the start of the body. `status()`, `header()` and `body()`
give the raw values.

The application is found through the Composer root package. Override `applicationBasePath()` in the test case
to visit another one.

## Contributing

See [`AGENTS.md`](AGENTS.md).
