<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Event;

final readonly class AnnotationCreated
{
    public function __construct(
        public string $annotationId,
        public string $documentId,
        public string $sessionId,
        public string $authorPseudo,
        public bool $isReply,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
