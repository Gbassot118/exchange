<?php

declare(strict_types=1);

namespace App\Domain\Session\Event;

final readonly class ParticipantJoined
{
    public function __construct(
        public string $sessionId,
        public string $participantId,
        public string $pseudo,
        public bool $isAgent,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {}
}
