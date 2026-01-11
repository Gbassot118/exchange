<?php

declare(strict_types=1);

namespace App\Domain\Decision\Exception;

final class DecisionNotFoundException extends \DomainException
{
    public static function withId(string $decisionId): self
    {
        return new self(sprintf('Decision with ID "%s" not found', $decisionId));
    }
}
