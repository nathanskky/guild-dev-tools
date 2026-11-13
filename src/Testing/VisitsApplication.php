<?php

declare(strict_types=1);

namespace Guild\DevTools\Testing;

use Composer\InstalledVersions;
use Guild\DevTools\Persona\Persona;
use Guild\Framework\Exception\ConfigurationException;
use NoDiscard;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * For feature tests: visit the application as a persona or a guest, through
 * the real router, Gate and grants.
 *
 *     $this->signedInAs('editor')->get('/')->assertOk();
 *
 * Each request loads the application from config/app.php, then replaces:
 *
 * - the session, with the visit's own, in memory
 * - the database, with in-memory SQLite migrated once per PHPUnit process
 *   with the framework's tables only; the application's own tables are absent
 * - the logger, with one that discards everything
 * - the request, with the one being sent
 *
 * The roster's grants are seeded on a test's first request only, so a grant
 * the test revokes stays revoked. Each test runs in a database transaction,
 * opened before setUp() and rolled back after the test.
 */
trait VisitsApplication
{
    /**
     * A visitor signed in as $persona. A key not in the roster fails on the
     * visit's first request.
     *
     * @param  string|Persona  $persona  A key from config/personas.php, or a Persona that joins the roster for
     *                                   this visit only.
     *
     * @throws ConfigurationException When $persona is a string that breaks the persona key format.
     */
    #[NoDiscard]
    protected function signedInAs(string|Persona $persona): Visit
    {
        return Visit::signedInAs($this->applicationBasePath(), $persona);
    }

    /**
     * A visitor who hasn't signed in.
     */
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
     * Before setUp(), so rows a test writes there go to the test database
     * and are rolled back too.
     */
    #[Before(priority: 1)]
    protected function beginVisitTransaction(): void
    {
        TestDatabase::activate();
        TestDatabase::begin();
    }

    #[After]
    protected function rollBackVisitTransaction(): void
    {
        TestDatabase::rollBack();
    }
}
