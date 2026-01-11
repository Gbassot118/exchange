<?php

declare(strict_types=1);

namespace App\Domain\Session\ValueObject;

final readonly class InviteCode
{
    private const CODE_LENGTH = 32;

    private function __construct(
        private string $value,
    ) {}

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)));
    }

    public static function fromString(string $code): self
    {
        if (strlen($code) !== self::CODE_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Invite code must be exactly %d characters, got %d',
                self::CODE_LENGTH,
                strlen($code)
            ));
        }

        if (!ctype_xdigit($code)) {
            throw new \InvalidArgumentException('Invite code must be a valid hexadecimal string');
        }

        return new self($code);
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
}
