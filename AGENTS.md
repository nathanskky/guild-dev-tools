# AGENTS.md: guild/dev-tools

> For maintainers of guild/dev-tools and their agents. App developers start at [docs/index.md](docs/index.md).

The [workspace handbook](../AGENTS.md) covers cross-repo workflow and conventions.

guild/dev-tools provides persona sign-in and its feature-test kit. It's a framework
[extension](https://github.com/nathanskky/guild-framework/blob/main/README.md#extensions) of type
`guild-extension`, and apps install it with `require-dev`.

## Verify

`composer check` must pass.

## Keeping docs current

Every code change gets a docs check in the same commit. Check README, `docs/`, AGENTS.md, the docblocks
of public classes and the comments at the changed code for anything the change makes wrong or incomplete:

- Update a page that describes the changed behavior.
- If no page covers new public behavior, add one to `docs/` and list it in `docs/index.md`.
- If a maintainer needs a fact the code doesn't show, add a comment where the code is.
- If the behavior is gone, remove its page and its index entry.
- If the change adds or renames a domain term, update `docs/reference/glossary.md`.

Then check the pages that link to a changed page for text that's now wrong, and link a new page from
related pages. A non-obvious choice gets a decision record in `docs/decisions/`.

Public behavior here includes the roster's arguments and rules, the picker and banner text, the
environment variables, boot failure messages, and every `Testing\` method an app calls. The roster
classes and the test kit keep their API reference in docblocks, so a change to them updates the docblock.

## Conventions

- Types an app must not use carry `@internal`; keep that marker on anything new that isn't for apps.
- Every `ConfigurationException` says what is wrong and the one change that fixes it.
- Session keys start with `guild_dev_tools_`.
- Docs examples use an app's own namespace, such as `App\Authorization\AppPermission`, never `Guild\Starter\…`.

## Tests

- Don't use PHPUnit mocks or stubs; use the framework's `Testing\` fakes and Monolog's `TestHandler`.
- Give each test class `#[CoversClass]` plus `#[UsesClass]`, or `#[CoversNothing]` for an acceptance test.
- Put test classes under `Guild\DevTools\Test\`.
- Name fixture personas, groups and the role through `tests/Support/FixtureRoster`, and permissions through `FixturePermission`.
- Pass the environment to `PersonaSignInExtension`'s constructor, never through `putenv()`.
- A test that needs the flag off saves and restores it, as `PersonaSignInOffTest` does.
- Pair each negative string assertion with a positive one on the same string, in the same test.

`putenv()` changes the environment for the whole PHPUnit process. A negative assertion alone still passes
after a rename, because the old string is absent everywhere.

## Gotchas

- `PersonaBanner` must stay prepended, or it misses the 403 and 503 pages.
- Bind the classes passed to `useSignInMethod()` and `useGroupDirectory()` in `boot()`, never `configure()`.
- Factories must resolve `PersonaRoster` from the container, or inline test personas vanish.
- The test kit seeds grants once per test, not per request.
- The flag accepts `1` because PHPUnit writes `true` as `1`.

The comment at each gotcha's code explains the reason.
