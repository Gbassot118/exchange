<?php

declare(strict_types=1);

namespace App\Application\Command\Estimation;

final readonly class VoteEstimationCommand
{
    public function __construct(
        public string $estimationId,
        public string $participantId,
        public string $value,
    ) {}
}
