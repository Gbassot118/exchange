<?php

declare(strict_types=1);

namespace App\Domain\Session\Exception;

final class InviteCodeExpiredException extends \DomainException
{
    public static function create(string $sessionId): self
    {
        return new self(sprintf(
            'Le code d\'invitation pour la session "%s" a expiré. Contactez l\'organisateur pour obtenir un nouveau code.',
            $sessionId
        ));
    }
}
