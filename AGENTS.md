# AGENTS.md

Guidance for AI coding agents (and new humans) working in this repository.

## What this is

`guild/dev-tools` adds persona sign-in to Guild applications for local development and tests. Namespace
`Guild\DevTools\`, autoloaded from `src/`. It is a `guild-extension` Composer package: `guild/framework`
discovers it and calls `PersonaSignInExtension`. Read `README.md` for what it does from an application's side.

- **PHP:** `~8.5.0`
- **Remote:** `https://github.com/nathanskky/guild-dev-tools.git`
- **Default branch:** `develop`. Not released yet.
- **`composer.lock` is gitignored** here, so there is no lock to keep in sync.
- **Requires `guild/framework` `^2.1`.** dev-tools majors track framework majors.

## Verify your change

```bash
composer install
composer check   # PHPUnit, then PHPStan level 10 over src/, then Pint (PSR-12)
```

All three are green on a clean checkout. Keep them green.

`phpunit.xml.dist` is strict: every test class needs `#[CoversClass]` (with `#[UsesClass]` for
collaborators) or `#[CoversNothing]` for an acceptance test, and risky tests, warnings and PHPUnit
deprecations fail the run. Test classes live under `Guild\DevTools\Test\`.

**No PHPUnit mocks.** The doubles are the framework's `Testing\ArraySessionStore`, `Testing\FrameworkSchema`
(the real migrations on in-memory SQLite) and `Testing\GroupDirectoryContract`, plus Monolog's `TestHandler`.
`tests/Fixture/app` is a small application whose `config/app.php` discovers this package as an extension; the
acceptance test and `tests/Testing` visit it with this package's own test kit. `tests/Fixture/persona-off` is
an IU Login application with persona sign-in off, and `tests/Fixture/not-an-app` a broken `config/app.php`.

`phpunit.xml.dist` forces `PERSONA_SIGN_IN_ENABLED` on and both secrets blank for every test, as an
application's would. Tests of the extension itself pass their environment to `PersonaSignInExtension`
explicitly.

## Layout

- `PersonaSignInExtension` — reads the environment, refuses real credentials, loads `config/personas.php`,
  chooses the framework's three slots in `configure()`, binds its services in `boot()`
- `Persona\` — the roster's value objects: `PersonaKey`, `Persona`, `PersonaGroup`, `RoleGrant`,
  `DirectoryStatus`, `PersonaRoster`
- `SignIn\` — `PersonaSignIn` (the `SignInMethod`), `PersonaGroupDirectory` (the `GroupDirectory`),
  and their session state `SignedInPersona` and `DirectoryOutage`; `LocalHost`
- `Grant\` — `PersonaGrantSeeder`
- `Testing\` — the test kit: `VisitsApplication` (trait for test cases), `Visit`, `VisitResponse` (API), and
  `ApplicationUnderTest`, `TestDatabase` (internal)

## Conventions

- `final readonly` classes with constructor promotion, `#[\Override]` on interface implementations, and
  `@internal` on types an application should not use. `Persona` is `final` but not `readonly`, because PHP
  does not allow its `identity` property hook on a readonly class.
- Every `ConfigurationException` says what is wrong and the one change that fixes it.
- Session keys are prefixed `guild_dev_tools_`.

## Landmines

- **`PersonaSignIn::challenge()` redirects to `/_dev/personas`**, the persona picker, which does not exist
  yet. Until it does, a guest on a `RequireSignIn` route gets a 404 after the redirect.
- **The challenge's return path relies on the PSR-7 URI.** Laminas normalises `//host` paths and encodes
  backslashes before `PersonaSignIn` sees them; `returnTarget()` refuses both anyway.
- **Claimed classes must be bound in `boot()`.** The framework checks at `create()` that the container can
  build each chosen class, and builds nothing there, so a test can still override `SessionStore` afterwards.

- **PHPUnit writes `<env value="true">` as `1`.** Any spelling of `true` in `phpunit.xml` reaches PHP as `'1'`,
  so the extension accepts `1` as well as `true`.
- **The test kit loads the application once per request**, not once per visit, so nothing one request
  resolved leaks into the next. Only the session (per visit) and the database (per test) carry over.
- **The test kit has no clock.** The framework passes `time(...)` to `MembershipProvider`, and each visit
  starts with an empty session, so the membership TTL never expires inside a test.
- **The test kit reads persona mode from the framework's claimed sign-in slot.** Resolving `SignInMethod`
  instead would build `PersonaSignIn` with the roster before an inline persona joins it.
- **PHPStan does not analyse `VisitsApplication`**, because only tests use it (`trait.unused` is ignored in
  `phpstan.neon`). Keep the trait to one-line delegations.

## Branching and releases

Feature work targets `develop`; `main` is the released state, and tags are applied to `main`. This is the
model across the Guild packages.
