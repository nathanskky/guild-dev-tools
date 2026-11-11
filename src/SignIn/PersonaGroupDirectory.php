<?php

declare(strict_types=1);

namespace Guild\DevTools\SignIn;

use Guild\DevTools\Persona\DirectoryStatus;
use Guild\DevTools\Persona\PersonaGroup;
use Guild\DevTools\Persona\PersonaRoster;
use Guild\Framework\Authorization\Membership\GroupDirectory;
use Guild\Grouper\GroupMembership;
use Guild\Grouper\GrouperUnavailable;
use Override;

/**
 * Answers "which groups is this user in" from the persona roster instead of
 * Grouper. The framework's MembershipProvider still caches and serves stale
 * answers in front of it, exactly as it does for Grouper.
 */
final readonly class PersonaGroupDirectory implements GroupDirectory
{
    public function __construct(private PersonaRoster $roster, private DirectoryOutage $outage)
    {
    }

    #[Override]
    public function groupsFor(string $username): GroupMembership|GrouperUnavailable
    {
        if ($this->outage->isOn()) {
            return new GrouperUnavailable("persona sign-in's directory outage toggle is on");
        }

        $persona = $this->roster->findByUsername($username);

        // Grouper answers an unknown subject with an empty membership, not an outage.
        if ($persona === null) {
            return new GroupMembership($username, []);
        }

        if ($persona->directory === DirectoryStatus::Unavailable) {
            return new GrouperUnavailable(sprintf(
                "persona '%s' is configured with DirectoryStatus::Unavailable",
                $persona->key->value,
            ));
        }

        return new GroupMembership(
            $username,
            array_map(static fn (PersonaGroup $group) => $group->toGrouperGroup(), $persona->groups),
        );
    }
}
