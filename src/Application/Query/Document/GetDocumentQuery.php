<?php

declare(strict_types=1);

namespace App\Application\Query\Document;

final readonly class GetDocumentQuery
{
    public function __construct(
        public string $documentId,
        public bool $includeContent = true,
        public bool $includeAnnotations = false,
        public bool $includeVersions = false,
    ) {}
}
