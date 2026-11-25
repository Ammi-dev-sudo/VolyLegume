<?php

namespace App\Support;

use App\Models\AuditLog;
use DateTimeInterface;
use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * Chaîne de hachage du journal d'audit.
 *
 * Chaque entrée référence le hachage de la précédente (prev_hash) et possède
 * son propre hachage HMAC-SHA256 (entry_hash). Toute modification, insertion ou
 * suppression d'une entrée rompt la chaîne et devient détectable.
 */
class AuditChain
{
    /**
     * Calcule le hachage d'une entrée à partir du hachage précédent.
     */
    public static function hash(?string $previousHash, AuditLog $log): string
    {
        return hash_hmac(
            'sha256',
            ($previousHash ?? '').'|'.self::payload($log),
            SecretKey::bytes(),
        );
    }

    /**
     * Sérialise de manière déterministe les données couvertes par le hachage.
     */
    public static function payload(AuditLog $log): string
    {
        $data = self::canonicalize([
            'id' => $log->getKey(),
            'farm_id' => $log->farm_id,
            'user_id' => $log->user_id,
            'action' => $log->action,
            'description' => $log->description,
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'context' => $log->context,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
        ]);

        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Vérifie l'intégrité de la chaîne.
     *
     * La première entrée chaînée sert d'ancrage : elle peut suivre des entrées
     * antérieures à la mise en place du chaînage (entry_hash nul). Cela reste
     * compatible avec une base existante et avec la purge planifiée du journal.
     *
     * @return array{valid: bool, checked: int, legacy: int, first_broken_id: ?int}
     */
    public static function verify(): array
    {
        $previousHash = null;
        $checked = 0;
        $legacy = 0;
        $anchored = false;
        $firstBrokenId = null;

        AuditLog::query()
            ->orderBy('id')
            ->chunk(1000, function (Collection $logs) use (&$previousHash, &$checked, &$legacy, &$anchored, &$firstBrokenId): void {
                foreach ($logs as $log) {
                    if ($firstBrokenId !== null) {
                        return;
                    }

                    if (! $anchored) {
                        if ($log->entry_hash === null) {
                            $legacy++;

                            continue;
                        }

                        $anchored = true;
                    }

                    $checked++;

                    $expectedHash = self::hash($log->prev_hash, $log);

                    if ($log->entry_hash === null || ! hash_equals($log->entry_hash, $expectedHash)) {
                        $firstBrokenId = $log->id;

                        return;
                    }

                    if ($previousHash !== null && $log->prev_hash !== $previousHash) {
                        $firstBrokenId = $log->id;

                        return;
                    }

                    $previousHash = $log->entry_hash;
                }
            });

        return [
            'valid' => $firstBrokenId === null,
            'checked' => $checked,
            'legacy' => $legacy,
            'first_broken_id' => $firstBrokenId,
        ];
    }

    /**
     * Ordonne récursivement les clés pour un encodage JSON stable.
     */
    private static function canonicalize(mixed $value): mixed
    {
        if ($value instanceof JsonSerializable) {
            return self::canonicalize($value->jsonSerialize());
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::canonicalize(...), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = self::canonicalize($item);
        }

        return $value;
    }
}
