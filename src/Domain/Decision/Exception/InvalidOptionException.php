<?php

declare(strict_types=1);

namespace App\Domain\Decision\Exception;

final class InvalidOptionException extends \DomainException
{
    public static function notFound(string $optionId, string $decisionId): self
    {
        return new self(sprintf(
            'Option "%s" not found in decision "%s"',
            $optionId,
            $decisionId
        ));
    }
}
