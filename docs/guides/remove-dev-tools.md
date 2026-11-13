# Remove dev-tools

> For app developers taking persona sign-in and its test kit out of an app.

This guide shows you how to remove guild/dev-tools from an app created from guild/starter. Afterward,
your app signs in only through IU Login, locally as well as in production.

## Before you start

- Your app's stack can start with `docker/up-iu-services.sh`, which needs the 1Password setup starter's
  [README](https://github.com/nathanskky/guild-starter) describes.

## Remove the package

1. Remove the package:

   ```bash
   composer remove --dev guild/dev-tools
   ```

2. In `composer.json`, delete the `repositories` entry whose `url` is
   `https://github.com/nathanskky/guild-dev-tools.git`. Keep the other Guild entries; framework still
   needs them.

3. Delete `config/personas.php`.

4. In `phpunit.xml.dist`, delete the `PERSONA_SIGN_IN_ENABLED`, `OIDC_CLIENT_SECRET` and
   `GROUPER_PASSWORD` lines inside `<php>`, and the comment above them.

5. Rewrite or delete each test that uses the `VisitsApplication` trait or another `Guild\DevTools` class.
   Nothing else provides them.

6. Delete `docker/up-personas.sh`. In `docker/docker-compose.yml`, delete the `PERSONA_SIGN_IN_ENABLED`
   line and the comment above it.

   Both turn the persona flag on. With the flag on and dev-tools gone, `config/authentication.php`
   refuses to boot.

7. Delete the persona sign-in check at the top of `config/authentication.php`, and the test in
   `tests/Configuration/AuthenticationConfigTest.php` that expects its message. Nothing turns the flag on
   now.

8. From now on, start the stack with `docker/up-iu-services.sh`.

To check, run `composer test`, then start the stack: no page shows the persona banner.
