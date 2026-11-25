<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Chiffrement authentifié d'une chaîne au repos.
 *
 * S'appuie sur l'implémentation Laravel (AES-256-CBC + HMAC, chiffrement puis
 * authentification). Les valeurs héritées encore en clair restent lisibles afin
 * de ne pas casser les données existantes ; elles sont chiffrées au prochain
 * enregistrement.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedString implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return '';
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            return (string) $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value === '') {
            return '';
        }

        return Crypt::encryptString((string) $value);
    }
}
