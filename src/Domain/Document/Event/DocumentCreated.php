<?php

declare(strict_types=1);

namespace App\Domain\Document\Event;

final readonly class DocumentCreated
{
    public function __construct(
        public string $documentId,
        public string $sessionId,
        public string $title,
        public string $type,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
