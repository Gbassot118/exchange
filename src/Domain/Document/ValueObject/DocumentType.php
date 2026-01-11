<?php

declare(strict_types=1);

namespace App\Domain\Document\ValueObject;

use App\Domain\Document\Exception\InvalidDocumentTypeException;

final readonly class DocumentType
{
    public const SYNTHESIS = 'synthesis';
    public const QUESTION = 'question';
    public const COMPARISON = 'comparison';
    public const ANNEXE = 'annexe';
    public const COMPTE_RENDU = 'compte_rendu';
    public const GENERAL = 'general';

    private const ALL_TYPES = [
        self::SYNTHESIS,
        self::QUESTION,
        self::COMPARISON,
        self::ANNEXE,
        self::COMPTE_RENDU,
        self::GENERAL,
    ];

    private function __construct(
        private string $value,
    ) {}

    public static function synthesis(): self
    {
        return new self(self::SYNTHESIS);
    }

    public static function question(): self
    {
        return new self(self::QUESTION);
    }

    public static function comparison(): self
    {
        return new self(self::COMPARISON);
    }

    public static function annexe(): self
    {
        return new self(self::ANNEXE);
    }

    public static function compteRendu(): self
    {
        return new self(self::COMPTE_RENDU);
    }

    public static function general(): self
    {
        return new self(self::GENERAL);
    }

    public static function fromString(string $value): self
    {
        if (!in_array($value, self::ALL_TYPES, true)) {
            throw InvalidDocumentTypeException::create($value, self::ALL_TYPES);
        }

        return new self($value);
    }

    public function isSynthesis(): bool
    {
        return $this->value === self::SYNTHESIS;
    }

    public function isQuestion(): bool
    {
        return $this->value === self::QUESTION;
    }

    public function isComparison(): bool
    {
        return $this->value === self::COMPARISON;
    }

    public function isAnnexe(): bool
    {
        return $this->value === self::ANNEXE;
    }

    public function isCompteRendu(): bool
    {
        return $this->value === self::COMPTE_RENDU;
    }

    public function isGeneral(): bool
    {
        return $this->value === self::GENERAL;
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
