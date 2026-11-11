<?php

declare(strict_types=1);

use Guild\DevTools\Persona\DirectoryStatus;
use Guild\DevTools\Persona\Persona;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\DevTools\Test\Support\FixturePermission;

$editors = new PersonaGroup('iu:roles:sys:acm:starter-editors', 'Starter Editors');
$admins = new PersonaGroup('iu:roles:sys:acm:starter-admins', 'Starter Admins');

return new PersonaRoster(
    personas: [
        new Persona('editor', username: 'persona-editor', name: 'Erin Editor', groups: [$editors]),
        new Persona('newcomer', username: 'persona-newcomer', name: 'Nico Newcomer'),
        new Persona('admin', username: 'persona-admin', name: 'Ada Admin', groups: [$admins]),
        new Persona('outage', username: 'persona-outage', name: 'Grouper Down', directory: DirectoryStatus::Unavailable),
    ],
    grants: [
        new RoleGrant('Editor', groups: [$editors], permissions: [FixturePermission::DocumentsView]),
    ],
    systemAdminGroup: $admins,
);
