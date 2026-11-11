<?php

declare(strict_types=1);

namespace Guild\DevTools\Testing;

use Guild\DevTools\SignIn\PersonaSignIn;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * What the application answered a visit's request, with assertions that
 * name the actual status, redirect target and the start of the body when
 * they fail.
 */
final readonly class VisitResponse
{
    private const int BODY_EXCERPT = 500;

    public function __construct(public ResponseInterface $response)
    {
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function header(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    public function assertOk(): static
    {
        return $this->assertStatus(200);
    }

    public function assertForbidden(): static
    {
        return $this->assertStatus(403);
    }

    public function assertNotFound(): static
    {
        return $this->assertStatus(404);
    }

    public function assertServiceUnavailable(): static
    {
        return $this->assertStatus(503);
    }

    /**
     * A redirect whose Location is exactly $to.
     */
    public function assertRedirect(string $to): static
    {
        $this->assertIsRedirect("a redirect to {$to}");
        Assert::assertSame($to, $this->header('Location'), "Expected a redirect to {$to}. " . $this->context());

        return $this;
    }

    /**
     * A redirect to the persona picker, wherever it would return to.
     */
    public function assertRedirectsToSignIn(): static
    {
        $this->assertIsRedirect('a redirect to sign in');
        Assert::assertSame(
            PersonaSignIn::PICKER_PATH,
            parse_url($this->header('Location'), PHP_URL_PATH),
            'Expected a redirect to sign in (' . PersonaSignIn::PICKER_PATH . '). ' . $this->context(),
        );

        return $this;
    }

    /**
     * $text appears in the body as a reader sees it: HTML entities are
     * decoded first, so any template engine's escaping matches.
     */
    public function assertSee(string $text): static
    {
        Assert::assertStringContainsString($text, $this->readableBody(), "Expected to see '{$text}'. " . $this->context());

        return $this;
    }

    public function assertDontSee(string $text): static
    {
        Assert::assertStringNotContainsString($text, $this->readableBody(), "Expected not to see '{$text}'. " . $this->context());

        return $this;
    }

    private function assertStatus(int $expected): static
    {
        Assert::assertSame($expected, $this->status(), "Expected status {$expected}. " . $this->context());

        return $this;
    }

    private function assertIsRedirect(string $expected): void
    {
        $status = $this->status();
        Assert::assertTrue($status >= 300 && $status < 400, "Expected {$expected}. " . $this->context());
    }

    private function readableBody(): string
    {
        return html_entity_decode($this->body(), ENT_QUOTES | ENT_HTML5);
    }

    /**
     * The actual status, Location and the start of the body, for failure messages.
     */
    private function context(): string
    {
        $context = 'Got status ' . $this->status();
        $location = $this->header('Location');

        if ($location !== '') {
            $context .= ', Location: ' . $location;
        }

        return $context . ', body: ' . mb_substr($this->body(), 0, self::BODY_EXCERPT);
    }
}
