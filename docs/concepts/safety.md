# Safety

> For app developers and reviewers checking that persona sign-in can't reach production.

```mermaid
flowchart TD
  accTitle: The checks persona sign-in passes before it runs
  B{"Installed?<br/>require-dev only"} -->|"no: production build"| X1["No persona sign-in"]
  B -->|yes| F{"PERSONA_SIGN_IN_ENABLED<br/>true or 1?"}
  F -->|no| X2["IU Login and Grouper, as usual"]
  F -->|yes| C{"OIDC_CLIENT_SECRET or<br/>GROUPER_PASSWORD set?"}
  C -->|yes| X3["Boot fails"]
  C -->|no| H{"Request host local?"}
  H -->|no| X4["404"]
  H -->|yes| OK["Persona sign-in,<br/>with banner and log"]
```

<details>
<summary>Diagram as text</summary>

Persona sign-in runs only after four checks, in this order:

1. Is dev-tools installed? It's a `require-dev` package, so a production build has no persona sign-in.
2. Is `PERSONA_SIGN_IN_ENABLED` `true` or `1`? If not, the app uses IU Login and Grouper as usual.
3. Is `OIDC_CLIENT_SECRET` or `GROUPER_PASSWORD` set? If so, boot fails.
4. Is the request's host local? If not, the request gets a 404.

When all four pass, persona sign-in runs, with the banner and the log.

</details>

Persona sign-in lets anyone become anyone, so it's built in layers. The first three layers each keep it
out of production on their own. The rest limit what it can do where it does run.

| Layer | What it does |
|---|---|
| Absent from production | Installed with `require-dev`, so a `--no-dev` build doesn't contain it. |
| Off unless chosen | Inactive unless `PERSONA_SIGN_IN_ENABLED` is `true` or `1`. |
| Refuses real credentials | Boot fails if `OIDC_CLIENT_SECRET` or `GROUPER_PASSWORD` is set while it's on. |
| Local hosts only | Sign-in and the picker answer only on `localhost`, `127.0.0.1` or `::1`; other hosts get 404 and no banner. |
| Visible | A banner on every full HTML page (one with a `<body>` tag) says who you're signed in as. |
| Session hygiene | The session ID and CSRF token are replaced on every switch and sign-out. |
| Audited | Each switch and sign-out is logged at `notice` with the persona's key, as is each outage toggle. |

The host check reads the request's `Host` header, which the client controls. So it stops accidents, not a
crafted request; keeping the package out of production builds stops those.

Refusing real credentials covers the likeliest mistake. A stack with IU Login or Grouper secrets is set up
for real users, so persona sign-in won't start beside them. A secret counts as set if it's
non-blank in either the process environment or `$_ENV`.

## Related

- [Environment variables](../reference/environment-variables.md) lists the variables and the values each accepts.
- [Boot failures](../reference/boot-failures.md) lists what each refusal to boot says.
- [How persona sign-in works](how-persona-sign-in-works.md) shows what persona sign-in replaces.
