<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\AuditChain;
use Illuminate\Console\Command;

/**
 * Chaîne les entrées d'audit antérieures à la mise en place du chaînage.
 *
 * Opération de rattrapage à exécuter une seule fois : la commande refuse de
 * s'exécuter dès qu'une entrée est déjà chaînée, afin de ne jamais « bénir »
 * une chaîne existante potentiellement altérée.
 */
class ChainAuditLogs extends Command
{
    protected $signature = 'audit:chain';

    protected $description = 'Chaîne les entrées d’audit historiques (rattrapage unique)';

    public function handle(): int
    {
        if (AuditLog::query()->whereNotNull('entry_hash')->exists()) {
            $this->error('Des entrées sont déjà chaînées : utilisez « audit:verify » pour contrôler l’intégrité.');

            return self::FAILURE;
        }

        $previousHash = null;
        $chained = 0;

        AuditLog::query()
            ->orderBy('id')
            ->chunkById(500, function ($logs) use (&$previousHash, &$chained): void {
                foreach ($logs as $log) {
                    $log->prev_hash = $previousHash;
                    $log->entry_hash = AuditChain::hash($previousHash, $log);
                    $log->save();

                    $previousHash = $log->entry_hash;
                    $chained++;
                }
            });

        $this->info(sprintf('%d entrée(s) d’audit chaînée(s).', $chained));

        return self::SUCCESS;
    }
}
