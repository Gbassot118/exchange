<?php

declare(strict_types=1);

namespace App\Application\Command\Document;

use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Document\Event\DocumentDeleted;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteDocumentHandler
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(DeleteDocumentCommand $command): void
    {
        $documentId = DocumentId::fromString($command->documentId);
        $document = $this->documentRepository->findById($documentId);

        if ($document === null) {
            throw DocumentNotFoundException::withId($command->documentId);
        }

        $sessionId = $document->getSession()->getId()->toString();
        $title = $document->getTitle();

        $this->documentRepository->remove($document);

        $event = new DocumentDeleted(
            documentId: $command->documentId,
            sessionId: $sessionId,
            title: $title,
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyDocumentDeleted(
            sessionId: $sessionId,
            documentId: $command->documentId,
        );
    }
}
