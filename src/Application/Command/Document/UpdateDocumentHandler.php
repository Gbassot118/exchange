<?php

declare(strict_types=1);

namespace App\Application\Command\Document;

use App\Application\DTO\Response\DocumentResponse;
use App\Application\DTO\Transformer\DocumentTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Document\Event\DocumentUpdated;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\ValueObject\ParticipantId;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateDocumentHandler
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private DocumentTransformer $documentTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(UpdateDocumentCommand $command): DocumentResponse
    {
        $documentId = DocumentId::fromString($command->documentId);
        $document = $this->documentRepository->findById($documentId);

        if ($document === null) {
            throw DocumentNotFoundException::withId($command->documentId);
        }

        $hasContentChanges = false;

        if ($command->title !== null) {
            $document->setTitle($command->title);
        }

        if ($command->content !== null || $command->metadata !== null) {
            $author = null;
            if ($command->authorParticipantId !== null) {
                $participantId = ParticipantId::fromString($command->authorParticipantId);
                $author = $this->participantRepository->findById($participantId);
            }

            $document->updateContent(
                content: $command->content ?? $document->getContent(),
                metadata: $command->metadata ?? $document->getMetadata(),
                author: $author,
                changeDescription: $command->changeDescription,
            );
            $hasContentChanges = true;
        }

        if ($command->parentId !== null) {
            $parent = $this->documentRepository->findById(DocumentId::fromString($command->parentId));
            if ($parent !== null) {
                $document->setParent($parent);
            }
        }

        if ($command->sortOrder !== null) {
            $document->setSortOrder($command->sortOrder);
        }

        $this->documentRepository->save($document);

        $sessionId = $document->getSession()->getId()->toString();

        $event = new DocumentUpdated(
            documentId: $command->documentId,
            sessionId: $sessionId,
            hasContentChanges: $hasContentChanges,
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyDocumentUpdated(
            sessionId: $sessionId,
            document: $this->documentTransformer->toResponse($document),
        );

        return $this->documentTransformer->toResponse($document);
    }
}
