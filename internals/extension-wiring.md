# Extension wiring

> For maintainers changing how `PersonaSignInExtension` plugs into the framework.

`configure()` reads the environment, refuses real credentials, loads the roster and claims the sign-in
method, group directory and System Admin slots. `boot()` binds the services, mounts the picker and adds
the banner. This page covers only what the code doesn't make obvious.

## Middleware order

- `PersonaBanner` is added with `lazyPrependMiddleware()`, so it wraps the `AuthorizationFailureResponder` that `addAuthorization()` adds.
- Appended instead, the banner would miss the 403 and 503 pages, where a developer most needs to switch.
- The picker group runs `RequireLocalHost` before `VerifyCsrfToken`, so another host gets 404, not "Form expired".
- `tests/Picker/PersonaBannerRoutesTest.php` checks the banner on the 403 and 503 pages; no test pins the host-before-CSRF order.

## Binding in `boot()`

- Bind every claimed class (`PersonaSignIn`, `PersonaGroupDirectory`) in `boot()`, not `configure()`.
- The framework checks claimed classes with `has()` at `create()` and builds nothing, so tests can still override `SessionStore`.
- Every factory resolves `PersonaRoster` from the container, never the captured `$roster`.
- `ApplicationUnderTest` replaces the roster after boot to add an inline persona; a captured roster would miss it.

## The picker page

- `PersonaPickerPage` builds its own Rivet `Renderer`, never the app's.
- The app's navigation may itself need someone signed in, which the picker can't assume.
- The framework's `AdminPage` is `@internal` and titled "Administration", so it isn't an option either.

## Environment

- `mergeEnvironment()`: for the flag, the process environment wins over `$_ENV`, so a start script's choice stands.
- For a secret, a non-blank value in either source counts, because the app may read the one the check would miss.
- `1` is accepted as well as `true` because PHPUnit writes `<env value="true">` as `1`.
- Tests of the extension pass their environment to the constructor, not through `putenv()`.

## Return paths

- `ReturnPath::from()` is the one rule for where a visitor is sent back to.
- It runs on the challenge's target and again on the picker's `return` input, because a query string or form field can say anything.
- Laminas already normalises `//host` paths and encodes backslashes before `PersonaSignIn` sees them; keep `ReturnPath`'s checks anyway.

## Sign-out

- `PersonaSignIn::signOut()` regenerates the session ID but doesn't rotate the CSRF token.
- The framework's `SignOutController` rotates it after `signOut()` returns; the safety docs rely on both.

## The grant seeder

- `PersonaGrantSeeder` never deletes or detaches, so grants added at `/framework/authorization` survive.
- It runs from the picker's `show()` and from the test kit, never on an ordinary request.
- Its docblock says "before each test visit"; the test kit runs it once per test.
