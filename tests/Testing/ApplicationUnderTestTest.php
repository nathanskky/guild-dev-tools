<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Testing;

use Guild\DevTools\Testing\ApplicationUnderTest;
use Guild\Framework\Exception\ConfigurationException;
use Guild\Framework\Testing\ArraySessionStore;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApplicationUnderTest::class)]
#[UsesClass(ArraySessionStore::class)]
final class ApplicationUnderTestTest extends TestCase
{
    public function testAMissingAppConfigurationIsReported(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessageMatches('/No config\/app\.php under .*applicationBasePath\(\)/');

        self::boot(dirname(__DIR__) . '/Fixture');
    }

    public function testAConfigurationThatIsNotAnApplicationIsReported(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('config/app.php must return the Application that ->create() builds.');

        self::boot(dirname(__DIR__) . '/Fixture/not-an-app');
    }

    private static function boot(string $basePath): void
    {
        ApplicationUnderTest::boot($basePath, new ArraySessionStore(), new ServerRequest(uri: 'http://localhost/'), null);
    }
}
