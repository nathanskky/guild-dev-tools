<?php

declare(strict_types=1);

namespace Guild\DevTools\Picker;

use Guild\DevTools\Persona\DirectoryStatus;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\DevTools\SignIn\PersonaSignIn;
use Guild\Framework\Admin\Csrf;
use Guild\Framework\Authorization\Permission;
use Guild\Rivet\Component\Alert;
use Guild\Rivet\Component\Badge;
use Guild\Rivet\Component\Page\Page;
use Guild\Rivet\Enum\AlertStyle;
use Guild\Rivet\Enum\BadgeStyle;
use Guild\Rivet\Html\Html;
use Guild\Rivet\Page\PageDefaults;
use Guild\Rivet\Render\Renderer;
use Guild\Rivet\Render\RenderContext;
use Guild\Rivet\Rivet;

/**
 * The persona picker as a complete Rivet page: every persona with what it
 * may do, a button to sign in as each, a guest entry and the outage toggle.
 *
 * Rendered with its own renderer, never the application's: the
 * application's navigation may itself need someone signed in. Everything
 * taken from the roster or the request is escaped.
 *
 * @internal
 */
final class PersonaPickerPage
{
    public const string TITLE = 'Persona sign-in';

    private const string HEADING = 'Sign in as a persona';

    public const string OUTAGE_PATH = PersonaSignIn::PICKER_PATH . '/outage';

    private const string SIGN_OUT_PATH = '/framework/sign-out';

    private ?Renderer $renderer = null;

    public function __construct(private readonly PersonaRoster $roster, private readonly Csrf $csrf)
    {
    }

    /**
     * @param  Persona|null  $current  The persona signed in now, or null for a guest.
     * @param  string  $return  Where to go after choosing; already checked by ReturnPath.
     * @param  string|null  $notice  Something the developer should know, e.g. why they were signed out.
     * @param  string|null  $error  Why the last form was refused.
     */
    public function render(?Persona $current, string $return, bool $outageOn, ?string $notice = null, ?string $error = null): string
    {
        $renderer = $this->renderer ??= new Renderer(
            Rivet::registry(),
            pageDefaults: new PageDefaults(appTitle: self::TITLE, homeHref: PersonaSignIn::PICKER_PATH),
        );
        $renderer->reset();
        $context = $renderer->context();

        $page = new Page(title: self::HEADING, heading: self::HEADING);
        $context->open($page);

        try {
            return $page->render($context, implode('', [
                $error === null ? '' : self::alert($context, $error, AlertStyle::Danger),
                $notice === null ? '' : self::alert($context, $notice, AlertStyle::Info),
                Html::el('p')->text(
                    'Choose who to be. Personas come from config/personas.php; persona sign-in runs on this machine only.'
                )->render(),
                $this->personaList($context, $current, $return),
                $this->guestEntry(),
                $this->outageControl($return, $outageOn),
            ]));
        } finally {
            $context->close();
        }
    }

    private function personaList(RenderContext $context, ?Persona $current, string $return): string
    {
        $list = Html::el('ul')->class('rvt-list-plain');

        foreach ($this->roster->personas as $persona) {
            $list->children(Html::el('li')->class('rvt-m-bottom-md')->html(
                $this->personaCard($context, $persona, $current !== null && $current->key->equals($persona->key), $return)
            ));
        }

        return $list->render();
    }

    private function personaCard(RenderContext $context, Persona $persona, bool $isCurrent, string $return): string
    {
        $badges = array_filter([
            $isCurrent ? new Badge('Signed in', BadgeStyle::Success)->render($context) : null,
            $this->roster->isSystemAdmin($persona) ? new Badge('System Admin', BadgeStyle::Info)->render($context) : null,
            $persona->directory === DirectoryStatus::Unavailable ? new Badge('Grouper unavailable', BadgeStyle::Danger)->render($context) : null,
        ]);

        $groups = $persona->groups === []
            ? 'No groups'
            : implode(', ', array_map(static fn (PersonaGroup $group): string => $group->label, $persona->groups));

        $details = Html::el('dl')->children(
            Html::el('dt')->text('Key'),
            Html::el('dd')->children(Html::el('code')->text($persona->key->value)),
            Html::el('dt')->text('Username'),
            Html::el('dd')->text($persona->username),
            Html::el('dt')->text('Groups'),
            Html::el('dd')->text($groups),
            Html::el('dt')->text('Roles'),
            Html::el('dd')->html($this->roles($persona)),
        );

        $form = Html::el('form')->attr('method', 'post')->attr('action', PersonaSignIn::PICKER_PATH)->children(
            $this->csrf->field(),
            self::hidden('persona', $persona->key->value),
            self::hidden('return', $return),
            Html::el('button')->attr('type', 'submit')->class('rvt-button')->text('Sign in as ' . $persona->name),
        );

        return Html::el('div')->class('rvt-card', 'rvt-card--raised')->children(
            Html::el('div')->class('rvt-card__body')->children(
                Html::el('h2')->class('rvt-card__title')->text($persona->name),
                Html::el('div')->class('rvt-card__content')->html(implode(' ', $badges) . $details->render() . $form->render()),
            ),
        )->render();
    }

    private function roles(Persona $persona): string
    {
        $grants = $this->roster->grantsFor($persona);

        if ($grants === []) {
            return Html::el('span')->text('No permissions')->render();
        }

        $list = Html::el('ul')->class('rvt-list-plain');

        foreach ($grants as $grant) {
            $list->children(Html::el('li')->text(self::describe($grant)));
        }

        return $list->render();
    }

    private static function describe(RoleGrant $grant): string
    {
        if ($grant->permissions === []) {
            return $grant->role . ': no permissions';
        }

        return $grant->role . ': ' . implode(', ', array_map(
            static fn (Permission $permission): string => (string) $permission->value,
            $grant->permissions,
        ));
    }

    private function guestEntry(): string
    {
        return Html::el('form')->attr('method', 'post')->attr('action', self::SIGN_OUT_PATH)->class('rvt-m-bottom-lg')->children(
            $this->csrf->field(),
            Html::el('button')->attr('type', 'submit')->class('rvt-button', 'rvt-button--secondary')->text('Continue as a guest'),
        )->render();
    }

    private function outageControl(string $return, bool $outageOn): string
    {
        return Html::el('section')->children(
            Html::el('h2')->class('rvt-ts-20')->text('Grouper'),
            Html::el('p')->text($outageOn
                ? 'Grouper outage simulated: once cached membership expires, every persona\'s groups are unavailable.'
                : 'Grouper answers with each persona\'s groups from config/personas.php.'),
            Html::el('form')->attr('method', 'post')->attr('action', self::OUTAGE_PATH)->children(
                $this->csrf->field(),
                self::hidden('outage', $outageOn ? 'off' : 'on'),
                self::hidden('return', $return),
                Html::el('button')->attr('type', 'submit')->class('rvt-button', 'rvt-button--secondary')
                    ->text($outageOn ? 'End the Grouper outage' : 'Simulate a Grouper outage'),
            ),
        )->render();
    }

    private static function alert(RenderContext $context, string $message, AlertStyle $style): string
    {
        return new Alert(title: $message, style: $style, dismissible: false)->render($context);
    }

    private static function hidden(string $name, string $value): Html
    {
        return Html::el('input')->attr('type', 'hidden')->attr('name', $name)->attr('value', $value);
    }
}
