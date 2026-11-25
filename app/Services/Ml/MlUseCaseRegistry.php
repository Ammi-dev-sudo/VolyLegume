<?php

namespace App\Services\Ml;

/**
 * Résout les composants ML associés à un cas d'usage.
 */
class MlUseCaseRegistry
{
    public const YIELD = 'yield';

    public const ACTIVITY_RISK = 'activity-risk';

    public const CUSTOMER_SEGMENT = 'customer-segment';

    public function builder(string $useCase): ?MlDatasetBuilder
    {
        return match ($useCase) {
            self::YIELD => app(YieldDatasetBuilder::class),
            self::ACTIVITY_RISK => app(ActivityRiskDatasetBuilder::class),
            self::CUSTOMER_SEGMENT => app(CustomerSegmentDatasetBuilder::class),
            default => null,
        };
    }

    public function runner(string $useCase): ?PythonModelRunner
    {
        return match ($useCase) {
            self::YIELD => app(YieldModelRunner::class),
            self::ACTIVITY_RISK => app(ActivityRiskModelRunner::class),
            self::CUSTOMER_SEGMENT => app(CustomerSegmentModelRunner::class),
            default => null,
        };
    }

    public function importer(string $useCase): ?MlPredictionImporter
    {
        return match ($useCase) {
            self::YIELD => app(YieldPredictionImporter::class),
            self::ACTIVITY_RISK => app(ActivityRiskPredictionImporter::class),
            self::CUSTOMER_SEGMENT => app(CustomerSegmentPredictionImporter::class),
            default => null,
        };
    }

    public function artifactBase(string $useCase): string
    {
        return match ($useCase) {
            self::YIELD => 'yield',
            self::ACTIVITY_RISK => 'activity_risk',
            self::CUSTOMER_SEGMENT => 'customer_segment',
            default => $useCase,
        };
    }

    /**
     * @return list<string>
     */
    public function supportedUseCases(): array
    {
        return [self::YIELD, self::ACTIVITY_RISK, self::CUSTOMER_SEGMENT];
    }
}
