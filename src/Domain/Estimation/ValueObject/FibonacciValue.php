<?php

declare(strict_types=1);

namespace App\Domain\Estimation\ValueObject;

final readonly class FibonacciValue
{
    public const VALUES = ['0', '1', '2', '3', '5', '8', '13', '21', '?'];

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid Fibonacci value "%s". Valid values are: %s',
                $value,
                implode(', ', self::VALUES)
            ));
        }

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::VALUES, true);
    }

    public function isUncertain(): bool
    {
        return $this->value === '?';
    }

    public function isNumeric(): bool
    {
        return $this->value !== '?';
    }

    public function toInt(): ?int
    {
        return $this->isNumeric() ? (int) $this->value : null;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @return array<string>
     */
    public static function allValues(): array
    {
        return self::VALUES;
    }
}
