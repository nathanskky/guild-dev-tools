<?php

declare(strict_types=1);

namespace Guild\DevTools\Persona;

use Guild\Framework\Exception\ConfigurationException;
use Guild\Grouper\GrouperGroup;

/**
 * A Grouper group a persona is in, or a role grant is mapped to. It names a
 * real Grouper group, so the grants written for it work against production.
 */
final readonly class PersonaGroup
{
    /** The Grouper system name, e.g. 'iu:roles:sys:acm:starter-editors'; what grants match on. */
    public string $identifier;

    /** The ACM label, e.g. 'Starter Editors'; what the System Admin check matches on. */
    public string $label;

    public function __construct(string $identifier, string $label)
    {
        $this->identifier = trim($identifier);
        $this->label = trim($label);

        if ($this->identifier === '') {
            throw new ConfigurationException(
                "A persona group has a blank identifier. Give it the group's Grouper system name, "
                . "e.g. 'iu:roles:sys:acm:starter-editors'."
            );
        }

        if ($this->label === '') {
            throw new ConfigurationException(sprintf(
                "Persona group '%s' has a blank label. Give it the group's ACM label, e.g. 'Starter Editors'.",
                $this->identifier,
            ));
        }
    }

    /**
     * The group as Grouper would return it.
     */
    public function toGrouperGroup(): GrouperGroup
    {
        return new GrouperGroup($this->identifier, $this->label, $this->label, md5($this->identifier));
    }
}
