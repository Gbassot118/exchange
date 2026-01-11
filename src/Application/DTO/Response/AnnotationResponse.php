<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Entity\Annotation;

final class AnnotationResponse
{
    /**
     * @param array<string>|null $mentions
     * @param array<AnnotationResponse>|null $replies
     */
    public function __construct(
        public readonly string $id,
        public readonly string $content,
        public readonly string $type,
        public readonly string $status,
        public readonly ?array $anchor,
        public readonly ParticipantResponse $author,
        public readonly string $documentId,
        public readonly ?string $parentId,
        public readonly ?array $mentions,
        public readonly bool $takenIntoAccount,
        public readonly ?string $resolvedBy,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly int $replyCount,
        public ?array $replies = null,
    ) {}

    public static function fromEntity(Annotation $annotation, bool $includeReplies = false): self
    {
        $replies = null;
        if ($includeReplies && !$annotation->isReply()) {
            $replies = array_map(
                fn(Annotation $r) => self::fromEntity($r, false),
                $annotation->getReplies()->toArray()
            );
        }

        return new self(
            id: $annotation->getId()->toString(),
            content: $annotation->getContent(),
            type: $annotation->getType(),
            status: $annotation->getStatus(),
            anchor: $annotation->getAnchor(),
            author: ParticipantResponse::fromEntity($annotation->getAuthor()),
            documentId: $annotation->getDocument()->getId()->toString(),
            parentId: $annotation->getParentAnnotation()?->getId()->toString(),
            mentions: $annotation->getMentions(),
            takenIntoAccount: $annotation->isTakenIntoAccount(),
            resolvedBy: $annotation->getResolvedBy()?->getPseudo(),
            createdAt: $annotation->getCreatedAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $annotation->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            replyCount: $annotation->getReplies()->count(),
            replies: $replies,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'content' => $this->content,
            'type' => $this->type,
            'status' => $this->status,
            'anchor' => $this->anchor,
            'author' => $this->author->toArray(),
            'document_id' => $this->documentId,
            'parent_id' => $this->parentId,
            'mentions' => $this->mentions,
            'taken_into_account' => $this->takenIntoAccount,
            'resolved_by' => $this->resolvedBy,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'reply_count' => $this->replyCount,
            'replies' => $this->replies !== null
                ? array_map(fn($r) => $r->toArray(), $this->replies)
                : null,
        ], fn($value) => $value !== null);
    }
}
