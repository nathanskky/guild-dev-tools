# Boot failures

> For app developers whose app refuses to boot with a persona sign-in error.

While persona sign-in is on, each of these problems stops your app at boot with the message shown.

| Cause | Message starts with |
|---|---|
| A secret is set | `Persona sign-in is enabled, but OIDC_CLIENT_SECRET is set.` (or `GROUPER_PASSWORD`; if both are set, it names `OIDC_CLIENT_SECRET`) |
| `config/personas.php` is missing | `Persona sign-in is enabled, but config/personas.php was not found.` |
| `config/personas.php` throws an `Error` | `Problem encountered while reading config/personas.php:` |
| `config/personas.php` returns something else | `config/personas.php must return a PersonaRoster.` |
| The roster breaks a rule | The rule's message; `PersonaRoster`'s docblock lists the rules. |
| `addAuthorization()` is given `IdentitySource::Cas` | The framework's message: a chosen sign-in method needs `IdentitySource::Oidc`. |
