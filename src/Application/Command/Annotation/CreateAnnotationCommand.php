<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

final readonly class CreateAnnotationCommand
{
    /**
     * @param array<string, mixed>|null $anchor
     */
    public function __construct(
        public string $documentId,
        public string $authorParticipantId,
        public string $content,
        public string $type = 'comment',
        public ?array $anchor = null,
        public ?string $parentId = null,
    ) {}

    public function isReply(): bool
    {
        return $this->parentId !== null;
    }
}
