<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Entity\EstimationVote;

final readonly class EstimationVoteResponse
{
    public function __construct(
        public string $id,
        public string $participantId,
        public string $participantPseudo,
        public string $value,
        public string $createdAt,
    ) {}

    public static function fromEntity(EstimationVote $vote): self
    {
        return new self(
            id: $vote->getId()->toString(),
            participantId: $vote->getParticipant()->getId()->toString(),
            participantPseudo: $vote->getParticipant()->getPseudo(),
            value: $vote->getValue(),
            createdAt: $vote->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'participant_id' => $this->participantId,
            'participant_pseudo' => $this->participantPseudo,
            'value' => $this->value,
            'created_at' => $this->createdAt,
        ];
    }
}
