<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\ValueObject;

final readonly class AnnotationType
{
    public const COMMENT = 'comment';

    private const ALL_TYPES = [
        self::COMMENT,
    ];

    private function __construct(
        private string $value,
    ) {}

    public static function comment(): self
    {
        return new self(self::COMMENT);
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::ALL_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid annotation type "%s". Valid types are: %s',
                $value,
                implode(', ', self::ALL_TYPES)
            ));
        }

        return new self($value);
    }

    public function isComment(): bool
    {
        return $this->value === self::COMMENT;
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
    public static function allTypes(): array
    {
        return self::ALL_TYPES;
    }
}
