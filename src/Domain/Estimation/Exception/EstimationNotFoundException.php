<?php

declare(strict_types=1);

namespace App\Domain\Estimation\Exception;

final class EstimationNotFoundException extends \DomainException
{
    public static function withId(string $estimationId): self
    {
        return new self(sprintf('Estimation with ID "%s" not found', $estimationId));
    }
}
