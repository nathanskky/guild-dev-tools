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
`tests/Fixture/app` is a small application booted by the extension and acceptance tests.

## Layout

- `PersonaSignInExtension` — reads the environment, refuses real credentials, loads `config/personas.php`,
  chooses the framework's three slots in `configure()`, binds its services in `boot()`
- `Persona\` — the roster's value objects: `PersonaKey`, `Persona`, `PersonaGroup`, `RoleGrant`,
  `DirectoryStatus`, `PersonaRoster`
- `SignIn\` — `PersonaSignIn` (the `SignInMethod`), `PersonaGroupDirectory` (the `GroupDirectory`),
  and their session state `SignedInPersona` and `DirectoryOutage`; `LocalHost`
- `Grant\` — `PersonaGrantSeeder`

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

## Branching and releases

Feature work targets `develop`; `main` is the released state, and tags are applied to `main`. This is the
model across the Guild packages.
