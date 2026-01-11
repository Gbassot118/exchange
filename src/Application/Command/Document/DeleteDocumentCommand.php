<?php

declare(strict_types=1);

namespace App\Application\Command\Document;

final readonly class DeleteDocumentCommand
{
    public function __construct(
        public string $documentId,
    ) {}
}
