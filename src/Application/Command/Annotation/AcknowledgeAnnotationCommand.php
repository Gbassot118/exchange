<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

final readonly class AcknowledgeAnnotationCommand
{
    public function __construct(
        public string $annotationId,
        public bool $acknowledged = true,
    ) {}
}
