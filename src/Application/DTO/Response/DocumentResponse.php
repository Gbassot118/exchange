<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Entity\Document;

final class DocumentResponse
{
    /**
     * @param array<AnnotationResponse>|null $annotations
     * @param array<DocumentVersionResponse>|null $versions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $type,
        public readonly ?string $parentId,
        public readonly int $sortOrder,
        public readonly int $currentVersion,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $content = null,
        public readonly ?array $metadata = null,
        public ?array $annotations = null,
        public ?array $versions = null,
    ) {}

    public static function fromEntity(
        Document $document,
        bool $includeContent = true,
        bool $includeAnnotations = false,
        bool $includeVersions = false
    ): self {
        $response = new self(
            id: $document->getId()->toString(),
            title: $document->getTitle(),
            slug: $document->getSlug(),
            type: $document->getType(),
            parentId: $document->getParent()?->getId()->toString(),
            sortOrder: $document->getSortOrder(),
            currentVersion: $document->getCurrentVersion(),
            createdAt: $document->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $document->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            content: $includeContent ? $document->getContent() : null,
            metadata: $includeContent ? $document->getMetadata() : null,
        );

        if ($includeVersions) {
            $response->versions = array_map(
                fn($v) => DocumentVersionResponse::fromEntity($v),
                $document->getVersions()->toArray()
            );
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type,
            'parent_id' => $this->parentId,
            'sort_order' => $this->sortOrder,
            'current_version' => $this->currentVersion,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'content' => $this->content,
            'metadata' => $this->metadata,
            'annotations' => $this->annotations !== null
                ? array_map(fn($a) => $a->toArray(), $this->annotations)
                : null,
            'versions' => $this->versions !== null
                ? array_map(fn($v) => $v->toArray(), $this->versions)
                : null,
        ], fn($value) => $value !== null);
    }
}
