<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\ValueObject;

final readonly class AnnotationStatus
{
    public const OPEN = 'open';
    public const IN_PROGRESS = 'in_progress';
    public const RESOLVED = 'resolved';

    private const ALL_STATUSES = [
        self::OPEN,
        self::IN_PROGRESS,
        self::RESOLVED,
    ];

    private function __construct(
        private string $value,
    ) {}

    public static function open(): self
    {
        return new self(self::OPEN);
    }

    public static function inProgress(): self
    {
        return new self(self::IN_PROGRESS);
    }

    public static function resolved(): self
    {
        return new self(self::RESOLVED);
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::ALL_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid annotation status "%s". Valid statuses are: %s',
                $value,
                implode(', ', self::ALL_STATUSES)
            ));
        }

        return new self($value);
    }

    public function isOpen(): bool
    {
        return $this->value === self::OPEN;
    }

    public function isInProgress(): bool
    {
        return $this->value === self::IN_PROGRESS;
    }

    public function isResolved(): bool
    {
        return $this->value === self::RESOLVED;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array<string>
     */
    public static function allStatuses(): array
    {
        return self::ALL_STATUSES;
    }
}
