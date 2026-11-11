<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Picker;

use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaKey;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\DevTools\Picker\PersonaPickerPage;
use Guild\DevTools\Test\Support\FixturePermission;
use Guild\DevTools\Test\Support\FixtureRoster;
use Guild\Framework\Admin\Csrf;
use Guild\Framework\Testing\ArraySessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonaPickerPage::class)]
#[UsesClass(Persona::class)]
#[UsesClass(PersonaGroup::class)]
#[UsesClass(PersonaKey::class)]
#[UsesClass(PersonaRoster::class)]
#[UsesClass(RoleGrant::class)]
#[UsesClass(ArraySessionStore::class)]
#[UsesClass(Csrf::class)]
final class PersonaPickerPageTest extends TestCase
{
    private Csrf $csrf;

    protected function setUp(): void
    {
        $this->csrf = new Csrf(new ArraySessionStore());
    }

    public function testEveryPersonaIsListedWithWhatItMayDo(): void
    {
        $body = $this->page()->render(null, '/', false);

        foreach ([
            FixtureRoster::EDITOR_NAME,
            FixtureRoster::EDITOR_USERNAME,
            FixtureRoster::EDITORS_LABEL,
            sprintf('%s: %s, %s', FixtureRoster::EDITOR_ROLE, FixturePermission::DocumentsView->value, FixturePermission::DocumentsEdit->value),
            'Sign in as ' . FixtureRoster::EDITOR_NAME,
            FixtureRoster::NEWCOMER_NAME,
            'No groups',
            'No permissions',
            FixtureRoster::ADMIN_NAME,
            'System Admin',
            FixtureRoster::OUTAGE_NAME,
            'Grouper unavailable',
        ] as $text) {
            self::assertStringContainsString($text, $body, sprintf('the picker says "%s"', $text));
        }
    }

    public function testEachPersonaSaysWhatSetsItApart(): void
    {
        $body = $this->page()->render(null, '/', false);

        self::assertStringContainsString('System Admin', self::entry($body, FixtureRoster::ADMIN_NAME, FixtureRoster::OUTAGE_NAME), 'the admin is marked System Admin');
        self::assertStringNotContainsString('System Admin', self::entry($body, FixtureRoster::EDITOR_NAME, FixtureRoster::NEWCOMER_NAME), 'the editor is not');
        self::assertStringContainsString('No groups', self::entry($body, FixtureRoster::NEWCOMER_NAME, FixtureRoster::ADMIN_NAME), 'the newcomer is in no group');
        self::assertStringNotContainsString('No groups', self::entry($body, FixtureRoster::EDITOR_NAME, FixtureRoster::NEWCOMER_NAME), 'the editor is in one');
    }

    public function testTheCurrentPersonaIsMarked(): void
    {
        $body = $this->page()->render($this->persona(FixtureRoster::EDITOR), '/', false);

        self::assertSame(1, substr_count($body, 'Signed in'), 'one persona is signed in');
        self::assertStringContainsString('Signed in', self::entry($body, FixtureRoster::EDITOR_NAME, FixtureRoster::NEWCOMER_NAME), 'and it is the editor');
        self::assertStringNotContainsString('Signed in', $this->page()->render(null, '/', false), 'a guest has no persona marked');
    }

    public function testEachFormCarriesTheTokenTheReturnAndThePersona(): void
    {
        $body = $this->page()->render(null, '/documents', false);

        self::assertStringContainsString('action="/_dev/personas"', $body, 'the persona forms post to the picker');
        self::assertStringContainsString(sprintf('name="_csrf" value="%s"', $this->csrf->token()), $body, 'with the session\'s token');
        self::assertStringContainsString(sprintf('name="persona" value="%s"', FixtureRoster::EDITOR), $body, 'naming the persona by key');
        self::assertStringContainsString('name="return" value="/documents"', $body, 'and where to go next');
    }

    public function testTheGuestEntrySignsOut(): void
    {
        $body = $this->page()->render(null, '/', false);

        self::assertStringContainsString('action="/framework/sign-out"', $body, 'a guest is a signed-out visitor');
        self::assertStringContainsString('Continue as a guest', $body, 'offered by name');
    }

    public function testTheReturnPathIsEscaped(): void
    {
        $body = $this->page()->render(null, '/documents?a="><script>x</script>', false);

        self::assertStringNotContainsString('"><script>', $body, 'a return path cannot close the attribute');
        self::assertStringContainsString('&quot;&gt;&lt;script&gt;', $body, 'it is kept, escaped');
    }

    public function testAPersonaNameIsEscaped(): void
    {
        $roster = new PersonaRoster([new Persona('eve', username: 'persona-eve', name: 'Eve <b>Bold</b>')]);

        $body = new PersonaPickerPage($roster, $this->csrf)->render(null, '/', false);

        self::assertStringNotContainsString('<b>Bold</b>', $body, 'a name is text, not markup');
        self::assertStringContainsString('Eve &lt;b&gt;Bold&lt;/b&gt;', $body, 'shown as written');
    }

    public function testTheOutageControlFollowsTheState(): void
    {
        $off = $this->page()->render(null, '/', false);
        $on = $this->page()->render(null, '/', true);

        self::assertStringContainsString('Simulate a Grouper outage', $off, 'with Grouper answering, the outage can be started');
        self::assertStringNotContainsString('Grouper outage simulated', $off, 'and none is reported');
        self::assertStringContainsString('End the Grouper outage', $on, 'during one, it can be ended');
        self::assertStringContainsString('Grouper outage simulated', $on, 'and it is reported');
        self::assertStringContainsString('action="/_dev/personas/outage"', $on, 'the control posts to the outage toggle');
    }

    public function testANoticeAndAnErrorAreShown(): void
    {
        $body = $this->page()->render(null, '/', false, notice: 'You were signed out.', error: 'No such persona.');

        self::assertStringContainsString('You were signed out.', $body, 'a notice is shown');
        self::assertStringContainsString('No such persona.', $body, 'and an error');
    }

    public function testItIsACompletePage(): void
    {
        $body = $this->page()->render(null, '/', false);

        self::assertMatchesRegularExpression('/<body[\s>]/', $body, 'a whole document, so the banner has a <body> to follow');
        self::assertStringContainsString('<title>Sign in as a persona · Persona sign-in</title>', $body, 'titled for what it is');
    }

    private function page(): PersonaPickerPage
    {
        return new PersonaPickerPage(self::roster(), $this->csrf);
    }

    private function persona(string $key): Persona
    {
        $persona = self::roster()->find(new PersonaKey($key));
        self::assertNotNull($persona);

        return $persona;
    }

    private static function roster(): PersonaRoster
    {
        $roster = require dirname(__DIR__) . '/Fixture/app/config/personas.php';
        self::assertInstanceOf(PersonaRoster::class, $roster);

        return $roster;
    }

    /**
     * The part of $body from $name's entry up to $nextName's.
     */
    private static function entry(string $body, string $name, string $nextName): string
    {
        $start = strpos($body, $name);
        $end = strpos($body, $nextName, (int) $start);
        self::assertNotFalse($start, sprintf('%s is listed', $name));
        self::assertNotFalse($end, sprintf('%s is listed after %s', $nextName, $name));

        return substr($body, $start, $end - $start);
    }
}
