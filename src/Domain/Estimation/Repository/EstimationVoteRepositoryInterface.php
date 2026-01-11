<?php

declare(strict_types=1);

namespace App\Domain\Estimation\Repository;

use App\Domain\Estimation\ValueObject\EstimationId;
use App\Entity\Estimation;
use App\Entity\EstimationVote;
use App\Entity\Participant;

interface EstimationVoteRepositoryInterface
{
    public function save(EstimationVote $vote): void;

    public function findByEstimationAndParticipant(Estimation $estimation, Participant $participant): ?EstimationVote;

    /**
     * @return array<EstimationVote>
     */
    public function findByEstimation(EstimationId $estimationId): array;

    public function removeByEstimationAndParticipant(Estimation $estimation, Participant $participant): void;

    public function remove(EstimationVote $vote): void;
}
