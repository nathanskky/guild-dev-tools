<?php

declare(strict_types=1);

namespace Guild\DevTools\Persona;

use Guild\Framework\Authorization\Identity\Identity;
use Guild\Framework\Exception\ConfigurationException;

/**
 * A made-up user for local development and tests: a username, IU Login-style
 * attributes, and the Grouper groups it is in.
 *
 * Final but not readonly: PHP does not allow a hooked property such as
 * $identity on a readonly class, so each stored property is readonly instead.
 */
final class Persona
{
    public readonly PersonaKey $key;

    public readonly string $username;

    public readonly string $name;

    /**
     * Who this persona is, as IU Login would report it. The username claim
     * always comes from $username.
     */
    public Identity $identity {
        get => new Identity($this->username, $this->attributes + ['username' => $this->username, 'name' => $this->name]);
    }

    /**
     * @param  string|PersonaKey  $key  The name signedInAs() and the session use; PersonaKey gives the format.
     * @param  string  $username  The username claim. Trimmed; must not be blank.
     * @param  string  $name  The name claim, shown on the picker and banner. Trimmed; must not be blank.
     * @param  list<PersonaGroup>  $groups  The Grouper groups the persona is in.
     * @param  DirectoryStatus  $directory  Unavailable makes Grouper unreachable for this persona.
     * @param  array<string, string>  $attributes  Further IU Login claims, e.g. 'email', used as given.
     *                                             Must not contain 'username' or 'name'.
     *
     * @throws ConfigurationException When the key, a blank value or a reserved attribute breaks a rule above.
     */
    public function __construct(
        string|PersonaKey $key,
        string $username,
        string $name,
        public readonly array $groups = [],
        public readonly DirectoryStatus $directory = DirectoryStatus::Available,
        public readonly array $attributes = [],
    ) {
        $this->key = $key instanceof PersonaKey ? $key : new PersonaKey($key);
        $this->username = trim($username);
        $this->name = trim($name);

        if ($this->username === '') {
            throw new ConfigurationException(sprintf(
                "Persona '%s' has a blank username. Give it one, e.g. 'persona-%s'.",
                $this->key->value,
                $this->key->value,
            ));
        }

        if ($this->name === '') {
            throw new ConfigurationException(sprintf(
                "Persona '%s' has a blank name. Give it the display name IU Login would release, e.g. 'Erin Editor'.",
                $this->key->value,
            ));
        }

        foreach (['username', 'name'] as $claim) {
            if (array_key_exists($claim, $attributes)) {
                throw new ConfigurationException(sprintf(
                    "Persona '%s' sets '%s' in attributes:. Use the %s: argument; it is the only source of the %s claim.",
                    $this->key->value,
                    $claim,
                    $claim,
                    $claim,
                ));
            }
        }
    }
}
