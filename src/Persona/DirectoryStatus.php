<?php

declare(strict_types=1);

namespace Guild\DevTools\Persona;

/**
 * Whether Grouper answers for a persona. Unavailable simulates an outage for
 * that persona alone, so the 503 path can be seen without breaking anything.
 */
enum DirectoryStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
