<?php

namespace App\Models\Concerns;

use App\Support\AuditLogger;

/**
 * Journalise automatiquement les créations, modifications et suppressions
 * des modèles métier sensibles.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            app(AuditLogger::class)->recordModelEvent('created', $model);
        });

        static::updated(function ($model): void {
            app(AuditLogger::class)->recordModelEvent('updated', $model);
        });

        static::deleted(function ($model): void {
            app(AuditLogger::class)->recordModelEvent('deleted', $model);
        });
    }
}
