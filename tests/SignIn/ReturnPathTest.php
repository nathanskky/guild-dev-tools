<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\SignIn;

use Guild\DevTools\SignIn\ReturnPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnPath::class)]
final class ReturnPathTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function pathsOnThisSite(): iterable
    {
        yield 'a page' => ['/documents'];
        yield 'a page with a query' => ['/documents?page=2'];
        yield 'the root' => ['/'];
    }

    #[DataProvider('pathsOnThisSite')]
    public function testAPathOnThisSiteIsKept(string $path): void
    {
        self::assertSame($path, ReturnPath::from($path), sprintf('%s is a page of this application', $path));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function targetsThatReturnToTheRoot(): iterable
    {
        yield 'none' => [null];
        yield 'empty' => [''];
        yield 'a relative path' => ['documents'];
        yield 'protocol-relative' => ['//evil.example'];
        yield 'backslash' => ['/\\evil.example'];
        yield 'absolute URL' => ['https://evil.example/'];
        yield 'the picker' => ['/_dev/personas'];
        yield 'the picker with a query' => ['/_dev/personas?return=%2F'];
        yield 'under the picker' => ['/_dev/personas/outage'];
        yield 'a tab browsers strip before parsing' => ["/\t/evil.example"];
        yield 'a tab before a backslash' => ["/\t\\evil.example"];
        yield 'a newline' => ["/\n/evil.example"];
        yield 'a carriage return' => ["/documents\r\nX-Injected: 1"];
        yield 'a space' => ['/ /evil.example'];
        yield 'a NUL byte' => ["/documents\0"];
    }

    #[DataProvider('targetsThatReturnToTheRoot')]
    public function testAnythingElseReturnsToTheRoot(?string $target): void
    {
        self::assertSame('/', ReturnPath::from($target), sprintf('%s is off this site, or the picker itself', var_export($target, true)));
    }
}
