<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Support;

/**
 * The names in the fixture application's config/personas.php, so the roster
 * and the tests that visit it name each persona, group and role once.
 * Renaming one is a single edit, and a test that missed it fails to find it.
 */
final class FixtureRoster
{
    public const string EDITOR = 'editor';

    public const string EDITOR_NAME = 'Erin Editor';

    public const string EDITOR_USERNAME = 'persona-editor';

    public const string NEWCOMER = 'newcomer';

    public const string NEWCOMER_NAME = 'Nico Newcomer';

    public const string NEWCOMER_USERNAME = 'persona-newcomer';

    public const string ADMIN = 'admin';

    public const string ADMIN_NAME = 'Ada Admin';

    public const string ADMIN_USERNAME = 'persona-admin';

    public const string OUTAGE = 'outage';

    public const string OUTAGE_NAME = 'Grouper Down';

    public const string OUTAGE_USERNAME = 'persona-outage';

    public const string EDITORS_GROUP = 'iu:roles:sys:acm:starter-editors';

    public const string EDITORS_LABEL = 'Starter Editors';

    public const string ADMINS_GROUP = 'iu:roles:sys:acm:starter-admins';

    public const string ADMINS_LABEL = 'Starter Admins';

    public const string EDITOR_ROLE = 'Editor';

    private function __construct()
    {
    }
}
