<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use App\Domain\Session\ValueObject\SessionStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateSessionStatusRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le statut est obligatoire')]
        #[Assert\Choice(
            choices: ['preparation', 'en_cours', 'termine', 'archive'],
            message: 'Le statut doit être l\'un des suivants: preparation, en_cours, termine, archive'
        )]
        public string $status,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? '',
        );
    }

    public function toSessionStatus(): SessionStatus
    {
        return SessionStatus::fromString($this->status);
    }
}
