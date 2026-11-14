<?php

declare(strict_types=1);

namespace App\Services\Player;

use App\Exceptions\PlayerException;

/** Signs and verifies short-lived admission tickets without storing them in the database. */
final class Credentials
{
    // Sign a purpose-limited ticket with an expiry.
    public function sign(string $purpose, array $claims, int $ttl): string
    {
        $payload = self::encode(json_encode(['purpose' => $purpose, 'exp' => time() + $ttl, 'claims' => $claims], JSON_THROW_ON_ERROR));
        return $payload . '.' . self::encode(hash_hmac('sha256', $payload, $this->key(), true));
    }

    // Verify ticket signature, purpose, and expiry.
    public function verify(string $token, string $purpose): array
    {
        if (strlen($token) > 16000) throw new PlayerException('invalid_credential', 401);
        $parts = explode('.', $token);
        if (count($parts) !== 2 || ! hash_equals(self::encode(hash_hmac('sha256', $parts[0], $this->key(), true)), $parts[1])) {
            throw new PlayerException('invalid_credential', 401);
        }
        $data = json_decode(base64_decode(strtr($parts[0], '-_', '+/'), true) ?: '', true);
        if (! is_array($data) || ($data['purpose'] ?? null) !== $purpose || ! is_array($data['claims'] ?? null)) {
            throw new PlayerException('invalid_credential', 401);
        }
        if (($data['exp'] ?? 0) <= time()) throw new PlayerException('credential_expired', 410);
        return $data['claims'];
    }

    // Read the configured signing key.
    private function key(): string
    {
        $key = (string) config('Encryption')->key;
        if ($key === '') throw new PlayerException('signing_unavailable', 503);
        return hash('sha256', 'edutest-player:' . $key, true);
    }

    // Encode ticket data for transport.
    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
