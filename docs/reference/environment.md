# Environment reference

> For app developers looking up the environment variables persona sign-in reads.

| Variable | Read as | Effect |
|---|---|---|
| `PERSONA_SIGN_IN_ENABLED` | `true` or `1`, in any case, ignoring surrounding whitespace | Turns persona sign-in on. Any other value, or none, leaves it off. |
| `OIDC_CLIENT_SECRET` | Set when non-blank | While persona sign-in is on, boot fails with a `ConfigurationException` naming this variable. |
| `GROUPER_PASSWORD` | Set when non-blank | While persona sign-in is on, boot fails with a `ConfigurationException` naming this variable. |

## Sources

- Persona sign-in reads the process environment and `$_ENV`.
- For `PERSONA_SIGN_IN_ENABLED`, the process environment wins over `$_ENV`.
- For the two secrets, a non-blank value in either source counts as set.
- PHPUnit's `<env name="…" value="true"/>` reaches PHP as `1`.
- Without `force="true"`, PHPUnit leaves a variable already set in the process environment unchanged.

## Boot failures

| Cause | Message starts with |
|---|---|
| A secret is set | `Persona sign-in is enabled, but OIDC_CLIENT_SECRET is set.` (or `GROUPER_PASSWORD`) |
| `config/personas.php` is missing | `Persona sign-in is enabled, but config/personas.php was not found.` |
| `config/personas.php` throws an `Error` | `Problem encountered while reading config/personas.php:` |
| `config/personas.php` returns something else | `config/personas.php must return a PersonaRoster.` |
| The roster breaks a rule | The rule's message; see [Roster reference](roster.md#rules). |
| `addAuthorization()` is given `IdentitySource::Cas` | The framework's message: a chosen sign-in method needs `IdentitySource::Oidc`. |

When both secrets are set, the message names `OIDC_CLIENT_SECRET`.
