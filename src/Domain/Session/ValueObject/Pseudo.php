<?php

declare(strict_types=1);

namespace App\Domain\Session\ValueObject;

final readonly class Pseudo
{
    private const MIN_LENGTH = 1;
    private const MAX_LENGTH = 100;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $pseudo): self
    {
        $trimmed = trim($pseudo);

        if (strlen($trimmed) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException('Pseudo cannot be empty');
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Pseudo cannot exceed %d characters',
                self::MAX_LENGTH
            ));
        }

        return new self($trimmed);
    }

    public function toString(): string
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
}
