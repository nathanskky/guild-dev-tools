<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Testing;

use Guild\DevTools\Testing\TestDatabase;
use Guild\DevTools\Testing\VisitsApplication;
use Guild\Framework\Model\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A test case that prepares data in setUp(), before any visit: the rows go
 * to the test kit's database and are rolled back after each test. Two tests
 * write the same role, so a row that leaked would show up as two.
 */
#[CoversClass(TestDatabase::class)]
final class RowsWrittenInSetUpTest extends TestCase
{
    use VisitsApplication;

    protected function setUp(): void
    {
        $role = new Role();
        $role->name = 'Reviewer';
        $role->active = true;
        $role->created_by = 'jdoe';
        $role->save();
    }

    public function testARowWrittenInSetUpIsInTheKitsDatabase(): void
    {
        self::assertSame(1, self::reviewersInTheKitsDatabase(), 'setUp() wrote to the database visits read');
    }

    public function testTheOtherTestsRowWasRolledBack(): void
    {
        self::assertSame(1, self::reviewersInTheKitsDatabase(), 'only this test\'s row is there');
    }

    private static function reviewersInTheKitsDatabase(): int
    {
        return TestDatabase::capsule()->getConnection()->table('framework_roles')->where('name', 'Reviewer')->count();
    }
}
