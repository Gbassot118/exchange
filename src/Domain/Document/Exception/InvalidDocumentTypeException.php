<?php

declare(strict_types=1);

namespace App\Domain\Document\Exception;

final class InvalidDocumentTypeException extends \DomainException
{
    /**
     * @param array<string> $validTypes
     */
    public static function create(string $invalidType, array $validTypes): self
    {
        return new self(sprintf(
            'Invalid document type "%s". Valid types are: %s',
            $invalidType,
            implode(', ', $validTypes)
        ));
    }
}
