<?php

declare(strict_types=1);

namespace App\Application\Query\Session;

final readonly class GetSessionQuery
{
    public function __construct(
        public string $sessionId,
        public bool $includeParticipants = true,
    ) {}
}
