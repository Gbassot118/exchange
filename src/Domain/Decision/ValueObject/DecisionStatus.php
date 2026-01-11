<?php

declare(strict_types=1);

namespace App\Domain\Decision\ValueObject;

final readonly class DecisionStatus
{
    public const OUVERT = 'ouvert';
    public const EN_DISCUSSION = 'en_discussion';
    public const CONSENSUS = 'consensus';
    public const VALIDE = 'valide';
    public const REPORTE = 'reporte';

    private const ALL_STATUSES = [
        self::OUVERT,
        self::EN_DISCUSSION,
        self::CONSENSUS,
        self::VALIDE,
        self::REPORTE,
    ];

    private function __construct(
        private string $value,
    ) {}

    public static function ouvert(): self
    {
        return new self(self::OUVERT);
    }

    public static function enDiscussion(): self
    {
        return new self(self::EN_DISCUSSION);
    }

    public static function consensus(): self
    {
        return new self(self::CONSENSUS);
    }

    public static function valide(): self
    {
        return new self(self::VALIDE);
    }

    public static function reporte(): self
    {
        return new self(self::REPORTE);
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::ALL_STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid decision status "%s". Valid statuses are: %s',
                $value,
                implode(', ', self::ALL_STATUSES)
            ));
        }

        return new self($value);
    }

    public function isOuvert(): bool
    {
        return $this->value === self::OUVERT;
    }

    public function isEnDiscussion(): bool
    {
        return $this->value === self::EN_DISCUSSION;
    }

    public function isConsensus(): bool
    {
        return $this->value === self::CONSENSUS;
    }

    public function isValide(): bool
    {
        return $this->value === self::VALIDE;
    }

    public function isReporte(): bool
    {
        return $this->value === self::REPORTE;
    }

    public function isPending(): bool
    {
        return in_array($this->value, [self::OUVERT, self::EN_DISCUSSION], true);
    }

    public function isFinal(): bool
    {
        return in_array($this->value, [self::VALIDE, self::REPORTE], true);
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
