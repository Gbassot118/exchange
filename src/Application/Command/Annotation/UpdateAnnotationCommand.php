<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

final readonly class UpdateAnnotationCommand
{
    public function __construct(
        public string $annotationId,
        public ?string $content = null,
        public ?string $status = null,
    ) {}
}
