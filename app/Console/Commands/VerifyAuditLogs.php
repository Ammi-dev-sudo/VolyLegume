<?php

namespace App\Console\Commands;

use App\Support\AuditChain;
use Illuminate\Console\Command;

/**
 * Vérifie l'intégrité de la chaîne de hachage du journal d'audit.
 *
 * Toute altération, insertion ou suppression d'une entrée intermédiaire rompt
 * la chaîne et est signalée par l'identifiant de la première entrée incohérente.
 */
class VerifyAuditLogs extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Vérifie l’intégrité de la chaîne de hachage du journal d’audit';

    public function handle(): int
    {
        $result = AuditChain::verify();

        if ($result['valid']) {
            $this->info(sprintf(
                'Chaîne d’audit valide (%d entrée(s) chaînée(s), %d entrée(s) antérieure(s) au chaînage).',
                $result['checked'],
                $result['legacy'],
            ));

            return self::SUCCESS;
        }

        $this->error(sprintf(
            'Chaîne d’audit rompue à l’entrée #%s (après %d entrée(s) vérifiée(s)).',
            $result['first_broken_id'],
            $result['checked'],
        ));

        return self::FAILURE;
    }
}
