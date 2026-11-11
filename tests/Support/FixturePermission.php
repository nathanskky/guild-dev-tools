<?php

declare(strict_types=1);

namespace Guild\DevTools\Test\Support;

use Guild\Framework\Authorization\Permission;

/**
 * The permission enum of the test applications in this repository.
 */
enum FixturePermission: string implements Permission
{
    case DocumentsView = 'documents.view';
    case DocumentsEdit = 'documents.edit';
}
