<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateDocumentRequest
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        #[Assert\Length(
            min: 1,
            max: 255,
            minMessage: 'Le titre doit contenir au moins {{ limit }} caractère',
            maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
        )]
        public ?string $title = null,

        public ?string $content = null,

        public ?array $metadata = null,

        #[Assert\Uuid(message: 'L\'ID du document parent doit être un UUID valide')]
        public ?string $parentId = null,

        #[Assert\PositiveOrZero(message: 'L\'ordre de tri doit être un nombre positif ou zéro')]
        public ?int $sortOrder = null,

        #[Assert\Length(
            max: 500,
            maxMessage: 'La description du changement ne peut pas dépasser {{ limit }} caractères'
        )]
        public ?string $changeDescription = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            content: $data['content'] ?? null,
            metadata: $data['metadata'] ?? null,
            parentId: $data['parent_id'] ?? $data['parentId'] ?? null,
            sortOrder: $data['sort_order'] ?? $data['sortOrder'] ?? null,
            changeDescription: $data['change_description'] ?? $data['changeDescription'] ?? null,
        );
    }

    public function hasChanges(): bool
    {
        return $this->title !== null
            || $this->content !== null
            || $this->metadata !== null
            || $this->parentId !== null
            || $this->sortOrder !== null;
    }
}
