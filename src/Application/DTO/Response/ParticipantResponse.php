<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Entity\Participant;

final readonly class ParticipantResponse
{
    public function __construct(
        public string $id,
        public string $pseudo,
        public string $color,
        public bool $isAgent,
        public ?string $currentDocumentId = null,
        public ?string $lastSeenAt = null,
        public ?string $createdAt = null,
    ) {}

    public static function fromEntity(Participant $participant, bool $includePresence = false): self
    {
        return new self(
            id: $participant->getId()->toString(),
            pseudo: $participant->getPseudo(),
            color: $participant->getColor(),
            isAgent: $participant->isAgent(),
            currentDocumentId: $includePresence ? $participant->getCurrentDocumentId()?->toString() : null,
            lastSeenAt: $includePresence ? $participant->getLastSeenAt()?->format(\DateTimeInterface::ATOM) : null,
            createdAt: $participant->getCreatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'pseudo' => $this->pseudo,
            'color' => $this->color,
            'is_agent' => $this->isAgent,
            'current_document_id' => $this->currentDocumentId,
            'last_seen_at' => $this->lastSeenAt,
            'created_at' => $this->createdAt,
        ], fn($value) => $value !== null);
    }
}
