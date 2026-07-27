<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Client;

final readonly class ClientFinancialSummary
{
    public function __construct(
        public Client $client,
        public int $projectCount,
        public int $estimatedAmountCents,
        public int $paymentsCents,
        public int $remainingToCollectCents,
        public int $unconfiguredProjectCount,
    ) {
    }
}
