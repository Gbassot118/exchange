<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateSessionRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le titre de la session est obligatoire')]
        #[Assert\Length(
            min: 3,
            max: 255,
            minMessage: 'Le titre doit contenir au moins {{ limit }} caractères',
            maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
        )]
        public string $title,

        #[Assert\Length(
            max: 2000,
            maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
        )]
        public ?string $description = null,

        #[Assert\NotBlank(message: 'Le pseudo du créateur est obligatoire')]
        #[Assert\Length(
            min: 1,
            max: 100,
            minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractère',
            maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères'
        )]
        public ?string $creatorPseudo = null,

        public bool $isAgent = false,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            creatorPseudo: $data['creator_pseudo'] ?? $data['creatorPseudo'] ?? $data['agent_name'] ?? null,
            isAgent: $data['is_agent'] ?? $data['isAgent'] ?? false,
        );
    }
}
