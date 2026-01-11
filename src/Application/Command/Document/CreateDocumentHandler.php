<?php

declare(strict_types=1);

namespace App\Application\Command\Document;

use App\Application\DTO\Response\DocumentResponse;
use App\Application\DTO\Transformer\DocumentTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Application\Port\SlugGeneratorInterface;
use App\Domain\Document\Event\DocumentCreated;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\ParticipantId;
use App\Domain\Session\ValueObject\SessionId;
use App\Entity\Document;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateDocumentHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private DocumentRepositoryInterface $documentRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private DocumentTransformer $documentTransformer,
        private SlugGeneratorInterface $slugGenerator,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(CreateDocumentCommand $command): DocumentResponse
    {
        $sessionId = SessionId::fromString($command->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($command->sessionId);
        }

        $parent = null;
        if ($command->parentId !== null) {
            $parentId = DocumentId::fromString($command->parentId);
            $parent = $this->documentRepository->findById($parentId);
            if ($parent === null) {
                throw DocumentNotFoundException::withId($command->parentId);
            }
        }

        $slug = $this->slugGenerator->generateForDocument($command->title, $session);

        $document = new Document();
        $document->setSession($session);
        $document->setTitle($command->title);
        $document->setSlug($slug);
        $document->setType($command->type);
        if ($parent !== null) {
            $document->setParent($parent);
        }
        if ($command->sortOrder !== null) {
            $document->setSortOrder($command->sortOrder);
        }

        if ($command->content !== null) {
            $author = null;
            if ($command->authorParticipantId !== null) {
                $participantId = ParticipantId::fromString($command->authorParticipantId);
                $author = $this->participantRepository->findById($participantId);
            }
            $document->updateContent($command->content, $command->metadata, $author);
        }

        $this->documentRepository->save($document);

        $event = new DocumentCreated(
            documentId: $document->getId()->toString(),
            sessionId: $command->sessionId,
            title: $document->getTitle(),
            type: $document->getType(),
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyDocumentCreated(
            sessionId: $command->sessionId,
            document: $this->documentTransformer->toResponse($document),
        );

        return $this->documentTransformer->toResponse($document);
    }
}
