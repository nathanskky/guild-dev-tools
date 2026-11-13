# Testing dev-tools

> For maintainers writing or changing tests in this repo, or changing the test kit in `src/Testing/`.

## Conventions

- Don't use PHPUnit mocks or stubs; use the framework's `Testing\ArraySessionStore`, `Testing\FrameworkSchema` and `Testing\GroupDirectoryContract`, and Monolog's `TestHandler`.
- Give each test class `#[CoversClass]` plus `#[UsesClass]` for collaborators, or `#[CoversNothing]` for an acceptance test.
- Put test classes under `Guild\DevTools\Test\`.
- Name the fixture app's personas, groups and role through `tests/Support/FixtureRoster`, and its permissions through `FixturePermission`.
- A test that builds its own roster keeps its own values, as class constants where it repeats them.
- Pair a negative string assertion (`assertDontSee`, `assertStringNotContainsString`, `assertFalse(…->exists())`) with a positive one on the same string, in the same test.

The pairing rule exists because a negative assertion alone still passes after a rename: the old string is
absent everywhere.

## Fixtures

| Fixture | What it is | Used by |
|---|---|---|
| `tests/Fixture/app` | An app whose `config/app.php` discovers this package as an extension | The acceptance test, `PersonaSignInExtensionTest`, `tests/Testing/` and `tests/Picker/` |
| `tests/Fixture/persona-off` | An IU Login app, settings from `$_ENV`, with persona sign-in off | `tests/Testing/PersonaSignInOffTest.php` |
| `tests/Fixture/not-an-app` | A `config/app.php` that returns an array instead of an `Application` | `tests/Testing/ApplicationUnderTestTest.php` |

## Environment

`phpunit.xml.dist` forces `PERSONA_SIGN_IN_ENABLED` on and both secrets blank for every test, as an app's
would. Tests of `PersonaSignInExtension` pass their environment to its constructor, not through
`putenv()`, which would change it for the whole process. `PersonaSignInOffTest` clears the flag in
`setUp()` and restores it in `tearDown()`; copy that pattern for any test that needs the flag off.

## Test kit gotchas

The kit loads the app once per request, not once per visit, so nothing one request resolved leaks into the
next. Only the session, per visit, and the database, per test, carry over between requests.

The kit seeds grants once per test, on the first request after the transaction opens. Seeding on every
request would reactivate a grant the test revoked; `VisitTest::testAGrantRevokedMidTestStaysRevoked` pins
this.

The kit has no clock. The framework passes `time(...)` to `MembershipProvider`, so the membership TTL
never expires inside a test.

The kit reads which sign-in method the app chose from `$app->claimedSlots`, not by resolving
`SignInMethod`. Resolving it would build `PersonaSignIn` with the roster before an inline persona joins it.

PHPStan doesn't analyze `VisitsApplication`, because only tests use it (`trait.unused` is ignored in
`phpstan.neon`). So keep the trait to one-line delegations into `Visit` and `TestDatabase`, which PHPStan
does check.
