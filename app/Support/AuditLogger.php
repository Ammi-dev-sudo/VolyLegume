<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Farm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Écriture centralisée des événements d'audit.
 *
 * Le journal ne doit jamais interrompre une opération métier : toute erreur
 * d'écriture est convertie en avertissement de log applicatif.
 */
class AuditLogger
{
    public function __construct(private FarmContext $farmContext) {}

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $action,
        string $description,
        ?Model $subject = null,
        array $context = [],
        ?int $farmId = null,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): ?AuditLog {
        try {
            $request = app()->bound('request') ? request() : null;

            $userId ??= $request?->user()?->id ?? Auth::id();
            $farmId ??= $this->resolveFarmId($subject);

            return AuditLog::create([
                'farm_id' => $farmId,
                'user_id' => $userId,
                'action' => Str::limit($action, 80, ''),
                'description' => Str::limit($description, 500, '…'),
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'context' => $context !== [] ? $context : null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Impossible d’écrire une entrée d’audit.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function recordModelEvent(string $event, Model $model): void
    {
        $entity = Str::snake(class_basename($model));
        $label = Str::headline(class_basename($model));

        [$oldValues, $newValues] = $this->valuesFor($event, $model);

        $this->record(
            $entity.'.'.$event,
            sprintf('%s : %s (%s #%s)', $label, $this->eventLabel($event), $label, $model->getKey()),
            $model,
            [],
            null,
            null,
            $oldValues,
            $newValues,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function denied(string $description, array $context = []): void
    {
        $this->record('access.denied', $description, null, $context);
    }

    /**
     * @return array{0: ?array<string, mixed>, 1: ?array<string, mixed>}
     */
    private function valuesFor(string $event, Model $model): array
    {
        $excluded = ['password', 'remember_token', 'created_at', 'updated_at'];

        if ($event === 'created') {
            return [null, $this->except($model->getAttributes(), $excluded)];
        }

        if ($event === 'updated') {
            $changes = array_keys($model->getChanges());

            return [
                $this->except($model->getOriginal(), array_merge($excluded, array_diff(array_keys($model->getAttributes()), $changes))),
                $this->except($model->getChanges(), $excluded),
            ];
        }

        return [$this->except($model->getOriginal(), $excluded), null];
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $excluded
     * @return array<string, mixed>
     */
    private function except(array $values, array $excluded): array
    {
        return array_filter(
            $values,
            fn (mixed $value, string $key): bool => ! in_array($key, $excluded, true) && ! is_object($value),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function resolveFarmId(?Model $subject): ?int
    {
        if ($subject instanceof Farm) {
            return $subject->getKey();
        }

        if ($subject !== null && isset($subject->farm_id)) {
            return (int) $subject->farm_id;
        }

        return $this->farmContext->id();
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'created' => 'création',
            'updated' => 'modification',
            'deleted' => 'suppression',
            default => $event,
        };
    }
}
