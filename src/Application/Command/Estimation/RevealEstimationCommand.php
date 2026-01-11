<?php

declare(strict_types=1);

namespace App\Application\Command\Estimation;

final readonly class RevealEstimationCommand
{
    public function __construct(
        public string $estimationId,
    ) {}
}
