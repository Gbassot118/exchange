<?php

declare(strict_types=1);

namespace App\Domain\Decision\Exception;

final class DecisionLockedException extends \DomainException
{
    public static function cannotVote(string $decisionId): self
    {
        return new self(sprintf(
            'Cannot vote on decision "%s" because it is locked',
            $decisionId
        ));
    }

    public static function cannotModify(string $decisionId): self
    {
        return new self(sprintf(
            'Cannot modify decision "%s" because it is locked',
            $decisionId
        ));
    }
}
