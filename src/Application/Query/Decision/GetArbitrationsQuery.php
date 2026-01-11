<?php

declare(strict_types=1);

namespace App\Application\Query\Decision;

final readonly class GetArbitrationsQuery
{
    public function __construct(
        public string $sessionId,
    ) {}
}
