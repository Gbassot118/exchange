<?php

declare(strict_types=1);

namespace App\Application\Command\Decision;

use App\Application\DTO\Response\DecisionResponse;
use App\Application\DTO\Transformer\DecisionTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Decision\Event\DecisionCreated;
use App\Domain\Decision\Repository\DecisionRepositoryInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;
use App\Entity\Decision;
use Symfony\Component\Uid\Uuid;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateDecisionHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private DocumentRepositoryInterface $documentRepository,
        private DecisionRepositoryInterface $decisionRepository,
        private DecisionTransformer $decisionTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(CreateDecisionCommand $command): DecisionResponse
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

        $decision = new Decision();
        $decision->setSession($session);
        $decision->setTitle($command->title);
        if ($command->description !== null) {
            $decision->setDescription($command->description);
        }

        // Generate IDs for options
        $optionsWithIds = array_map(
            fn(array $option) => [
                'id' => Uuid::v7()->toString(),
                'label' => $option['label'],
                'description' => $option['description'] ?? null,
            ],
            $command->options
        );
        $decision->setOptions($optionsWithIds);

        if ($linkedDocument !== null) {
            $decision->setLinkedDocument($linkedDocument);
        }

        $this->decisionRepository->save($decision);

        $event = new DecisionCreated(
            decisionId: $decision->getId()->toString(),
            sessionId: $command->sessionId,
            title: $decision->getTitle(),
            optionCount: count($command->options),
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyDecisionCreated(
            sessionId: $command->sessionId,
            decision: $this->decisionTransformer->toResponse($decision),
        );

        return $this->decisionTransformer->toResponse($decision);
    }
}
