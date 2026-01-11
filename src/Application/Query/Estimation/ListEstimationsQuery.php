<?php

declare(strict_types=1);

namespace App\Application\Query\Estimation;

final readonly class ListEstimationsQuery
{
    public function __construct(
        public string $sessionId,
        public ?string $status = null,
        public ?string $documentId = null,
        public bool $openOnly = false,
    ) {}
}
