<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Entity\Estimation;

final readonly class EstimationResponse
{
    /**
     * @param array<string> $voterParticipantIds
     * @param array<EstimationVoteResponse>|null $votes
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $status,
        public bool $isRevealed,
        public array $voterParticipantIds,
        public int $voteCount,
        public ?array $votes,
        public ?float $average,
        public ?string $linkedDocumentId,
        public ?string $revealedAt,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromEntity(Estimation $estimation, bool $includeVotes = true): self
    {
        $isRevealed = $estimation->isRevealed();
        $votes = null;
        $average = null;

        if ($isRevealed && $includeVotes) {
            $votes = [];
            foreach ($estimation->getVotes() as $vote) {
                $votes[] = EstimationVoteResponse::fromEntity($vote);
            }
            $average = $estimation->calculateAverage();
        }

        return new self(
            id: $estimation->getId()->toString(),
            title: $estimation->getTitle(),
            description: $estimation->getDescription(),
            status: $estimation->getStatus(),
            isRevealed: $isRevealed,
            voterParticipantIds: $estimation->getVoterParticipantIds(),
            voteCount: $estimation->getVotes()->count(),
            votes: $votes,
            average: $average,
            linkedDocumentId: $estimation->getLinkedDocument()?->getId()->toString(),
            revealedAt: $estimation->getRevealedAt()?->format(\DateTimeInterface::ATOM),
            createdAt: $estimation->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $estimation->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'is_revealed' => $this->isRevealed,
            'voter_participant_ids' => $this->voterParticipantIds,
            'vote_count' => $this->voteCount,
            'votes' => $this->votes !== null ? array_map(fn($v) => $v->toArray(), $this->votes) : null,
            'average' => $this->average,
            'linked_document_id' => $this->linkedDocumentId,
            'revealed_at' => $this->revealedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
