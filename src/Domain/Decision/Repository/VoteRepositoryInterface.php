<?php

declare(strict_types=1);

namespace App\Domain\Decision\Repository;

use App\Domain\Decision\ValueObject\DecisionId;
use App\Domain\Session\ValueObject\ParticipantId;
use App\Entity\Decision;
use App\Entity\Participant;
use App\Entity\Vote;

interface VoteRepositoryInterface
{
    public function save(Vote $vote): void;

    public function findByDecisionAndParticipant(Decision $decision, Participant $participant): ?Vote;

    /**
     * @return array<Vote>
     */
    public function findByDecision(DecisionId $decisionId): array;

    public function removeByDecisionAndParticipant(Decision $decision, Participant $participant): void;

    public function remove(Vote $vote): void;
}
