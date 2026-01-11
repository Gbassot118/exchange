<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Event;

final readonly class AnnotationResolved
{
    public function __construct(
        public string $annotationId,
        public string $sessionId,
        public string $resolvedByPseudo,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
