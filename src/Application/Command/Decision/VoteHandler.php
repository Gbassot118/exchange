<?php

declare(strict_types=1);

namespace App\Application\Command\Decision;

use App\Application\DTO\Response\DecisionResponse;
use App\Application\DTO\Transformer\DecisionTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Decision\Event\VoteCast;
use App\Domain\Decision\Exception\DecisionLockedException;
use App\Domain\Decision\Exception\DecisionNotFoundException;
use App\Domain\Decision\Exception\InvalidOptionException;
use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Decision\Repository\VoteRepositoryInterface;
use App\Domain\Decision\ValueObject\DecisionId;
use App\Domain\Decision\ValueObject\OptionId;
use App\Domain\Session\Exception\ParticipantNotFoundException;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\ValueObject\ParticipantId;
use App\Entity\Vote;
use Symfony\Component\Uid\Uuid;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VoteHandler
{
    public function __construct(
        private DecisionRepositoryInterface $decisionRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private VoteRepositoryInterface $voteRepository,
        private DecisionTransformer $decisionTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(VoteCommand $command): DecisionResponse
    {
        $decisionId = DecisionId::fromString($command->decisionId);
        $decision = $this->decisionRepository->findById($decisionId);

        if ($decision === null) {
            throw DecisionNotFoundException::withId($command->decisionId);
        }

        if ($decision->isLocked()) {
            throw DecisionLockedException::create($command->decisionId);
        }

        $participantId = ParticipantId::fromString($command->participantId);
        $participant = $this->participantRepository->findById($participantId);

        if ($participant === null) {
            throw ParticipantNotFoundException::withId($command->participantId);
        }

        $optionId = OptionId::fromString($command->optionId);
        if (!$decision->hasOption($optionId->value())) {
            throw InvalidOptionException::withId($command->optionId, $command->decisionId);
        }

        $existingVote = $this->voteRepository->findByDecisionAndParticipant($decision, $participant);

        if ($existingVote !== null) {
            $existingVote->setOptionId(Uuid::fromString($optionId->value()));
            if ($command->comment !== null) {
                $existingVote->setComment($command->comment);
            }
            $this->voteRepository->save($existingVote);
        } else {
            $vote = new Vote();
            $vote->setDecision($decision);
            $vote->setParticipant($participant);
            $vote->setOptionId(Uuid::fromString($optionId->value()));
            if ($command->comment !== null) {
                $vote->setComment($command->comment);
            }
            $this->voteRepository->save($vote);
        }

        $sessionId = $decision->getSession()->getId()->toString();

        $event = new VoteCast(
            decisionId: $command->decisionId,
            sessionId: $sessionId,
            participantPseudo: $participant->getPseudo(),
            optionId: $command->optionId,
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyVoteCast(
            sessionId: $sessionId,
            decision: $this->decisionTransformer->toResponse($decision),
        );

        return $this->decisionTransformer->toResponse($decision);
    }
}
