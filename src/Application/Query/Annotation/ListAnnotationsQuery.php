<?php

declare(strict_types=1);

namespace App\Application\Query\Annotation;

final readonly class ListAnnotationsQuery
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(
        public string $sessionId,
        public ?string $documentId = null,
        public array $filters = [],
        public bool $includeReplies = true,
    ) {}
}
