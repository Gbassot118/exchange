<?php

declare(strict_types=1);

namespace App\Domain\Session\ValueObject;

use App\Domain\Session\Exception\InvalidSessionStatusTransitionException;

final readonly class SessionStatus
{
    public const PREPARATION = 'preparation';
    public const EN_COURS = 'en_cours';
    public const TERMINE = 'termine';
    public const ARCHIVE = 'archive';

    private const VALID_TRANSITIONS = [
        self::PREPARATION => [self::EN_COURS],
        self::EN_COURS => [self::TERMINE, self::PREPARATION],
        self::TERMINE => [self::ARCHIVE, self::EN_COURS],
        self::ARCHIVE => [],
    ];

    private const ALL_STATUSES = [
        self::PREPARATION,
        self::EN_COURS,
        self::TERMINE,
        self::ARCHIVE,
    ];

    private function __construct(
        private string $value,
    ) {}

    public static function preparation(): self
    {
        return new self(self::PREPARATION);
    }

    public static function enCours(): self
    {
        return new self(self::EN_COURS);
    }

    public static function termine(): self
    {
        return new self(self::TERMINE);
    }

    public static function archive(): self
    {
        return new self(self::ARCHIVE);
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::ALL_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid session status "%s". Valid values are: %s',
                $value,
                implode(', ', self::ALL_STATUSES)
            ));
        }

        return new self($value);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        if ($this->equals($newStatus)) {
            return true;
        }

        $allowedTransitions = self::VALID_TRANSITIONS[$this->value] ?? [];

        return in_array($newStatus->value, $allowedTransitions, true);
    }

    public function validateTransitionTo(self $newStatus): void
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw InvalidSessionStatusTransitionException::create($this, $newStatus);
        }
    }

    public function isPreparation(): bool
    {
        return $this->value === self::PREPARATION;
    }

    public function isEnCours(): bool
    {
        return $this->value === self::EN_COURS;
    }

    public function isTermine(): bool
    {
        return $this->value === self::TERMINE;
    }

    public function isArchive(): bool
    {
        return $this->value === self::ARCHIVE;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function value(): string
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
