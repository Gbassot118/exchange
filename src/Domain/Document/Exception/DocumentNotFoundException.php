<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

final class DocumentNotFoundException extends \DomainException
{
    public static function withId(string $documentId): self
    {
        return new self(sprintf('Document with ID "%s" not found', $documentId));
    }

    public static function withSlug(string $slug, string $sessionId): self
    {
        return new self(sprintf(
            'Document with slug "%s" not found in session "%s"',
            $slug,
            $sessionId
        ));
    }
}
