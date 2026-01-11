<?php

declare(strict_types=1);

namespace App\Application\Command\Estimation;

use App\Application\DTO\Response\EstimationResponse;
use App\Application\DTO\Transformer\EstimationTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Estimation\Event\EstimationVoteCast;
use App\Domain\Estimation\Exception\EstimationAlreadyRevealedException;
use App\Domain\Estimation\Exception\EstimationNotFoundException;
use App\Domain\Estimation\Exception\InvalidFibonacciValueException;
use App\Domain\Estimation\Repository\EstimationRepositoryInterface;
use App\Domain\Estimation\Repository\EstimationVoteRepositoryInterface;
use App\Domain\Estimation\ValueObject\EstimationId;
use App\Domain\Estimation\ValueObject\FibonacciValue;
use App\Domain\Session\Exception\ParticipantNotFoundException;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\ValueObject\ParticipantId;
use App\Entity\EstimationVote;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VoteEstimationHandler
{
    public function __construct(
        private EstimationRepositoryInterface $estimationRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private EstimationVoteRepositoryInterface $voteRepository,
        private EstimationTransformer $estimationTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(VoteEstimationCommand $command): EstimationResponse
    {
        $estimationId = EstimationId::fromString($command->estimationId);
        $estimation = $this->estimationRepository->findById($estimationId);

        if ($estimation === null) {
            throw EstimationNotFoundException::withId($command->estimationId);
        }

        if (!$estimation->isOpen()) {
            throw EstimationAlreadyRevealedException::cannotVote($command->estimationId);
        }

        $participantId = ParticipantId::fromString($command->participantId);
        $participant = $this->participantRepository->findById($participantId);

        if ($participant === null) {
            throw ParticipantNotFoundException::withId($command->participantId);
        }

        if (!FibonacciValue::isValid($command->value)) {
            throw InvalidFibonacciValueException::withValue($command->value);
        }

        $existingVote = $this->voteRepository->findByEstimationAndParticipant($estimation, $participant);

        if ($existingVote !== null) {
            $existingVote->setValue($command->value);
            $this->voteRepository->save($existingVote);
        } else {
            $vote = new EstimationVote();
            $vote->setEstimation($estimation);
            $vote->setParticipant($participant);
            $vote->setValue($command->value);
            $this->voteRepository->save($vote);
        }

        $sessionId = $estimation->getSession()->getId()->toString();

        $event = new EstimationVoteCast(
            estimationId: $command->estimationId,
            sessionId: $sessionId,
            participantId: $command->participantId,
            participantPseudo: $participant->getPseudo(),
            voteCount: $estimation->getVotes()->count(),
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyEstimationVoted(
            sessionId: $sessionId,
            estimationId: $command->estimationId,
            participantId: $command->participantId,
            voteCount: $estimation->getVotes()->count(),
        );

        return $this->estimationTransformer->toResponse($estimation);
    }
}
