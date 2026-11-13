<?php

declare(strict_types=1);

namespace Guild\DevTools\Persona;

use Guild\Framework\Exception\ConfigurationException;

/**
 * The short, stable name a persona is referred to by, e.g. 'editor': in
 * config/personas.php, in tests (signedInAs('editor')) and in the session.
 *
 * A key is lower-case letters, digits and hyphens, starts with a letter, and
 * is at most 40 characters. It is used as given, not trimmed.
 */
final readonly class PersonaKey
{
    private const int MAX_LENGTH = 40;

    /**
     * @throws ConfigurationException When $value breaks the key format.
     */
    public function __construct(public string $value)
    {
        if (strlen($value) > self::MAX_LENGTH || preg_match('/\A[a-z][a-z0-9-]*\z/', $value) !== 1) {
            throw new ConfigurationException(sprintf(
                "Persona key '%s' is not valid. Use lower-case letters, digits and hyphens, starting with a letter, "
                . "at most %d characters (e.g. 'editor').",
                $value,
                self::MAX_LENGTH,
            ));
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
