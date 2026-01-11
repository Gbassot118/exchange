<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Entity\Session;

final readonly class SessionResponse
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $status,
        public string $inviteCode,
        public string $createdAt,
        public string $updatedAt,
        public ?int $documentCount = null,
        public ?int $participantCount = null,
        public ?int $decisionCount = null,
    ) {}

    public static function fromEntity(Session $session, bool $includeStats = false): self
    {
        return new self(
            id: $session->getId()->toString(),
            title: $session->getTitle(),
            description: $session->getDescription(),
            status: $session->getStatus(),
            inviteCode: $session->getInviteCode(),
            createdAt: $session->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $session->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            documentCount: $includeStats ? $session->getDocuments()->count() : null,
            participantCount: $includeStats ? $session->getParticipants()->count() : null,
            decisionCount: $includeStats ? $session->getDecisions()->count() : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'invite_code' => $this->inviteCode,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'document_count' => $this->documentCount,
            'participant_count' => $this->participantCount,
            'decision_count' => $this->decisionCount,
        ], fn($value) => $value !== null);
    }
}
