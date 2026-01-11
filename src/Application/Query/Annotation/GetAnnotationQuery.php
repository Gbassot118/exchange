<?php

declare(strict_types=1);

namespace App\Application\Query\Annotation;

final readonly class GetAnnotationQuery
{
    public function __construct(
        public string $annotationId,
        public bool $includeReplies = true,
    ) {}
}
