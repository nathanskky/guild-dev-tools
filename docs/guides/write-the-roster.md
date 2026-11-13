# Write the roster

> For app developers deciding who they can sign in as locally and in tests.

This guide shows you how to write `config/personas.php` for an app with real roles. The roster holds a
persona for each kind of user, the groups they're in, and the grants those groups hold.

## Before you start

- Your app has dev-tools; apps created from guild/starter do.
- Your app has its permission enum, for example `App\Authorization\AppPermission` with `ReportsView` and
  `ReportsEdit` cases.
- You know the Grouper system name and ACM label of each group your app's grants use in production.

## Declare each group once

To keep the grants valid in production, give each `PersonaGroup` the real Grouper system name and ACM
label:

```php
$viewers = new PersonaGroup('iu:roles:sys:acm:reports-viewers', 'Reports Viewers');
$editors = new PersonaGroup('iu:roles:sys:acm:reports-editors', 'Reports Editors');
$admins  = new PersonaGroup('iu:roles:sys:acm:reports-admins', 'Reports Admins');
```

Reuse each variable wherever the group appears. A group declared twice with different labels fails at
boot.

## Add the personas

Add one `Persona` for each kind of user. Include one in no groups, to see what a stranger sees, and one
with `DirectoryStatus::Unavailable`, to see the 503 page a Grouper outage gives.

- Use a key of lower-case letters, digits and hyphens, starting with a letter; `signedInAs()` takes it.
- Start each username with `persona-`, so no persona shares a real IU username.
- To add IU Login claims such as `email`, pass `attributes:`; `username:` and `name:` set their own claims.

## Grant roles to groups

For each role, add a `RoleGrant` naming the groups mapped to it and the permissions it holds. Give each
role one grant.

## Name the System Admin group

To let a persona administer the app, put it in a group and pass that group as `systemAdminGroup:`. While
persona sign-in is on, this group's label replaces the one in `config/authorization.php`.

## The whole file

```php
<?php

declare(strict_types=1);

use App\Authorization\AppPermission;
use Guild\DevTools\Persona\{DirectoryStatus, Persona, PersonaGroup, PersonaRoster, RoleGrant};

$viewers = new PersonaGroup('iu:roles:sys:acm:reports-viewers', 'Reports Viewers');
$editors = new PersonaGroup('iu:roles:sys:acm:reports-editors', 'Reports Editors');
$admins  = new PersonaGroup('iu:roles:sys:acm:reports-admins', 'Reports Admins');

return new PersonaRoster(
    personas: [
        new Persona('viewer', username: 'persona-viewer', name: 'Vic Viewer', groups: [$viewers]),
        new Persona('editor', username: 'persona-editor', name: 'Erin Editor', groups: [$editors],
            attributes: ['email' => 'persona-editor@example.edu']),
        new Persona('newcomer', username: 'persona-newcomer', name: 'Nico Newcomer'),
        new Persona('admin', username: 'persona-admin', name: 'Ada Admin', groups: [$admins]),
        new Persona('outage', username: 'persona-outage', name: 'Grouper Down', directory: DirectoryStatus::Unavailable),
    ],
    grants: [
        new RoleGrant('Viewer', groups: [$viewers], permissions: [AppPermission::ReportsView]),
        new RoleGrant('Editor', groups: [$editors], permissions: [AppPermission::ReportsView, AppPermission::ReportsEdit]),
    ],
    systemAdminGroup: $admins,
);
```

If your app has authorization off, leave out `grants:`. With no grants, persona sign-in doesn't touch the
database.

## Check the roster

1. Open `/_dev/personas` on your local stack.
2. Check that each persona lists the roles and permissions you expect.

If the roster breaks a rule, the app refuses to boot, and the error names the persona or group and the fix.

## Related

- `PersonaRoster`, `Persona`, `PersonaGroup` and `RoleGrant` document every argument and rule in their docblocks.
- [Test as a persona](test-as-a-persona.md) shows how to add a persona for one test only.
