<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Exception;

final class AnnotationNotFoundException extends \DomainException
{
    public static function withId(string $annotationId): self
    {
        return new self(sprintf('Annotation with ID "%s" not found', $annotationId));
    }
}
