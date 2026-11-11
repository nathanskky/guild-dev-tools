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
     * @param  list<PersonaGroup>  $groups
     * @param  list<Permission>  $permissions  Cases of the application's permission enum.
     */
    public function __construct(string $role, public array $groups = [], public array $permissions = [])
    {
        $this->role = trim($role);

        if ($this->role === '') {
            throw new ConfigurationException("A role grant has a blank role name. Name the role, e.g. 'Editor'.");
        }
    }
}
