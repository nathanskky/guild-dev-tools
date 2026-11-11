# guild/dev-tools

Persona sign-in for local development and tests of Guild applications. A persona is a made-up user with a
username, IU Login-style attributes and Grouper groups. With persona sign-in on, people sign in as personas
instead of through IU Login, and their groups come from the same list instead of Grouper. Everything after
that is the application's real code: the framework's membership cache, the grant tables, the Gate and the
policies.

**Install it with `require-dev` only.** It is how a local stack lets anyone become anyone, so it must never
be in a production build.

## Requirements

- PHP `~8.5`
- `guild/framework` `^2.1`, with `addAuthentication()` and `addAuthorization(IdentitySource::Oidc, ...)`

## Installation

```bash
composer require --dev guild/dev-tools
```

Composer needs the VCS repositories for `guild/dev-tools`, `guild/framework`, `guild/access`, `guild/grouper`
and `guild/rivet` in the application's `composer.json`; `guild/starter` lists them.

The package has Composer type `guild-extension`, so `guild/framework` finds it with no change to
`config/app.php`. It does nothing until it is turned on.

## Turning it on

Set `PERSONA_SIGN_IN_ENABLED=true` in the environment. Any other value, or none, leaves the application
signing in through IU Login.

While it is on, the application:

- signs people in as personas from `config/personas.php` (`/framework/sign-out` signs them out)
- reads their groups from that file instead of Grouper, so `config/grouper.php` is not read
- takes the System Admin group label from the file, when it names one
- does not read `config/authentication.php`

## The roster: `config/personas.php`

```php
<?php

use Guild\DevTools\Persona\{DirectoryStatus, Persona, PersonaGroup, PersonaRoster, RoleGrant};
use Guild\Starter\Authorization\AppPermission;

$editors = new PersonaGroup('iu:roles:sys:acm:starter-editors', 'Starter Editors');
$admins  = new PersonaGroup('iu:roles:sys:acm:starter-admins', 'Starter Admins');

return new PersonaRoster(
    personas: [
        new Persona('editor', username: 'persona-editor', name: 'Erin Editor', groups: [$editors]),
        new Persona('newcomer', username: 'persona-newcomer', name: 'Nico Newcomer'),
        new Persona('admin', username: 'persona-admin', name: 'Ada Admin', groups: [$admins]),
        new Persona('outage', username: 'persona-outage', name: 'Grouper Down', directory: DirectoryStatus::Unavailable),
    ],
    grants: [
        new RoleGrant('Editor', groups: [$editors], permissions: [AppPermission::ExampleView]),
    ],
    systemAdminGroup: $admins,
);
```

- A persona's key (`'editor'`) is lower-case letters, digits and hyphens, at most 40 characters.
- `attributes:` adds IU Login claims such as `email`; `username` and `name` come from their own arguments.
- Usernames start with `persona-`, so none is a real IU username. `usernamePrefix:` changes the prefix.
- Groups name real Grouper system names and ACM labels, so the same grants work in production.
- Each group has one label, and no two groups share one.
- `DirectoryStatus::Unavailable` makes Grouper unreachable for that persona, to show the 503 page.

`grants:` are written into the framework's grant tables by `PersonaGrantSeeder`, through the same models the
administration pages use. It adds and reactivates and never deletes, so grants added through
`/framework/authorization` survive. Rows it writes say `persona-roster` in `created_by`.

A roster that breaks a rule fails at boot with a message naming the persona or group and the fix.

## Safety

Persona sign-in lets anyone become anyone. The first three layers each keep it out of production on their own;
the rest limit what it can do where it does run:

| Layer | What it does |
|---|---|
| Absent from production | Installed with `require-dev`, so a `--no-dev` build does not contain it |
| Off unless chosen | Inactive unless `PERSONA_SIGN_IN_ENABLED=true` |
| Refuses real credentials | Boot fails if `OIDC_CLIENT_SECRET` or `GROUPER_PASSWORD` is set while it is on |
| Local hosts only | Signing in answers 404 unless the request's host is `localhost`, `127.0.0.1` or `::1`, so an app reached under a real host name never offers it. The host comes from the client's `Host` header, so this is no defence against a crafted request |
| Session hygiene | The session ID and CSRF token are replaced on every switch and sign-out |
| Audited | Every switch and sign-out is logged at `notice` with the persona's key |

## Contributing

See [`AGENTS.md`](AGENTS.md).
