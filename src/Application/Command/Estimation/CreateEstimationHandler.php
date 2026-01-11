<?php

declare(strict_types=1);

namespace App\Application\Command\Estimation;

use App\Application\DTO\Response\EstimationResponse;
use App\Application\DTO\Transformer\EstimationTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Estimation\Event\EstimationCreated;
use App\Domain\Estimation\Repository\EstimationRepositoryInterface;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;
use App\Entity\Estimation;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateEstimationHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private DocumentRepositoryInterface $documentRepository,
        private EstimationRepositoryInterface $estimationRepository,
        private EstimationTransformer $estimationTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(CreateEstimationCommand $command): EstimationResponse
    {
        $sessionId = SessionId::fromString($command->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($command->sessionId);
        }

        $linkedDocument = null;
        if ($command->linkedDocumentId !== null) {
            $documentId = DocumentId::fromString($command->linkedDocumentId);
            $linkedDocument = $this->documentRepository->findById($documentId);

            if ($linkedDocument === null) {
                throw DocumentNotFoundException::withId($command->linkedDocumentId);
            }
        }

        $estimation = new Estimation();
        $estimation->setSession($session);
        $estimation->setTitle($command->title);
        if ($command->description !== null) {
            $estimation->setDescription($command->description);
        }

        if ($linkedDocument !== null) {
            $estimation->setLinkedDocument($linkedDocument);
        }

        $this->estimationRepository->save($estimation);

        $event = new EstimationCreated(
            estimationId: $estimation->getId()->toString(),
            sessionId: $command->sessionId,
            title: $estimation->getTitle(),
            linkedDocumentId: $linkedDocument?->getId()->toString(),
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyEstimationCreated(
            sessionId: $command->sessionId,
            estimation: $this->estimationTransformer->toResponse($estimation),
        );

        return $this->estimationTransformer->toResponse($estimation);
    }
}
