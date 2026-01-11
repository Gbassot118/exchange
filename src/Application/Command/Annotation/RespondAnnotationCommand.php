<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

final readonly class RespondAnnotationCommand
{
    public function __construct(
        public string $annotationId,
        public string $authorParticipantId,
        public string $content,
        public bool $markAsResolved = false,
    ) {}
}
