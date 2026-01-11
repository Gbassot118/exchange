<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateAnnotationRequest
{
    /**
     * @param array<string, mixed>|null $anchor
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Le contenu de l\'annotation est obligatoire')]
        #[Assert\Length(
            min: 1,
            max: 10000,
            minMessage: 'Le contenu doit contenir au moins {{ limit }} caractère',
            maxMessage: 'Le contenu ne peut pas dépasser {{ limit }} caractères'
        )]
        public string $content,

        #[Assert\NotBlank(message: 'L\'ID du document est obligatoire')]
        #[Assert\Uuid(message: 'L\'ID du document doit être un UUID valide')]
        public string $documentId,

        #[Assert\Choice(
            choices: ['comment'],
            message: 'Le type d\'annotation doit être: comment'
        )]
        public string $type = 'comment',

        public ?array $anchor = null,

        #[Assert\Uuid(message: 'L\'ID de l\'annotation parente doit être un UUID valide')]
        public ?string $parentId = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'] ?? '',
            documentId: $data['document_id'] ?? $data['documentId'] ?? '',
            type: $data['type'] ?? 'comment',
            anchor: $data['anchor'] ?? null,
            parentId: $data['parent_id'] ?? $data['parentId'] ?? null,
        );
    }

    public function isReply(): bool
    {
        return $this->parentId !== null;
    }
}
