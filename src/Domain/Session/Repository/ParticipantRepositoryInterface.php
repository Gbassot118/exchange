<?php

declare(strict_types=1);

namespace App\Domain\Session\Repository;

use App\Domain\Session\ValueObject\ParticipantId;
use App\Entity\Participant;
use App\Entity\Session;

interface ParticipantRepositoryInterface
{
    public function save(Participant $participant): void;

    public function findById(ParticipantId $id): ?Participant;

    public function findBySessionAndPseudo(Session $session, string $pseudo): ?Participant;

    /**
     * @return array<Participant>
     */
    public function findBySession(Session $session): array;

    /**
     * @return array<Participant>
     */
    public function findOnlineInSession(Session $session, \DateTimeImmutable $threshold): array;

    public function remove(Participant $participant): void;
}
