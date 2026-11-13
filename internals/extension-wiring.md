# Extension wiring

> For maintainers changing how `PersonaSignInExtension` plugs into the framework.

dev-tools is a framework
[extension](https://github.com/nathanskky/guild-framework/blob/main/README.md#extensions): the framework
finds it at boot and calls its `configure()`, then its `boot()`. The constructor reads the environment
with `mergeEnvironment()`. `configure()` refuses real credentials and loads the roster, then passes `PersonaSignIn` to `useSignInMethod()`,
`PersonaGroupDirectory` to `useGroupDirectory()` and the roster's System Admin label to
`useSystemAdminGroup()`. `boot()` binds the services, mounts the picker and adds the banner. This page
covers only what the code doesn't make obvious.

## Middleware order

`PersonaBanner` is added with `lazyPrependMiddleware()`, so it wraps the `AuthorizationFailureResponder`
that `addAuthorization()` adds. Appended instead, the banner would miss the 403 and 503 pages, where a
developer most needs to switch persona. `tests/Picker/PersonaBannerRoutesTest.php` checks the banner on
both pages.

The picker's route group runs `RequireLocalHost` before `VerifyCsrfToken`, so a request on another host
gets a 404 rather than "Form expired". No test pins this order.

## Binding in `boot()`

Bind the classes passed to `useSignInMethod()` and `useGroupDirectory()` in `boot()`, not `configure()`.
At `create()`, the framework only checks that the container `has()` each one and builds nothing, so tests
can still replace `SessionStore` before anything resolves it.

Every factory resolves `PersonaRoster` from the container, never the `$roster` it captured.
`ApplicationUnderTest` replaces the roster after boot to add an inline test persona, and a captured roster
would miss it.

## The picker page

`PersonaPickerPage` builds its own Rivet `Renderer`, never the app's, because the app's navigation may need
someone signed in.

## Environment

- For the flag, `mergeEnvironment()` lets the process environment win over `$_ENV`, so a start script's choice stands.
- For a secret, a non-blank value in either source counts, because the app may read the one a single-source check would miss.
- `1` is accepted as well as `true`, because PHPUnit writes `<env value="true">` as `1`.

## Return paths

`ReturnPath::from()` is the one rule for where a visitor is sent back to. It runs on the challenge's
target and again on the picker's `return` input, because a query string or form field can say anything.
Laminas already normalizes `//host` paths and encodes backslashes before `PersonaSignIn` sees them; keep
`ReturnPath`'s checks anyway.

## Sign-out

`PersonaSignIn::signOut()` regenerates the session ID but doesn't rotate the CSRF token. The framework's
`SignOutController` rotates it after `signOut()` returns, and the [safety page](../docs/concepts/safety.md)
relies on both.

## The grant seeder

`PersonaGrantSeeder` runs from the picker's `show()` and from the test kit, never on an ordinary request.
It never deletes or detaches, so grants added at `/framework/authorization` survive. Its docblock gives
the full rules.
