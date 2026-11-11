<?php

declare(strict_types=1);

namespace Guild\DevTools\Persona;

use Guild\Framework\Exception\ConfigurationException;

/**
 * The application's personas, the role grants they rely on, and its System
 * Admin group: what config/personas.php returns. One roster serves local
 * development and tests.
 *
 * Every rule is checked here, so a roster that would behave surprisingly
 * fails at boot with a message naming the persona or group and the fix.
 */
final readonly class PersonaRoster
{
    /**
     * @param  list<Persona>  $personas
     * @param  list<RoleGrant>  $grants
     * @param  PersonaGroup|null  $systemAdminGroup  Its label becomes the System Admin label in persona mode.
     * @param  string  $usernamePrefix  Every persona username starts with it, so none is a real IU username.
     *                                  '' turns the check off.
     */
    public function __construct(
        public array $personas,
        public array $grants = [],
        public ?PersonaGroup $systemAdminGroup = null,
        public string $usernamePrefix = 'persona-',
    ) {
        $this->checkPersonas();
        $this->checkGroups();
        $this->checkGrants();
    }

    public function find(PersonaKey $key): ?Persona
    {
        return array_find($this->personas, static fn (Persona $persona): bool => $persona->key->equals($key));
    }

    public function findByUsername(string $username): ?Persona
    {
        return array_find($this->personas, static fn (Persona $persona): bool => $persona->username === $username);
    }

    /**
     * The grants that name at least one of $persona's groups, in roster order:
     * what the persona may do once signed in.
     *
     * @return list<RoleGrant>
     */
    public function grantsFor(Persona $persona): array
    {
        $identifiers = array_map(static fn (PersonaGroup $group): string => $group->identifier, $persona->groups);

        return array_values(array_filter(
            $this->grants,
            static fn (RoleGrant $grant): bool => array_any(
                $grant->groups,
                static fn (PersonaGroup $group): bool => in_array($group->identifier, $identifiers, true),
            ),
        ));
    }

    /**
     * Whether $persona is in the roster's System Admin group. False when the
     * roster names none.
     */
    public function isSystemAdmin(Persona $persona): bool
    {
        $adminGroup = $this->systemAdminGroup;

        return $adminGroup !== null
            && array_any($persona->groups, static fn (PersonaGroup $group): bool => $group->identifier === $adminGroup->identifier);
    }

    private function checkPersonas(): void
    {
        if ($this->personas === []) {
            throw new ConfigurationException('config/personas.php declares no personas. Add at least one Persona to personas:.');
        }

        $byKey = [];
        $byUsername = [];

        foreach ($this->personas as $persona) {
            $key = $persona->key->value;

            if (isset($byKey[$key])) {
                throw new ConfigurationException(sprintf("Two personas use the key '%s'. Give each persona its own key.", $key));
            }

            if (isset($byUsername[$persona->username])) {
                throw new ConfigurationException(sprintf(
                    "Personas '%s' and '%s' share the username '%s'. Give each its own username.",
                    $byUsername[$persona->username],
                    $key,
                    $persona->username,
                ));
            }

            if ($this->usernamePrefix !== '' && ! str_starts_with($persona->username, $this->usernamePrefix)) {
                throw new ConfigurationException(sprintf(
                    "Persona '%s' has the username '%s', which does not start with '%s'. Persona usernames must not be "
                    . "real IU usernames; use e.g. '%s%s'.",
                    $key,
                    $persona->username,
                    $this->usernamePrefix,
                    $this->usernamePrefix,
                    $key,
                ));
            }

            $byKey[$key] = true;
            $byUsername[$persona->username] = $key;
        }
    }

    /**
     * One label per identifier and one identifier per label, across every group
     * the roster mentions: the System Admin check matches on labels, and
     * User::isSystemAdmin() refuses an ambiguous one.
     */
    private function checkGroups(): void
    {
        $groups = array_merge(
            ...array_map(static fn (Persona $persona): array => $persona->groups, $this->personas),
            ...array_map(static fn (RoleGrant $grant): array => $grant->groups, $this->grants),
        );

        if ($this->systemAdminGroup !== null) {
            $groups[] = $this->systemAdminGroup;
        }

        $labelOf = [];
        $identifierOf = [];

        foreach ($groups as $group) {
            $label = $labelOf[$group->identifier] ?? null;

            if ($label !== null && $label !== $group->label) {
                throw new ConfigurationException(sprintf(
                    "Group '%s' is declared with two labels, '%s' and '%s'. Declare each group once and reuse the variable.",
                    $group->identifier,
                    $label,
                    $group->label,
                ));
            }

            $identifier = $identifierOf[$group->label] ?? null;

            if ($identifier !== null && $identifier !== $group->identifier) {
                throw new ConfigurationException(sprintf(
                    "Groups '%s' and '%s' share the label '%s'. Labels must be unique, because the System Admin check "
                    . 'matches on them.',
                    $identifier,
                    $group->identifier,
                    $group->label,
                ));
            }

            $labelOf[$group->identifier] = $group->label;
            $identifierOf[$group->label] = $group->identifier;
        }
    }

    private function checkGrants(): void
    {
        $roles = [];

        foreach ($this->grants as $grant) {
            if (isset($roles[$grant->role])) {
                throw new ConfigurationException(sprintf("Two grants name the role '%s'. Merge them into one RoleGrant.", $grant->role));
            }

            $roles[$grant->role] = true;
        }
    }
}
