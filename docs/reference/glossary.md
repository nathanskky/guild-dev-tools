# Glossary

> For app developers meeting a dev-tools term for the first time.

| Term | Meaning | In code |
|---|---|---|
| Persona | A made-up user for local development and tests, with a username, IU Login claims and Grouper groups. | `Persona` |
| Persona key | The short, stable name you refer to a persona by, such as `editor`, in the roster, in tests and in the session. | `PersonaKey` |
| Roster | The personas, their role grants and the System Admin group that `config/personas.php` returns. | `PersonaRoster` |
| Persona group | A real Grouper group, by system name and ACM label, that a persona is in or a role grant maps to. | `PersonaGroup` |
| Role grant | A role, the groups mapped to it and the permissions it holds, written to the framework's grant tables. | `RoleGrant` |
| Persona sign-in | Signing in as a persona instead of through IU Login, with groups from the roster instead of Grouper. | `PersonaSignIn` |
| The persona flag | The `PERSONA_SIGN_IN_ENABLED` environment variable, which turns persona sign-in on. | `PersonaSignInExtension::isEnabledIn()` |
| Picker | The page at `/_dev/personas` where you choose a persona, or continue as a guest. | `PersonaSignIn::PICKER_PATH`; `PersonaPickerPage` (internal) |
| Banner | The bar on every full HTML page that says who you're signed in as, with controls to switch persona, sign out and simulate a Grouper outage. | `PersonaBanner` (internal) |
| Directory outage | A simulated Grouper outage, for your browser session, one persona, or one test visit. | `DirectoryStatus`, `Visit::duringDirectoryOutage()` |
| Test kit | The trait and classes that let a feature test visit your app as a persona or a guest. | `VisitsApplication` |
| IU Login claim | A fact IU Login releases about the signed-in user, such as `username`, `name` or `email`. | `Persona::$attributes` |

dev-tools is a framework [extension](https://github.com/nathanskky/guild-framework/blob/main/docs/concepts/extensions.md).

framework also uses the word "claim", for an extension's choice of a framework service such as the
sign-in method. This package's docs use "claim" only for an IU Login claim.
