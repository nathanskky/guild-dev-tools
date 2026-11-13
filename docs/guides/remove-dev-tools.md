# Remove dev-tools

> For app developers taking persona sign-in and its test kit out of an app.

This guide shows you how to remove guild/dev-tools from an app created from guild/starter. Afterward,
your app signs in only through IU Login, locally as well as in production.

## Before you start

Your app's stack must start with `docker/up-iu-services.sh`. That script needs the 1Password setup that
starter's [Manage secrets](https://github.com/nathanskky/guild-starter/blob/main/docs/guides/manage-secrets.md) describes.

## Remove the package

1. Remove the package with Composer:

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
   Those classes are no longer installed.

6. Delete `docker/up-personas.sh`. In `docker/docker-compose.yml`, delete the `PERSONA_SIGN_IN_ENABLED`
   line and the comment above it.

   Both set the persona flag. If the flag is set after dev-tools is removed, `config/authentication.php`
   stops the app at boot.

7. Delete the persona sign-in check at the top of `config/authentication.php`, and the test in
   `tests/Configuration/AuthenticationConfigTest.php` that expects its message. Nothing sets the flag
   anymore, so the check has no purpose.

8. From now on, start the stack with `docker/up-iu-services.sh`.

To confirm the removal, run `composer test`, then start the stack and check that no page shows the persona
banner.
