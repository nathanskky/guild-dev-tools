# guild/dev-tools

Persona sign-in for local development and tests of Guild apps. You sign in as made-up users from a roster
instead of through IU Login, and your app's real authorization code decides what each one may do.

**Install it with `require-dev` only.** Persona sign-in lets anyone sign in as anyone, so it must never be
in a production build.

## Install

Add this package's VCS repository to `repositories` in your app's `composer.json`:

```json
{ "type": "vcs", "url": "https://github.com/nathanskky/guild-dev-tools.git" }
```

Then install it:

```bash
composer require --dev guild/dev-tools
```

Your `config/app.php` must call `addAuthentication()`, or the app never uses persona sign-in. If it also
calls `addAuthorization()`, it must pass `IdentitySource::Oidc`.

## Start here

[Turn persona sign-in on and off](docs/start/turn-persona-sign-in-on-and-off.md) shows you how to switch
an app created from guild/starter between personas and IU Login.

## All docs

The [docs index](docs/index.md) lists every guide, concept and reference page.
