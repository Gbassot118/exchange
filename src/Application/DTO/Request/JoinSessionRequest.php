<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class JoinSessionRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le pseudo est obligatoire')]
        #[Assert\Length(
            min: 1,
            max: 100,
            minMessage: 'Le pseudo doit contenir au moins {{ limit }} caractère',
            maxMessage: 'Le pseudo ne peut pas dépasser {{ limit }} caractères'
        )]
        public string $pseudo,

        #[Assert\Regex(
            pattern: '/^#[0-9A-Fa-f]{6}$/',
            message: 'La couleur doit être au format hexadécimal (#RRGGBB)'
        )]
        public ?string $color = null,

        public bool $isAgent = false,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pseudo: $data['pseudo'] ?? '',
            color: $data['color'] ?? null,
            isAgent: $data['is_agent'] ?? $data['isAgent'] ?? false,
        );
    }
}
