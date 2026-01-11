<?php

declare(strict_types=1);

namespace App\Domain\Estimation\Repository;

use App\Domain\Estimation\ValueObject\EstimationId;
use App\Entity\Document;
use App\Entity\Estimation;
use App\Entity\Session;

interface EstimationRepositoryInterface
{
    public function save(Estimation $estimation): void;

    public function findById(EstimationId $id): ?Estimation;

    public function findByIdWithVotes(EstimationId|string $id): ?Estimation;

    /**
     * @return array<Estimation>
     */
    public function findBySession(Session $session): array;

    /**
     * @return array<Estimation>
     */
    public function findByDocument(Document $document): array;

    /**
     * @return array<Estimation>
     */
    public function findByStatus(Session $session, string $status): array;

    /**
     * @return array<Estimation>
     */
    public function findOpenBySession(Session $session): array;

    public function countOpenBySession(Session $session): int;

    public function remove(Estimation $estimation): void;
}
