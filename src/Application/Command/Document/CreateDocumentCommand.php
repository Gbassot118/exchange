<?php

declare(strict_types=1);

namespace App\Application\Command\Document;

final readonly class CreateDocumentCommand
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $sessionId,
        public string $title,
        public string $type,
        public ?string $content = null,
        public ?array $metadata = null,
        public ?string $parentId = null,
        public int $sortOrder = 0,
        public ?string $authorParticipantId = null,
    ) {}
}
