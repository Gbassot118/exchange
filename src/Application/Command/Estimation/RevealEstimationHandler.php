<?php

declare(strict_types=1);

namespace App\Application\Command\Estimation;

use App\Application\DTO\Response\EstimationResponse;
use App\Application\DTO\Transformer\EstimationTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Estimation\Event\EstimationRevealed;
use App\Domain\Estimation\Exception\EstimationAlreadyRevealedException;
use App\Domain\Estimation\Exception\EstimationNotFoundException;
use App\Domain\Estimation\Repository\EstimationRepositoryInterface;
use App\Domain\Estimation\ValueObject\EstimationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevealEstimationHandler
{
    public function __construct(
        private EstimationRepositoryInterface $estimationRepository,
        private EstimationTransformer $estimationTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(RevealEstimationCommand $command): EstimationResponse
    {
        $estimationId = EstimationId::fromString($command->estimationId);
        $estimation = $this->estimationRepository->findByIdWithVotes($estimationId);

        if ($estimation === null) {
            throw EstimationNotFoundException::withId($command->estimationId);
        }

        if (!$estimation->isOpen()) {
            throw EstimationAlreadyRevealedException::cannotReveal($command->estimationId);
        }

        $estimation->reveal();
        $this->estimationRepository->save($estimation);

        $sessionId = $estimation->getSession()->getId()->toString();

        $event = new EstimationRevealed(
            estimationId: $command->estimationId,
            sessionId: $sessionId,
            average: $estimation->calculateAverage(),
            voteCount: $estimation->getVotes()->count(),
        );

        $this->eventPublisher->publish($event);

        $response = $this->estimationTransformer->toResponse($estimation);

        $this->realtimeNotifier->notifyEstimationRevealed(
            sessionId: $sessionId,
            estimation: $response,
        );

        return $response;
    }
}
