<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\SignIn;

use Guild\DevTools\SignIn\LocalHost;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LocalHost::class)]
final class LocalHostTest extends TestCase
{
    #[DataProvider('hosts')]
    public function testOnlyThisMachineIsLocal(string $uri, bool $local): void
    {
        self::assertSame($local, LocalHost::is(new ServerRequest(uri: $uri)), $uri);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function hosts(): iterable
    {
        yield 'localhost with the docker port' => ['https://localhost:8443/', true];
        yield 'localhost in capitals' => ['http://LOCALHOST/', true];
        yield 'IPv4 loopback' => ['http://127.0.0.1/', true];
        yield 'IPv6 loopback' => ['http://[::1]:8443/', true];
        yield 'a host that merely starts with localhost' => ['http://localhost.evil.example/', false];
        yield 'a real host' => ['https://example.org/', false];
        yield 'no host at all' => ['/documents', false];
    }
}
