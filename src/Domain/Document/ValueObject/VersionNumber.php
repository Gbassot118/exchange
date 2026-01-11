<?php

declare(strict_types=1);

namespace App\Domain\Document\ValueObject;

final readonly class VersionNumber
{
    private function __construct(
        private int $value,
    ) {}

    public static function first(): self
    {
        return new self(1);
    }

    public static function fromInt(int $version): self
    {
        if ($version < 1) {
            throw new \InvalidArgumentException('Version number must be at least 1');
        }

        return new self($version);
    }

    public function increment(): self
    {
        return new self($this->value + 1);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
