<?php

declare(strict_types=1);

namespace App\Application\Command\Decision;

final readonly class ValidateDecisionCommand
{
    public function __construct(
        public string $decisionId,
        public string $selectedOptionId,
    ) {}
}
