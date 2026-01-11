<?php

declare(strict_types=1);

namespace App\Domain\Session\ValueObject;

final readonly class ParticipantColor
{
    private const AVAILABLE_COLORS = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4',
        '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F',
        '#BB8FCE', '#85C1E9', '#F8B500', '#00CED1',
    ];

    private function __construct(
        private string $value,
    ) {}

    public static function random(): self
    {
        $index = array_rand(self::AVAILABLE_COLORS);
        return new self(self::AVAILABLE_COLORS[$index]);
    }

    public static function fromString(string $color): self
    {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid color format "%s". Expected hexadecimal color (e.g., #FF6B6B)',
                $color
            ));
        }

        return new self(strtoupper($color));
    }

    public function value(): string
    {
        return $this->value;
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

    /**
     * @return array<string>
     */
    public static function availableColors(): array
    {
        return self::AVAILABLE_COLORS;
    }
}
