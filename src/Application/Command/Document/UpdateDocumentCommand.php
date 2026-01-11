<?php

declare(strict_types=1);

namespace App\Application\Command\Document;

final readonly class UpdateDocumentCommand
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public string $documentId,
        public ?string $title = null,
        public ?string $content = null,
        public ?array $metadata = null,
        public ?string $parentId = null,
        public ?int $sortOrder = null,
        public ?string $changeDescription = null,
        public ?string $authorParticipantId = null,
    ) {}

    public function hasChanges(): bool
    {
        return $this->title !== null
            || $this->content !== null
            || $this->metadata !== null
            || $this->parentId !== null
            || $this->sortOrder !== null;
    }
}
