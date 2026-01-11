<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

final readonly class ResolveAnnotationCommand
{
    public function __construct(
        public string $annotationId,
        public string $resolvedByParticipantId,
    ) {}
}
