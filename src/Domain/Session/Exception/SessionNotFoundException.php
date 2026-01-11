<?php

declare(strict_types=1);

namespace App\Domain\Session\Exception;

final class SessionNotFoundException extends \DomainException
{
    public static function withId(string $sessionId): self
    {
        return new self(sprintf('Session with ID "%s" not found', $sessionId));
    }

    public static function withInviteCode(string $inviteCode): self
    {
        return new self(sprintf('Session with invite code "%s" not found', $inviteCode));
    }
}
