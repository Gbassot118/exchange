<?php

declare(strict_types=1);

namespace App\Domain\Decision\Repository;

use App\Domain\Decision\ValueObject\DecisionId;
use App\Entity\Decision;
use App\Entity\Session;

interface DecisionRepositoryInterface
{
    public function save(Decision $decision): void;

    public function findById(DecisionId $id): ?Decision;

    public function findByIdWithVotes(DecisionId|string $id): ?Decision;

    /**
     * @return array<Decision>
     */
    public function findBySession(Session $session): array;

    /**
     * @return array<Decision>
     */
    public function findByStatus(Session $session, string $status): array;

    /**
     * @return array<Decision>
     */
    public function findPendingBySession(Session $session): array;

    public function countPendingBySession(Session $session): int;

    public function remove(Decision $decision): void;
}
