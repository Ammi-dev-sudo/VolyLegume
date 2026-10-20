<?php

namespace App\Exceptions;

use App\Models\Campaign;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * RG08 à RG10 : la récolte est refusée avant la borne autorisée la plus tardive.
 */
class HarvestTooEarlyException extends RuntimeException
{
    public function __construct(
        public readonly Campaign $campaign,
        public readonly CarbonInterface $harvestedAt,
        public readonly CarbonInterface $minimumHarvestAt,
    ) {
        parent::__construct(sprintf(
            'Récolte refusée : la campagne « %s » est soumise à une restriction jusqu’au %s.',
            $campaign->name,
            $minimumHarvestAt->format('d/m/Y H:i'),
        ));
    }
}
