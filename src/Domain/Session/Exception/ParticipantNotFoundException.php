<?php

declare(strict_types=1);

namespace App\Domain\Session\Exception;

final class ParticipantNotFoundException extends \DomainException
{
    public static function withId(string $participantId): self
    {
        return new self(sprintf('Participant with ID "%s" not found', $participantId));
    }

    public static function withPseudo(string $pseudo, string $sessionId): self
    {
        return new self(sprintf(
            'Participant with pseudo "%s" not found in session "%s"',
            $pseudo,
            $sessionId
        ));
    }
}
