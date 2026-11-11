<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Picker;

use Guild\DevTools\Picker\PersonaPickerController;
use Guild\DevTools\Picker\RequireLocalHost;
use Guild\DevTools\Test\Support\FixtureRoster;
use Guild\DevTools\Testing\VisitsApplication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The picker's routes on the fixture application, through the test kit.
 * The kit, the extension and the framework run for real; their own tests
 * cover them.
 */
#[CoversClass(PersonaPickerController::class)]
#[CoversClass(RequireLocalHost::class)]
final class PersonaPickerRoutesTest extends TestCase
{
    use VisitsApplication;

    protected function applicationBasePath(): string
    {
        return dirname(__DIR__) . '/Fixture/app';
    }

    public function testThePickerAnswersOnALocalHost(): void
    {
        $response = $this->asGuest()->get('/_dev/personas')->assertOk()->assertSee('Sign in as ' . FixtureRoster::EDITOR_NAME);

        self::assertStringStartsWith('text/html', $response->header('Content-Type'), 'a page for a browser');
    }

    public function testThePickerIsNotFoundOnAnotherHost(): void
    {
        $this->asGuest()->get('http://example.edu/_dev/personas')->assertNotFound()->assertDontSee(FixtureRoster::EDITOR_NAME);
        $this->asGuest()->get('/_dev/personas')->assertSee(FixtureRoster::EDITOR_NAME);
    }
}
