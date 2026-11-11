<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Testing;

use Closure;
use Guild\DevTools\Testing\VisitResponse;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VisitResponse::class)]
final class VisitResponseTest extends TestCase
{
    public function testStatusAssertionsPassOnTheirStatusAndReturnTheResponse(): void
    {
        $ok = new VisitResponse(new HtmlResponse('page'));

        self::assertSame($ok, $ok->assertOk(), 'assertions chain on the same response');
        new VisitResponse(new EmptyResponse(403))->assertForbidden();
        new VisitResponse(new EmptyResponse(404))->assertNotFound();
        new VisitResponse(new EmptyResponse(503))->assertServiceUnavailable();
    }

    public function testAFailedStatusAssertionGivesTheStatusLocationAndBody(): void
    {
        $redirect = self::failureOf(static fn () => new VisitResponse(new RedirectResponse('/_dev/personas?return=%2F'))->assertOk());
        self::assertStringContainsString('302', $redirect, 'the actual status is named');
        self::assertStringContainsString('/_dev/personas?return=%2F', $redirect, 'and where it was going');

        $error = self::failureOf(static fn () => new VisitResponse(new HtmlResponse(str_repeat('x', 2000) . 'TAIL', 500))->assertOk());
        self::assertStringContainsString('500', $error, 'the actual status is named');
        self::assertStringContainsString('xxxx', $error, 'with the start of the body');
        self::assertStringNotContainsString('TAIL', $error, 'cut short, so a whole page does not flood the output');
    }

    public function testRedirectsToSignInMeansARedirectToThePicker(): void
    {
        new VisitResponse(new RedirectResponse('/_dev/personas?return=%2Fdocuments'))->assertRedirectsToSignIn();
        new VisitResponse(new RedirectResponse('/_dev/personas?return=%2Fdocuments', 303))->assertRedirectsToSignIn();

        self::failureOf(static fn () => new VisitResponse(new RedirectResponse('/elsewhere'))->assertRedirectsToSignIn());
        self::failureOf(static fn () => new VisitResponse(new HtmlResponse('page'))->assertRedirectsToSignIn());
    }

    public function testAssertRedirectComparesTheLocationExactly(): void
    {
        new VisitResponse(new RedirectResponse('/a'))->assertRedirect('/a');

        self::failureOf(static fn () => new VisitResponse(new RedirectResponse('/a?b'))->assertRedirect('/a'));
        self::failureOf(static fn () => new VisitResponse(new HtmlResponse('page'))->assertRedirect('/a'));
    }

    public function testSeeMatchesTextAsAReaderSeesIt(): void
    {
        $response = new VisitResponse(new HtmlResponse('<p>Erin&#039;s &amp; Val&apos;s</p>'));

        $response->assertSee("Erin's & Val's")->assertDontSee('Ada');

        self::failureOf(static fn () => $response->assertSee('Ada'));
        self::failureOf(static fn () => $response->assertDontSee('Erin'));
    }

    /**
     * Run $assertion, expecting it to fail, and return its message.
     */
    private static function failureOf(Closure $assertion): string
    {
        try {
            $assertion();
        } catch (AssertionFailedError $failure) {
            return $failure->getMessage();
        }

        self::fail('the assertion was expected to fail');
    }
}
