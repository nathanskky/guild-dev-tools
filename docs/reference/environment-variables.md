# Environment variables

> For app developers setting or checking a variable persona sign-in reads.

| Variable | Read as | Effect |
|---|---|---|
| `PERSONA_SIGN_IN_ENABLED` | `true` or `1`, in any case, ignoring surrounding whitespace | Turns persona sign-in on. Any other value, or none, leaves it off. |
| `OIDC_CLIENT_SECRET` | Set when non-blank in the process environment or `$_ENV` | While persona sign-in is on, boot fails with a `ConfigurationException` naming this variable. |
| `GROUPER_PASSWORD` | Set when non-blank in the process environment or `$_ENV` | While persona sign-in is on, boot fails with a `ConfigurationException` naming this variable. |

[Boot failures](boot-failures.md) lists each message.
