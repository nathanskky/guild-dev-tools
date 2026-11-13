# guild/dev-tools docs

> For app developers using persona sign-in and its test kit.

New to the terms? The [glossary](reference/glossary.md) defines persona, roster and the rest.

## Start

- [Turn persona sign-in on and off](start/turn-persona-sign-in-on-and-off.md) — read when switching your starter app between personas and IU Login.

## Guides

- [Write the roster](guides/write-the-roster.md) — read when choosing the personas, groups and grants in `config/personas.php`.
- [Sign in as a persona](guides/sign-in-as-a-persona.md) — read when trying your app as another user, or during a Grouper outage, in the browser.
- [Test as a persona](guides/test-as-a-persona.md) — read when writing a feature test that signs in.
- [Remove dev-tools](guides/remove-dev-tools.md) — read when taking persona sign-in out of an app.

## Concepts

- [How persona sign-in works](concepts/how-persona-sign-in-works.md) — read when you need to know what's faked and what's real.
- [Safety](concepts/safety.md) — read when checking that persona sign-in can't reach production.

## Reference

- [Glossary](reference/glossary.md) — read when a dev-tools term is new to you.
- [Environment variables](reference/environment-variables.md) — read when setting or checking a variable persona sign-in reads.
- [Boot failures](reference/boot-failures.md) — read when your app refuses to boot with a persona sign-in error.

The roster classes and the test kit document their arguments and methods in docblocks, which your IDE
shows on hover.
