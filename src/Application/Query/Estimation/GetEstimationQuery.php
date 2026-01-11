<?php

declare(strict_types=1);

namespace App\Application\Query\Estimation;

final readonly class GetEstimationQuery
{
    public function __construct(
        public string $estimationId,
        public bool $includeVotes = true,
    ) {}
}
