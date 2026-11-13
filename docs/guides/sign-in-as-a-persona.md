# Sign in as a persona

> For app developers trying a page as different users on their own machine.

This guide shows you how to sign in as a persona, switch to another, sign out, and simulate a Grouper
outage in your browser.

## Before you start

- Persona sign-in is on; if it isn't, follow [Turn persona sign-in on and off](../start/turn-persona-sign-in-on-and-off.md).
- You reach the app on `localhost`, `127.0.0.1` or `[::1]`.

On any other host name, persona sign-in answers 404.

## Sign in

1. Open `/_dev/personas`, or open a page that needs sign-in, which sends you there.
2. Read each persona's groups, roles and permissions to choose the one you need.
3. Select **Sign in as** followed by the persona's name.

You return to the page you opened. If a form submission sent you to the picker, you land on `/` instead,
because only a `GET` or `HEAD` request is returned to.

The banner at the top of every page says who you are signed in as, for example
`Persona sign-in: signed in as Erin Editor (editor)`. It also appears on 403 and 503 pages. Responses
that aren't HTML, and HTML with no `<body>` tag such as an htmx fragment, get no banner.

## Switch persona

1. Select **Switch persona** in the banner.
2. Choose another persona.

You return to the page you were on, signed in as the new persona.

## Sign out

To become a guest, select **Sign out** in the banner, or **Continue as a guest** on the picker. You land
on the picker.

## Simulate a Grouper outage

To see how your app behaves when Grouper is down, select **Simulate a Grouper outage** in the banner or on
the picker. The banner then shows `Grouper outage simulated`.

The outage applies to your browser session, whoever you sign in as. Membership the framework has already
cached keeps answering, as it would in production, so a page fails only once that cache runs out. To see
the 503 page sooner, lower `membershipTtl` in `config/authorization.php`; the
[framework README](https://github.com/nathanskky/guild-framework/blob/main/README.md#setting-it-up) describes it.

To end the outage, select **End the Grouper outage**.

To make Grouper unavailable for one persona every time, give that persona
`directory: DirectoryStatus::Unavailable` in [the roster](write-the-roster.md).

## If a persona disappears

If you remove your current persona from `config/personas.php`, your next request signs you out. The
picker then tells you which persona went missing.

## Related

- [How persona sign-in works](../concepts/how-persona-sign-in-works.md) explains what the banner and
  picker sit on top of.
- [Safety](../concepts/safety.md) explains why the picker answers only on your own machine.
