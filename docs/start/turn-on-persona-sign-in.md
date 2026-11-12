# Turn on persona sign-in

> For app developers adding persona sign-in to a Guild app for local development and tests.

When you finish, you can sign in to your app on your machine as a persona from `/_dev/personas`. Your
feature tests are ready to sign in as personas too.

Apps created from [guild/starter](https://github.com/nathanskky/guild-starter) come with these steps done.

## Before you start

- Your app runs on PHP `~8.5` with `guild/framework` `^2.1`.
- Your `config/app.php` calls `addAuthentication()`.
- If it also calls `addAuthorization()`, it passes `IdentitySource::Oidc`.

## Steps

1. Add the dev-tools repository to `repositories` in your app's `composer.json`, beside the ones the
   [framework README](https://github.com/nathanskky/guild-framework/blob/main/README.md#installation) lists:

   ```json
   { "type": "vcs", "url": "https://github.com/nathanskky/guild-dev-tools.git" }
   ```

   Composer can now find `guild/dev-tools`.

2. Install the package as a development dependency:

   ```bash
   composer require --dev guild/dev-tools
   ```

   Composer adds `guild/dev-tools` under `require-dev`. Your app still signs in through IU Login.

3. Create `config/personas.php` with a starting roster:

   ```php
   <?php

   declare(strict_types=1);

   use Guild\DevTools\Persona\{Persona, PersonaGroup, PersonaRoster};

   $admins = new PersonaGroup('iu:roles:sys:acm:reports-admins', 'Reports Admins');

   return new PersonaRoster(
       personas: [
           new Persona('admin', username: 'persona-admin', name: 'Ada Admin', groups: [$admins]),
           new Persona('newcomer', username: 'persona-newcomer', name: 'Nico Newcomer'),
       ],
       systemAdminGroup: $admins,
   );
   ```

   Nothing changes yet. The app reads this file only while persona sign-in is on.

4. Set `PERSONA_SIGN_IN_ENABLED=true` in the environment your local stack starts with, and leave
   `OIDC_CLIENT_SECRET` and `GROUPER_PASSWORD` unset.

   Nothing changes until the stack restarts, because PHP reads its environment when its process starts.

5. Restart your local stack.

   Every full HTML page, one with a `<body>` tag, now opens with a banner that reads
   `Persona sign-in: not signed in`. If either secret is set, the app refuses to boot and the error names
   the variable.

6. Open `/_dev/personas` on `localhost`, for example `https://localhost:8443/_dev/personas`, and choose
   **Sign in as Ada Admin**.

   You land on `/`, and the banner reads `Persona sign-in: signed in as Ada Admin (admin)`.

7. Add these lines inside `<php>` in `phpunit.xml.dist`:

   ```xml
   <env name="PERSONA_SIGN_IN_ENABLED" value="true" force="true"/>
   <env name="OIDC_CLIENT_SECRET" value="" force="true"/>
   <env name="GROUPER_PASSWORD" value="" force="true"/>
   ```

   `force="true"` makes these values win over any already set, such as in a container started for real
   IU Login. Test cases that use the `VisitsApplication` trait now sign in as personas;
   [Test as a persona](../guides/test-as-a-persona.md) shows how to write one.

## Next

- [Write the roster](../guides/write-the-roster.md) with the groups and grants your app needs.
- [Test as a persona](../guides/test-as-a-persona.md) in your feature tests.
- [How persona sign-in works](../concepts/how-persona-sign-in-works.md) explains what stays real.
