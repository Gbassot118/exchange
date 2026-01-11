<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Exception;

final class CannotReplyToReplyException extends \DomainException
{
    public static function create(string $annotationId): self
    {
        return new self(sprintf(
            'Cannot reply to annotation "%s" because it is already a reply. Only top-level annotations can have replies.',
            $annotationId
        ));
    }
}
