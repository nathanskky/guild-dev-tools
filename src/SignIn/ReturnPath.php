<?php

declare(strict_types=1);

namespace Guild\DevTools\SignIn;

/**
 * Where a visitor may be sent back to after choosing a persona: a page of
 * this site, never another host and never the picker itself. Applied to the
 * challenge's target and again to whatever the picker is handed, since a
 * ?return= or a posted form field can say anything.
 *
 * @internal
 */
final class ReturnPath
{
    private const string PICKER = PersonaSignIn::PICKER_PATH;

    /**
     * $candidate when it is a path on this site outside the picker, else '/'.
     * A path must start with a single '/': '//host' and '/\host' are
     * resolved by browsers to another site. It may hold no whitespace or
     * control character: browsers strip tabs and newlines before parsing, so
     * '/<tab>/host' is '//host' to them, and a newline cannot go in a header.
     */
    public static function from(?string $candidate): string
    {
        if ($candidate === null
            || preg_match('/[\x00-\x20\x7F]/', $candidate) === 1
            || ! str_starts_with($candidate, '/')
            || str_starts_with($candidate, '//')
            || str_starts_with($candidate, '/\\')
            || self::isThePicker($candidate)
        ) {
            return '/';
        }

        return $candidate;
    }

    private static function isThePicker(string $path): bool
    {
        return $path === self::PICKER
            || str_starts_with($path, self::PICKER . '/')
            || str_starts_with($path, self::PICKER . '?');
    }
}
