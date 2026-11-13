# AGENTS.md: guild/dev-tools

> For maintainers of guild/dev-tools and their agents. App developers start at [docs/index.md](docs/index.md).

Cross-repo workflow and conventions: [workspace handbook](../AGENTS.md).

This repo is `guild/dev-tools`: persona sign-in and its feature-test kit, a framework
[extension](https://github.com/nathanskky/guild-framework/blob/main/README.md#extensions) of type
`guild-extension` that apps install with `require-dev`.

## Verify

`composer check` must pass.

## Keeping docs current

Every code change gets a docs check in the same commit. Check README, `docs/`, AGENTS.md, `internals/`
and the docblocks of public classes for anything the change makes wrong or incomplete:

- Update a page that describes the changed behavior.
- If no page covers new public behavior, add one to `docs/` and list it in `docs/index.md`.
- If a new maintainer fact needs a page, add it to `internals/` and list it below.
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

## Gotchas

- `PersonaBanner` must stay prepended, or it misses the 403 and 503 pages; see [extension wiring](internals/extension-wiring.md#middleware-order).
- Bind the classes passed to `useSignInMethod()` and `useGroupDirectory()` in `boot()`, never `configure()`; see [extension wiring](internals/extension-wiring.md#binding-in-boot).
- Factories must resolve `PersonaRoster` from the container, or inline test personas vanish; see [extension wiring](internals/extension-wiring.md#binding-in-boot).
- The test kit seeds grants once per test, not per request; see [testing](internals/testing.md#test-kit-gotchas).
- The flag accepts `1` because PHPUnit writes `true` as `1`; see [extension wiring](internals/extension-wiring.md#environment).

## Internals — read when

- [internals/extension-wiring.md](internals/extension-wiring.md) — read when changing `PersonaSignInExtension`, the picker, the banner, sign-out or the grant seeder.
- [internals/testing.md](internals/testing.md) — read when writing tests here or changing `src/Testing/`.
