<?php

declare(strict_types=1);

use Guild\DevTools\Persona\DirectoryStatus;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\DevTools\Test\Support\FixturePermission;
use Guild\DevTools\Test\Support\FixtureRoster as R;

$editors = new PersonaGroup(R::EDITORS_GROUP, R::EDITORS_LABEL);
$admins = new PersonaGroup(R::ADMINS_GROUP, R::ADMINS_LABEL);

return new PersonaRoster(
    personas: [
        new Persona(R::EDITOR, username: R::EDITOR_USERNAME, name: R::EDITOR_NAME, groups: [$editors]),
        new Persona(R::NEWCOMER, username: R::NEWCOMER_USERNAME, name: R::NEWCOMER_NAME),
        new Persona(R::ADMIN, username: R::ADMIN_USERNAME, name: R::ADMIN_NAME, groups: [$admins]),
        new Persona(R::OUTAGE, username: R::OUTAGE_USERNAME, name: R::OUTAGE_NAME, directory: DirectoryStatus::Unavailable),
    ],
    grants: [
        new RoleGrant(R::EDITOR_ROLE, groups: [$editors], permissions: [FixturePermission::DocumentsView, FixturePermission::DocumentsEdit]),
    ],
    systemAdminGroup: $admins,
);
