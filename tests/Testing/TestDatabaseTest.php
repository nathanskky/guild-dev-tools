<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Testing;

use Guild\DevTools\Testing\TestDatabase;
use Guild\Framework\Model\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestDatabase::class)]
final class TestDatabaseTest extends TestCase
{
    public function testTheSchemaIsMigratedOnceAndShared(): void
    {
        $capsule = TestDatabase::capsule();

        self::assertSame($capsule, TestDatabase::capsule(), 'one database for the whole PHPUnit process');
        self::assertTrue($capsule->getConnection()->getSchemaBuilder()->hasTable('framework_groups'), 'built by the framework\'s migrations');
    }

    public function testRollBackUndoesEverythingSinceBegin(): void
    {
        TestDatabase::activate();
        TestDatabase::begin();
        $group = new Group();
        $group->name = 'Starter Editors';
        $group->group_identifier = 'iu:roles:sys:acm:starter-editors';
        $group->active = true;
        $group->created_by = 'jdoe';
        $group->save();

        TestDatabase::rollBack();

        self::assertSame(0, Group::query()->count(), 'a test\'s writes do not reach the next test');
    }

    public function testRollBackClosesTransactionsLeftOpen(): void
    {
        $connection = TestDatabase::capsule()->getConnection();
        TestDatabase::begin();
        $connection->beginTransaction();
        $connection->beginTransaction();

        TestDatabase::rollBack();

        self::assertSame(0, $connection->transactionLevel(), 'a test that left transactions open still leaves a clean database');
    }
}
