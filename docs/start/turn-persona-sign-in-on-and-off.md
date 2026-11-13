# Turn persona sign-in on and off

> For app developers working in an app created from guild/starter.

When you finish, you can switch your local app between persona sign-in and real IU Login, and tell which
one is on.

Your app already has dev-tools, a starting roster in `config/personas.php`, and the `phpunit.xml.dist`
lines that turn persona sign-in on for tests. The start scripts on this page are part of your app.
starter's [Run locally](https://github.com/nathanskky/guild-starter/blob/main/docs/start/run-locally.md) describes what each script needs.

## Turn it on

1. From your app's folder, start the stack with persona sign-in:

   ```bash
   docker/up-personas.sh
   ```

   Every full HTML page (a page with a `<body>` tag) now opens with a banner that reads
   `Persona sign-in: not signed in`.

2. Open `https://localhost:8443/_dev/personas` and choose **Sign in as Erin Editor**.

   You land on `/`, and the banner reads `Persona sign-in: signed in as Erin Editor (editor)`.

## Turn it off

To switch back to real IU Login and Grouper, stop the stack and start it with the other script:

```bash
docker/up-iu-services.sh
```

The banner no longer appears, and signing in goes through IU Login.

## Tests

Your tests always run with persona sign-in on, regardless of which script started the stack.
`phpunit.xml.dist` sets the persona flag with `force="true"`.

## Next

- [Write the roster](../guides/write-the-roster.md) with the groups and grants your app needs.
- [Sign in as a persona](../guides/sign-in-as-a-persona.md) to switch persona, sign out or simulate a Grouper outage.
- [Test as a persona](../guides/test-as-a-persona.md) in your feature tests.
