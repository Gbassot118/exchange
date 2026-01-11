<?php

declare(strict_types=1);

namespace App\Application\Command\Decision;

final readonly class PostponeDecisionCommand
{
    public function __construct(
        public string $decisionId,
    ) {}
}
