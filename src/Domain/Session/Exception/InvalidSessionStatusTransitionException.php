<?php

declare(strict_types=1);

namespace App\Domain\Session\Exception;

use App\Domain\Session\ValueObject\SessionStatus;

final class InvalidSessionStatusTransitionException extends \DomainException
{
    public static function create(SessionStatus $from, SessionStatus $to): self
    {
        return new self(sprintf(
            'Cannot transition session status from "%s" to "%s"',
            $from->toString(),
            $to->toString()
        ));
    }
}
