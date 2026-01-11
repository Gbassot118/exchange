<?php

declare(strict_types=1);

namespace App\Domain\Session\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class ParticipantId
{
    private function __construct(
        private Uuid $value,
    ) {}

    public static function generate(): self
    {
        return new self(Uuid::v7());
    }

    public static function fromString(string $id): self
    {
        return new self(Uuid::fromString($id));
    }

    public static function fromUuid(Uuid $uuid): self
    {
        return new self($uuid);
    }

    public function toUuid(): Uuid
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function value(): string
    {
        return $this->value->toString();
    }

    public function equals(self $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
