<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\ValueObject;

final readonly class MentionList
{
    /**
     * @param array<string> $participantIds
     */
    private function __construct(
        private array $participantIds,
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<string> $participantIds
     */
    public static function fromArray(array $participantIds): self
    {
        // Validate and deduplicate
        $validIds = [];
        foreach ($participantIds as $id) {
            if (is_string($id) && !empty($id)) {
                $validIds[] = $id;
            }
        }

        return new self(array_unique($validIds));
    }

    public static function extractFromContent(string $content): self
    {
        preg_match_all('/@(\w+)/', $content, $matches);

        return new self($matches[1] ?? []);
    }

    public function isEmpty(): bool
    {
        return empty($this->participantIds);
    }

    public function count(): int
    {
        return count($this->participantIds);
    }

    public function contains(string $participantId): bool
    {
        return in_array($participantId, $this->participantIds, true);
    }

    public function add(string $participantId): self
    {
        if ($this->contains($participantId)) {
            return $this;
        }

        return new self([...$this->participantIds, $participantId]);
    }

    /**
     * @return array<string>
     */
    public function toArray(): array
    {
        return $this->participantIds;
    }

    public function equals(self $other): bool
    {
        $thisIds = $this->participantIds;
        $otherIds = $other->participantIds;

        sort($thisIds);
        sort($otherIds);

        return $thisIds === $otherIds;
    }
}
