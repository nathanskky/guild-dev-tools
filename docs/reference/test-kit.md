# Test kit reference

> For app developers looking up a method or assertion of the persona test kit.

The test kit is in `Guild\DevTools\Testing`. A test case uses the `VisitsApplication` trait.

## `VisitsApplication`

| Method | Returns | Description |
|---|---|---|
| `signedInAs(string\|Persona $persona)` | `Visit` | A visitor signed in as a roster key, or as a `Persona` that joins the roster for this visit. |
| `asGuest()` | `Visit` | A visitor who hasn't signed in. |
| `applicationBasePath()` | `string` | The directory holding `config/app.php`. Default: the Composer root package. Override it to visit another app. |

The trait opens a database transaction before `setUp()` and rolls it back after each test.

## `Visit`

| Method | Returns | Description |
|---|---|---|
| `get(string $uri)` | `VisitResponse` | Sends a `GET`. |
| `post(string $uri, array $data = [])` | `VisitResponse` | Sends a `POST` with `$data` as the form body. |
| `put(string $uri, array $data = [])` | `VisitResponse` | Sends a `PUT` with `$data` as the form body. |
| `delete(string $uri, array $data = [])` | `VisitResponse` | Sends a `DELETE` with `$data` as the form body. |
| `withHeaders(array $headers)` | `Visit` | A copy that sends `$headers`, over any it already sends, with every request. |
| `duringDirectoryOutage()` | `Visit` | A copy for which Grouper is unreachable. |

- A `$uri` starting with `/` is sent to `http://localhost`; an absolute URL is sent as given.
- `post()`, `put()` and `delete()` add the session's CSRF token as `_csrf`, unless `$data` has one.
- The session carries across a visit's requests; two visits have separate sessions.
- A copy gets a copy of the session as it is then, so membership cached earlier still answers.
- The original visit is unchanged by its copies.

## `VisitResponse`

| Method | Description |
|---|---|
| `assertOk()` | Status is 200. |
| `assertForbidden()` | Status is 403. |
| `assertNotFound()` | Status is 404. |
| `assertServiceUnavailable()` | Status is 503. |
| `assertRedirect(string $to)` | Status is 3xx and `Location` is exactly `$to`. |
| `assertRedirectsToSignIn()` | Status is 3xx and the `Location` path is `/_dev/personas`. |
| `assertSee(string $text)` | The body, with HTML entities decoded, contains `$text`. Markup isn't stripped. |
| `assertDontSee(string $text)` | The body, with HTML entities decoded, doesn't contain `$text`. |
| `status()` | The status code. |
| `header(string $name)` | The header's value, comma-joined. |
| `body()` | The body as a string. |

Each assertion returns the response, so assertions chain. A failure message gives the actual status, the
`Location` header and the first 500 characters of the body. The PSR-7 response is the public `$response`
property.

## Each request

Each request loads the app from `config/app.php`, then replaces these services:

| Service | Replaced with |
|---|---|
| Session | The visit's session, in memory. |
| Database | In-memory SQLite, migrated once per PHPUnit process with the framework's tables only. |
| Logger | A logger that discards everything. |
| Request | The request being sent. |

The roster's grants are seeded on a test's first request only, so a grant the test revokes stays revoked.

## Failures

| Cause | Result |
|---|---|
| `signedInAs()` with a key that breaks the key rule | `ConfigurationException` at once. |
| `signedInAs()` with a key not in the roster | `ConfigurationException` on the first request, naming the key. |
| Persona sign-in is off | `ConfigurationException` giving the `phpunit.xml.dist` line that turns it on. |
| No `config/app.php` under `applicationBasePath()` | `ConfigurationException` naming the directory. |
| The app throws an exception it doesn't handle | The test fails with that exception; it isn't turned into a 500. |
