<?php

declare(strict_types=1);

namespace App\Domain\Estimation\ValueObject;

final readonly class EstimationStatus
{
    public const OPEN = 'open';
    public const REVEALED = 'revealed';
    public const CLOSED = 'closed';

    private const ALL_STATUSES = [
        self::OPEN,
        self::REVEALED,
        self::CLOSED,
    ];

    private function __construct(
        private string $value,
    ) {}

    public static function open(): self
    {
        return new self(self::OPEN);
    }

    public static function revealed(): self
    {
        return new self(self::REVEALED);
    }

    public static function closed(): self
    {
        return new self(self::CLOSED);
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::ALL_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid estimation status "%s". Valid statuses are: %s',
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

    public function isRevealed(): bool
    {
        return $this->value === self::REVEALED;
    }

    public function isClosed(): bool
    {
        return $this->value === self::CLOSED;
    }

    public function canVote(): bool
    {
        return $this->value === self::OPEN;
    }

    public function votesVisible(): bool
    {
        return in_array($this->value, [self::REVEALED, self::CLOSED], true);
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
