<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Entity\Decision;

final readonly class DecisionResponse
{
    /**
     * @param array<array{id: string, label: string, description: string|null}> $options
     * @param array<string, int> $voteStats
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,
        public string $status,
        public array $options,
        public ?string $selectedOptionId,
        public bool $isLocked,
        public array $voteStats,
        public int $voteCount,
        public ?string $linkedDocumentId,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromEntity(Decision $decision): self
    {
        return new self(
            id: $decision->getId()->toString(),
            title: $decision->getTitle(),
            description: $decision->getDescription(),
            status: $decision->getStatus(),
            options: $decision->getOptions(),
            selectedOptionId: $decision->getSelectedOptionId()?->toString(),
            isLocked: $decision->isLocked(),
            voteStats: $decision->getVoteStats(),
            voteCount: $decision->getVotes()->count(),
            linkedDocumentId: $decision->getLinkedDocument()?->getId()->toString(),
            createdAt: $decision->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $decision->getUpdatedAt()->format(\DateTimeInterface::ATOM),
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
            'options' => $this->options,
            'selected_option_id' => $this->selectedOptionId,
            'is_locked' => $this->isLocked,
            'vote_stats' => $this->voteStats,
            'vote_count' => $this->voteCount,
            'linked_document_id' => $this->linkedDocumentId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
