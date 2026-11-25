<?php

namespace App\Support;

/**
 * Accès à la clé applicative sous forme d'octets bruts.
 *
 * Laravel stocke généralement APP_KEY sous la forme « base64:... ». Les usages
 * cryptographiques directs (HMAC, index aveugle) ont besoin des octets réels.
 */
class SecretKey
{
    public static function bytes(): string
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }
}
