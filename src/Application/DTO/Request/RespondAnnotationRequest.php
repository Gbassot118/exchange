<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RespondAnnotationRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le contenu de la réponse est obligatoire')]
        #[Assert\Length(
            min: 1,
            max: 10000,
            minMessage: 'Le contenu doit contenir au moins {{ limit }} caractère',
            maxMessage: 'Le contenu ne peut pas dépasser {{ limit }} caractères'
        )]
        public string $content,

        public bool $markAsResolved = false,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            content: $data['content'] ?? '',
            markAsResolved: $data['mark_as_resolved'] ?? $data['markAsResolved'] ?? false,
        );
    }
}
