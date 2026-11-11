<?php

declare(strict_types=1);

namespace Guild\DevTools\SignIn;

use Guild\DevTools\Persona\PersonaKey;
use Guild\Framework\Exception\ConfigurationException;
use Guild\Framework\Session\SessionStore;

/**
 * The persona this visitor is signed in as, kept in their session by key.
 *
 * @internal
 */
final readonly class SignedInPersona
{
    private const string KEY = 'guild_dev_tools_persona';

    public function __construct(private SessionStore $session)
    {
    }

    /**
     * The stored key, or null for a guest or a value that is not a valid key.
     */
    public function key(): ?PersonaKey
    {
        $value = $this->session->get(self::KEY);

        if (! is_string($value)) {
            return null;
        }

        try {
            return new PersonaKey($value);
        } catch (ConfigurationException) {
            return null;
        }
    }

    /**
     * The raw stored value, for saying which persona went missing.
     */
    public function storedValue(): ?string
    {
        $value = $this->session->get(self::KEY);

        return is_string($value) ? $value : null;
    }

    public function remember(PersonaKey $key): void
    {
        $this->session->put(self::KEY, $key->value);
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }
}
