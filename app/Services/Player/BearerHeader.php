<?php

declare(strict_types=1);

namespace App\Services\Player;

/** Apache internal redirects may rename the original Authorization server variable. */
final class BearerHeader
{
    public static function token(string $header, array $server): ?string
    {
        if ($header === '') {
            $keys = array_filter(array_keys($server), static fn ($key): bool =>
                is_string($key) && preg_match('/^(?:REDIRECT_)+HTTP_AUTHORIZATION$/D', $key) === 1);
            usort($keys, static fn (string $a, string $b): int => strlen($a) <=> strlen($b));
            if ($keys !== []) $header = is_string($server[$keys[0]]) ? $server[$keys[0]] : '';
        }
        // A present but malformed primary header must never fall back to another credential.
        return preg_match('/^Bearer ([A-Za-z0-9_.-]+)$/D', $header, $match) === 1 ? $match[1] : null;
    }
}
