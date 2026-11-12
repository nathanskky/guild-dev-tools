# Roster reference

> For app developers looking up an argument or rule of `config/personas.php`.

`config/personas.php` returns a `PersonaRoster`. All classes are in `Guild\DevTools\Persona`.
`username`, `name`, `identifier`, `label` and `role` are trimmed; other strings, persona keys included,
are used as given.

## `PersonaRoster`

| Argument | Type | Default | Description |
|---|---|---|---|
| `personas` | `list<Persona>` | required | The personas you can sign in as. |
| `grants` | `list<RoleGrant>` | `[]` | Roles, the groups mapped to them and their permissions. |
| `systemAdminGroup` | `?PersonaGroup` | `null` | Its label replaces the System Admin label in `config/authorization.php` while persona sign-in is on. |
| `usernamePrefix` | `string` | `'persona-'` | Every persona username must start with it. `''` turns the check off. |

## `Persona`

| Argument | Type | Default | Description |
|---|---|---|---|
| `key` | `string\|PersonaKey` | required | The name `signedInAs()` and the session use. |
| `username` | `string` | required | The `username` claim. Must not be blank. |
| `name` | `string` | required | The `name` claim, shown on the picker and banner. Must not be blank. |
| `groups` | `list<PersonaGroup>` | `[]` | The Grouper groups the persona is in. |
| `directory` | `DirectoryStatus` | `Available` | `Unavailable` makes Grouper unreachable for this persona. |
| `attributes` | `array<string, string>` | `[]` | Further IU Login claims, such as `email`. Must not contain `username` or `name`. |

A persona key is lower-case letters, digits and hyphens, starts with a letter, and is at most 40
characters.

## `PersonaGroup`

| Argument | Type | Description |
|---|---|---|
| `identifier` | `string` | The Grouper system name, such as `iu:roles:sys:acm:reports-editors`. Grants match on it. Must not be blank. |
| `label` | `string` | The ACM label, such as `Reports Editors`. The System Admin check matches on it. Must not be blank. |

## `RoleGrant`

| Argument | Type | Default | Description |
|---|---|---|---|
| `role` | `string` | required | The role name. Must not be blank. |
| `groups` | `list<PersonaGroup>` | `[]` | The groups mapped to the role. |
| `permissions` | `list<Permission>` | `[]` | Cases of the app's permission enum. |

## `DirectoryStatus`

| Case | Effect |
|---|---|
| `Available` | Grouper answers with the persona's groups. |
| `Unavailable` | Grouper is unreachable for this persona. |

## Rules

A roster that breaks a rule fails at boot with a `ConfigurationException` naming the persona or group and
the fix.

| Rule | Checked by |
|---|---|
| At least one persona. | `PersonaRoster` |
| Each persona has its own key. | `PersonaRoster` |
| Each persona has its own username. | `PersonaRoster` |
| Each username starts with `usernamePrefix`, unless it is `''`. | `PersonaRoster` |
| A group identifier has one label everywhere it appears. | `PersonaRoster` |
| No two group identifiers share a label. | `PersonaRoster` |
| No two grants name the same role. | `PersonaRoster` |
| Keys, blank values and reserved attributes, as listed in the tables. | Each class's constructor |

## How grants are written

| Event | Effect |
|---|---|
| The picker opens | The roster's grants are written to the framework's grant tables. |
| A test makes its first request | The same, once per test. |
| A row is missing | It's inserted active, with `created_by` set to `persona-roster`. |
| A row is inactive | It's reactivated, with `updated_by` set to `persona-roster`. |
| A row isn't in the roster | It's left as it is. Nothing is deleted or detached. |
| A group's label is taken by another group | The new group is named by its identifier. |
| `grants` is empty | Nothing is written and the database isn't touched. |

Rows are matched by group identifier, role name and permission value. A group you renamed keeps its name.
