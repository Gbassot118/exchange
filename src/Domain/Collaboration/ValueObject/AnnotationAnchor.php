<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\ValueObject;

final readonly class AnnotationAnchor
{
    private function __construct(
        private ?int $startOffset,
        private ?int $endOffset,
        private ?string $selectedText,
    ) {}

    public static function create(?int $startOffset, ?int $endOffset, ?string $selectedText = null): self
    {
        if ($startOffset !== null && $endOffset !== null && $startOffset > $endOffset) {
            throw new \InvalidArgumentException('Start offset cannot be greater than end offset');
        }

        return new self($startOffset, $endOffset, $selectedText);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        return self::create(
            $data['start_offset'] ?? $data['startOffset'] ?? null,
            $data['end_offset'] ?? $data['endOffset'] ?? null,
            $data['selected_text'] ?? $data['selectedText'] ?? null
        );
    }

    public function getStartOffset(): ?int
    {
        return $this->startOffset;
    }

    public function getEndOffset(): ?int
    {
        return $this->endOffset;
    }

    public function getSelectedText(): ?string
    {
        return $this->selectedText;
    }

    public function hasRange(): bool
    {
        return $this->startOffset !== null && $this->endOffset !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'start_offset' => $this->startOffset,
            'end_offset' => $this->endOffset,
            'selected_text' => $this->selectedText,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->startOffset === $other->startOffset
            && $this->endOffset === $other->endOffset
            && $this->selectedText === $other->selectedText;
    }
}
