<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Purge les entrées anciennes du journal d'audit selon une politique de rétention.
 *
 * Les opérations financières, les traitements et les récoltes sont conservés
 * plus longtemps que les connexions, refus d'accès et consultations, afin de
 * préserver les preuves utiles au suivi de l'exploitation.
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune
        {--days=365 : Durée de conservation des événements courants}
        {--keep-days=1825 : Durée de conservation des événements sensibles}
        {--farm= : Limiter la purge à une exploitation}
        {--dry-run : Compter les lignes sans les supprimer}
        {--chunk=1000 : Nombre de lignes supprimées par lot}';

    protected $description = 'Purge les entrées anciennes du journal d’audit selon une politique de rétention';

    /**
     * Actions dont la conservation est allongée.
     *
     * @var list<string>
     */
    private const SENSITIVE_PREFIXES = [
        'expense.',
        'revenue.',
        'sale.',
        'payment.',
        'treatment.',
        'harvest.',
        'produce_lot.',
        'stock_movement.',
    ];

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $keepDays = max($days, (int) $this->option('keep-days'));
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $farmId = $this->option('farm') !== null ? (int) $this->option('farm') : null;

        $cutoff = now()->subDays($days);
        $sensitiveCutoff = now()->subDays($keepDays);

        $query = $this->prunableQuery($cutoff, $sensitiveCutoff, $farmId);
        $total = (clone $query)->count();

        if ($dryRun) {
            $this->info(sprintf(
                '%d entrée(s) seraient supprimées (événements antérieurs au %s, sensibles antérieurs au %s).',
                $total,
                $cutoff->format('d/m/Y'),
                $sensitiveCutoff->format('d/m/Y'),
            ));

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $ids = (clone $query)->orderBy('id')->limit($chunk)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += AuditLog::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunk);

        $this->info(sprintf('%d entrée(s) du journal d’audit supprimée(s).', $deleted));

        return self::SUCCESS;
    }

    /**
     * @return Builder<AuditLog>
     */
    private function prunableQuery(Carbon $cutoff, Carbon $sensitiveCutoff, ?int $farmId): Builder
    {
        return AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->when($farmId !== null, fn (Builder $query) => $query->where('farm_id', $farmId))
            ->where(function (Builder $query) use ($sensitiveCutoff): void {
                $query->where('created_at', '<', $sensitiveCutoff)
                    ->orWhere(function (Builder $query): void {
                        $query->where(function (Builder $query): void {
                            foreach (self::SENSITIVE_PREFIXES as $prefix) {
                                $query->where('action', 'not like', $prefix.'%');
                            }
                        });
                    });
            });
    }
}
