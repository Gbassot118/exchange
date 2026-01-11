<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class VoteRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'ID de l\'option est obligatoire')]
        #[Assert\Uuid(message: 'L\'ID de l\'option doit être un UUID valide')]
        public string $optionId,

        #[Assert\Length(
            max: 1000,
            maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères'
        )]
        public ?string $comment = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            optionId: $data['option_id'] ?? $data['optionId'] ?? '',
            comment: $data['comment'] ?? null,
        );
    }
}
