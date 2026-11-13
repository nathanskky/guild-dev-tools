<?php

declare(strict_types=1);

namespace Guild\DevTools\Persona;

use Guild\Framework\Authorization\Permission;
use Guild\Framework\Exception\ConfigurationException;

/**
 * A role, the groups mapped to it, and the permissions it holds: the grant
 * rows PersonaGrantSeeder writes, as an administrator would through
 * /framework/authorization.
 */
final readonly class RoleGrant
{
    public string $role;

    /**
     * @param  string  $role  The role name. Trimmed; must not be blank.
     * @param  list<PersonaGroup>  $groups  The groups mapped to the role.
     * @param  list<Permission>  $permissions  Cases of the application's permission enum.
     *
     * @throws ConfigurationException When the role name is blank.
     */
    public function __construct(string $role, public array $groups = [], public array $permissions = [])
    {
        $this->role = trim($role);

        if ($this->role === '') {
            throw new ConfigurationException("A role grant has a blank role name. Name the role, e.g. 'Editor'.");
        }
    }
}
