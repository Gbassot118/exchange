<?php

declare(strict_types=1);

namespace App\Domain\Session\Exception;

final class SessionArchivedException extends \DomainException
{
    public static function cannotJoin(string $sessionId): self
    {
        return new self(sprintf('Cannot join archived session "%s"', $sessionId));
    }

    public static function cannotModify(string $sessionId): self
    {
        return new self(sprintf('Cannot modify archived session "%s"', $sessionId));
    }
}
