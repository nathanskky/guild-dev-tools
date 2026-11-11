<?php

declare(strict_types=1);

namespace Guild\DevTools\SignIn;

use Guild\Framework\Session\SessionStore;

/**
 * A simulated Grouper outage for this visitor, so the stale-membership and
 * 503 paths can be seen locally. Kept in the session, like the persona.
 *
 * @internal
 */
final readonly class DirectoryOutage
{
    private const string KEY = 'guild_dev_tools_directory_outage';

    public function __construct(private SessionStore $session)
    {
    }

    public function isOn(): bool
    {
        return $this->session->get(self::KEY) === true;
    }

    public function turnOn(): void
    {
        $this->session->put(self::KEY, true);
    }

    public function turnOff(): void
    {
        $this->session->forget(self::KEY);
    }
}
