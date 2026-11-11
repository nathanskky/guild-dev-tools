<?php

declare(strict_types=1);

namespace Guild\DevTools\Testing;

use Composer\InstalledVersions;
use Guild\DevTools\Persona\Persona;
use NoDiscard;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * For feature tests: visit the application as a persona or a guest, through
 * the real router, Gate and grants.
 *
 *     $this->signedInAs('editor')->get('/')->assertOk();
 *
 * Each test runs in a database transaction that is rolled back after it.
 */
trait VisitsApplication
{
    /**
     * @param  string|Persona  $persona  A key from config/personas.php, or a Persona for this test only.
     */
    #[NoDiscard]
    protected function signedInAs(string|Persona $persona): Visit
    {
        return Visit::signedInAs($this->applicationBasePath(), $persona);
    }

    #[NoDiscard]
    protected function asGuest(): Visit
    {
        return Visit::asGuest($this->applicationBasePath());
    }

    /**
     * The directory holding config/app.php: the Composer root package by
     * default. Override it to visit another application.
     */
    protected function applicationBasePath(): string
    {
        $root = InstalledVersions::getRootPackage()['install_path'];

        return realpath($root) ?: $root;
    }

    /**
     * Before setUp(), so rows a test writes there are rolled back too.
     */
    #[Before(priority: 1)]
    protected function beginVisitTransaction(): void
    {
        TestDatabase::begin();
    }

    #[After]
    protected function rollBackVisitTransaction(): void
    {
        TestDatabase::rollBack();
    }
}
