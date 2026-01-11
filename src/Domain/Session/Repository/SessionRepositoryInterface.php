<?php

declare(strict_types=1);

namespace App\Domain\Session\Repository;

use App\Domain\Session\ValueObject\SessionId;
use App\Entity\Session;

interface SessionRepositoryInterface
{
    public function save(Session $session): void;

    public function findById(SessionId $id): ?Session;

    public function findByInviteCode(string $inviteCode): ?Session;

    /**
     * @return array<Session>
     */
    public function findAllSessions(int $limit = 50): array;

    /**
     * @return array<Session>
     */
    public function findActive(): array;

    public function remove(Session $session): void;
}
