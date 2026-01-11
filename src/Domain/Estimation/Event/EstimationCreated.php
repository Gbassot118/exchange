<?php

declare(strict_types=1);

namespace App\Domain\Estimation\Event;

final readonly class EstimationCreated
{
    public function __construct(
        public string $estimationId,
        public string $sessionId,
        public string $title,
        public ?string $linkedDocumentId,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
