<?php

declare(strict_types=1);

namespace App\Domain\Estimation\Exception;

final class EstimationAlreadyRevealedException extends \DomainException
{
    public static function cannotVote(string $estimationId): self
    {
        return new self(sprintf('Cannot vote on estimation "%s" because it has already been revealed', $estimationId));
    }

    public static function cannotReveal(string $estimationId): self
    {
        return new self(sprintf('Estimation "%s" has already been revealed', $estimationId));
    }
}
