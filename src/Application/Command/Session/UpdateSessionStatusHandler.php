<?php

declare(strict_types=1);

namespace App\Application\Command\Session;

use App\Application\DTO\Response\SessionResponse;
use App\Application\DTO\Transformer\SessionTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Session\Event\SessionStatusChanged;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;
use App\Domain\Session\ValueObject\SessionStatus;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateSessionStatusHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private SessionTransformer $sessionTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(UpdateSessionStatusCommand $command): SessionResponse
    {
        $sessionId = SessionId::fromString($command->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($command->sessionId);
        }

        $currentStatus = SessionStatus::fromString($session->getStatus());
        $currentStatus->validateTransitionTo($command->newStatus);

        $session->setStatus($command->newStatus->toString());
        $this->sessionRepository->save($session);

        $event = new SessionStatusChanged(
            sessionId: $command->sessionId,
            previousStatus: $currentStatus->toString(),
            newStatus: $command->newStatus->toString(),
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifySessionStatusChanged(
            sessionId: $command->sessionId,
            newStatus: $command->newStatus->toString(),
        );

        return $this->sessionTransformer->toResponse($session, includeParticipants: false);
    }
}
