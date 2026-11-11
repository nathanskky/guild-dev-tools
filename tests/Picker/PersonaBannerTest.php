<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Picker;

use Guild\DevTools\Picker\PersonaBanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonaBanner::class)]
final class PersonaBannerTest extends TestCase
{
    private const string BANNER = '<div id="banner"></div>';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function pagesWithABody(): iterable
    {
        yield 'a bare tag' => ['<html><body><p>x</p></body></html>', '<html><body>' . self::BANNER . '<p>x</p></body></html>'];
        yield 'upper case with attributes' => ['<BODY class="x"><p>x</p>', '<BODY class="x">' . self::BANNER . '<p>x</p>'];
        yield 'attributes across lines' => ["<body\n  data-x=\"1\"><p>x</p>", "<body\n  data-x=\"1\">" . self::BANNER . '<p>x</p>'];
        yield 'two body tags' => ['<body>a<body>b', '<body>' . self::BANNER . 'a<body>b'];
    }

    #[DataProvider('pagesWithABody')]
    public function testTheBannerFollowsTheFirstBodyTag(string $html, string $expected): void
    {
        self::assertSame($expected, PersonaBanner::insertAfterBodyTag($html, self::BANNER), 'the banner opens the page');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function htmlWithoutABody(): iterable
    {
        yield 'a fragment' => ['<p>no body</p>'];
        yield 'a tag that only starts like body' => ['<bodyguard>x</bodyguard>'];
    }

    #[DataProvider('htmlWithoutABody')]
    public function testWithoutABodyTagThereIsNowhereToPutIt(string $html): void
    {
        self::assertNull(PersonaBanner::insertAfterBodyTag($html, self::BANNER), 'a fragment is left as it is');
    }
}
