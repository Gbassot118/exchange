<?php

declare(strict_types=1);

namespace App\Application\Query\Decision;

final readonly class GetDecisionQuery
{
    public function __construct(
        public string $decisionId,
        public bool $includeVotes = true,
    ) {}
}
