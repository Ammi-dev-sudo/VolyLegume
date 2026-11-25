<?php

namespace App\Support;

/**
 * Index aveugle pour la recherche exacte sur un champ chiffré.
 *
 * Le hachage HMAC reste déterministe (donc interrogeable) sans révéler la
 * valeur d'origine ni permettre d'inverser le contenu.
 */
class BlindIndex
{
    public static function for(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^0-9a-z+]/u', '', mb_strtolower(trim($value))) ?? '';

        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, SecretKey::bytes());
    }
}
