<?php

declare(strict_types=1);

namespace App\Domain\Document\ValueObject;

final readonly class DocumentSlug
{
    private const MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $slug): self
    {
        $normalized = self::normalize($slug);

        if (empty($normalized)) {
            throw new \InvalidArgumentException('Document slug cannot be empty');
        }

        if (strlen($normalized) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Document slug cannot exceed %d characters',
                self::MAX_LENGTH
            ));
        }

        return new self($normalized);
    }

    public static function fromTitle(string $title): self
    {
        $slug = self::slugify($title);

        if (empty($slug)) {
            $slug = 'document-' . bin2hex(random_bytes(4));
        }

        return new self($slug);
    }

    private static function slugify(string $text): string
    {
        // Transliterate non-ASCII characters
        $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);

        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-z0-9]+/', '-', strtolower($text));

        // Remove leading/trailing hyphens
        $text = trim($text, '-');

        // Limit length
        if (strlen($text) > self::MAX_LENGTH) {
            $text = substr($text, 0, self::MAX_LENGTH);
            $text = rtrim($text, '-');
        }

        return $text;
    }

    private static function normalize(string $slug): string
    {
        // Ensure slug is lowercase and only contains valid characters
        return preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
    }

    public function withSuffix(string $suffix): self
    {
        $newSlug = $this->value . '-' . $suffix;

        if (strlen($newSlug) > self::MAX_LENGTH) {
            $newSlug = substr($newSlug, 0, self::MAX_LENGTH);
        }

        return new self($newSlug);
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
