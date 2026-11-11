<?php

declare(strict_types=1);

namespace Guild\DevTools\Testing;

use Guild\Framework\Testing\FrameworkSchema;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Events\Dispatcher;

/**
 * The test kit's database: one in-memory SQLite connection per PHPUnit
 * process, built once by the framework's real migrations. Each test runs
 * inside a transaction that is rolled back after it.
 *
 * @internal
 */
final class TestDatabase
{
    private static ?Capsule $capsule = null;

    private function __construct()
    {
    }

    /**
     * The process's database, created and migrated on first use.
     */
    public static function capsule(): Capsule
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        FrameworkSchema::migrate(self::connectionOf($capsule));

        return self::$capsule = $capsule;
    }

    /**
     * Make Eloquent models use this database, as addIlluminateDatabase()
     * does for the application's own.
     */
    public static function activate(): Capsule
    {
        $capsule = self::capsule();
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return $capsule;
    }

    public static function begin(): void
    {
        self::connectionOf(self::capsule())->beginTransaction();
    }

    /**
     * Roll back to before begin(), including any transaction the test left open.
     */
    public static function rollBack(): void
    {
        $connection = self::connectionOf(self::capsule());

        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }

    private static function connectionOf(Capsule $capsule): Connection
    {
        return $capsule->getConnection();
    }
}
