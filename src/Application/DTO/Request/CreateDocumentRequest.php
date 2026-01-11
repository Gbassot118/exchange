<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateDocumentRequest
{
    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Le titre du document est obligatoire')]
        #[Assert\Length(
            min: 1,
            max: 255,
            minMessage: 'Le titre doit contenir au moins {{ limit }} caractère',
            maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
        )]
        public string $title,

        #[Assert\NotBlank(message: 'Le type de document est obligatoire')]
        #[Assert\Choice(
            choices: ['synthesis', 'question', 'comparison', 'annexe', 'compte_rendu', 'general'],
            message: 'Le type de document doit être l\'un des suivants: synthesis, question, comparison, annexe, compte_rendu, general'
        )]
        public string $type,

        public ?string $content = null,

        public ?array $metadata = null,

        #[Assert\Uuid(message: 'L\'ID du document parent doit être un UUID valide')]
        public ?string $parentId = null,

        #[Assert\PositiveOrZero(message: 'L\'ordre de tri doit être un nombre positif ou zéro')]
        public int $sortOrder = 0,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            type: $data['type'] ?? '',
            content: $data['content'] ?? null,
            metadata: $data['metadata'] ?? null,
            parentId: $data['parent_id'] ?? $data['parentId'] ?? null,
            sortOrder: $data['sort_order'] ?? $data['sortOrder'] ?? 0,
        );
    }
}
