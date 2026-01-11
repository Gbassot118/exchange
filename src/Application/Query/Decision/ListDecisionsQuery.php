<?php

declare(strict_types=1);

namespace App\Application\Query\Decision;

final readonly class ListDecisionsQuery
{
    public function __construct(
        public string $sessionId,
        public ?string $status = null,
        public bool $pendingOnly = false,
    ) {}
}
