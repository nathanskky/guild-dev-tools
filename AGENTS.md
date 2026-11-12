# AGENTS.md: guild/dev-tools

Cross-repo workflow and conventions: [workspace handbook](../AGENTS.md).

This repo is `guild/dev-tools`: persona sign-in and its feature-test kit, a `guild-extension` that apps
install with `require-dev`. App-facing behaviour is documented in [docs/index.md](docs/index.md).

## Verify

- `composer install`
- `composer check` runs PHPUnit, then PHPStan level 10 over `src/`, then Pint; all three must pass.
- `php bin/check-docs dev-tools`, from the workspace root, after any change to README, `docs/` or `internals/`.

## Keeping docs current

A change to public behaviour updates the page `docs/index.md` lists for it, in the same commit; a
non-obvious choice gets a decision record in `docs/decisions/`.

Public behaviour here includes the roster's arguments and rules, the picker and banner text, the
environment variables, boot failure messages, and every `Testing\` method an app calls.

## Conventions

- Types an app must not use carry `@internal`; keep that marker on anything new that isn't for apps.
- Every `ConfigurationException` says what is wrong and the one change that fixes it.
- Session keys start with `guild_dev_tools_`.
- Docs examples use an app's own namespace, such as `App\Authorization\AppPermission`, never `Guild\Starter\…`.

## Top landmines

- `PersonaBanner` must stay prepended, or it misses the 403 and 503 pages; see [extension wiring](internals/extension-wiring.md#middleware-order).
- Bind claimed classes in `boot()`, never `configure()`; see [extension wiring](internals/extension-wiring.md#binding-in-boot).
- Factories must resolve `PersonaRoster` from the container, or inline test personas vanish; see [extension wiring](internals/extension-wiring.md#binding-in-boot).
- The test kit seeds grants once per test, not per request; see [testing](internals/testing.md#test-kit-landmines).
- The flag accepts `1` because PHPUnit writes `true` as `1`; see [extension wiring](internals/extension-wiring.md#environment).

## Internals — read when

- [internals/extension-wiring.md](internals/extension-wiring.md) — read when changing `PersonaSignInExtension`, the picker, the banner, sign-out or the grant seeder.
- [internals/testing.md](internals/testing.md) — read when writing tests here or changing `src/Testing/`.
