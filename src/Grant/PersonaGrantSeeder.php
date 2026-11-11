<?php

declare(strict_types=1);

namespace Guild\DevTools\Grant;

use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\DevTools\Persona\RoleGrant;
use Guild\Framework\Model\Group;
use Guild\Framework\Model\Role;
use Guild\Framework\Model\RolePermission;

/**
 * Writes the roster's role grants into the framework's grant tables, through
 * the same Eloquent models the administration pages use.
 *
 * It inserts or reactivates, by group identifier, role name and permission,
 * and never deletes or detaches, so grants a developer adds through
 * /framework/authorization survive. A group the developer renamed keeps its
 * name. Runs when the persona picker loads and before each test visit, never
 * on an ordinary request.
 */
final readonly class PersonaGrantSeeder
{
    public const string ACTOR = 'persona-roster';

    public function __construct(private PersonaRoster $roster)
    {
    }

    public function seed(): void
    {
        // An application with authorization off has no grants, and may have no database.
        if ($this->roster->grants === []) {
            return;
        }

        new Group()->getConnection()->transaction(function (): void {
            foreach ($this->roster->grants as $grant) {
                $this->seedGrant($grant);
            }
        });
    }

    private function seedGrant(RoleGrant $grant): void
    {
        $role = Role::query()->where('name', $grant->role)->first() ?? new Role();

        if (! $role->exists) {
            $role->name = $grant->role;
            $role->created_by = self::ACTOR;
        }

        $this->activate($role);

        foreach ($grant->groups as $personaGroup) {
            $group = $this->group($personaGroup);

            if (! $group->roles()->whereKey($role->id)->exists()) {
                $group->roles()->attach($role->id, ['created_by' => self::ACTOR]);
            }
        }

        foreach ($grant->permissions as $permission) {
            $name = (string) $permission->value;
            $row = RolePermission::query()->where('role_id', $role->id)->where('name', $name)->first() ?? new RolePermission();

            if (! $row->exists) {
                $row->role_id = $role->id;
                $row->name = $name;
                $row->created_by = self::ACTOR;
            }

            $this->activate($row);
        }
    }

    private function group(PersonaGroup $personaGroup): Group
    {
        $group = Group::query()->where('group_identifier', $personaGroup->identifier)->first() ?? new Group();

        if (! $group->exists) {
            // Group names are unique; a developer's group may already use this label.
            $labelTaken = Group::query()->where('name', $personaGroup->label)->exists();
            $group->name = $labelTaken ? $personaGroup->identifier : $personaGroup->label;
            $group->group_identifier = $personaGroup->identifier;
            $group->created_by = self::ACTOR;
        }

        $this->activate($group);

        return $group;
    }

    /**
     * Save a new row active, or reactivate an existing one; an active existing
     * row is left untouched.
     */
    private function activate(Group|Role|RolePermission $model): void
    {
        if ($model->exists && $model->active) {
            return;
        }

        if ($model->exists) {
            $model->updated_by = self::ACTOR;
        }

        $model->active = true;
        $model->save();
    }
}
