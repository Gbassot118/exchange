<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Entity\DocumentVersion;

final readonly class DocumentVersionResponse
{
    public function __construct(
        public string $id,
        public int $version,
        public string $content,
        public ?array $metadata,
        public ?string $authorPseudo,
        public ?string $changeDescription,
        public string $createdAt,
    ) {}

    public static function fromEntity(DocumentVersion $version): self
    {
        return new self(
            id: $version->getId()->toString(),
            version: $version->getVersion(),
            content: $version->getContent(),
            metadata: $version->getMetadata(),
            authorPseudo: $version->getAuthor()?->getPseudo(),
            changeDescription: $version->getChangeDescription(),
            createdAt: $version->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'author_pseudo' => $this->authorPseudo,
            'change_description' => $this->changeDescription,
            'created_at' => $this->createdAt,
        ];
    }
}
