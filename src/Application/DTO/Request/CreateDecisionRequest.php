<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateDecisionRequest
{
    /**
     * @param array<array{label: string, description?: string|null}> $options
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Le titre de la décision est obligatoire')]
        #[Assert\Length(
            min: 3,
            max: 255,
            minMessage: 'Le titre doit contenir au moins {{ limit }} caractères',
            maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
        )]
        public string $title,

        #[Assert\NotBlank(message: 'Les options sont obligatoires')]
        #[Assert\Count(
            min: 2,
            max: 10,
            minMessage: 'Une décision doit avoir au moins {{ limit }} options',
            maxMessage: 'Une décision ne peut pas avoir plus de {{ limit }} options'
        )]
        public array $options,

        #[Assert\Length(
            max: 2000,
            maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
        )]
        public ?string $description = null,

        #[Assert\Uuid(message: 'L\'ID du document lié doit être un UUID valide')]
        public ?string $linkedDocumentId = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            options: $data['options'] ?? [],
            description: $data['description'] ?? null,
            linkedDocumentId: $data['linked_document_id'] ?? $data['linkedDocumentId'] ?? null,
        );
    }
}
