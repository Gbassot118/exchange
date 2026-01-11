<?php

declare(strict_types=1);

namespace App\Application\Query\Document;

final readonly class ListDocumentsQuery
{
    public function __construct(
        public string $sessionId,
        public ?string $parentId = null,
        public ?string $type = null,
        public bool $includeContent = false,
    ) {}
}
