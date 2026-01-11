<?php

declare(strict_types=1);

namespace App\Domain\Document\Event;

final readonly class DocumentUpdated
{
    public function __construct(
        public string $documentId,
        public string $sessionId,
        public bool $hasContentChanges,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
