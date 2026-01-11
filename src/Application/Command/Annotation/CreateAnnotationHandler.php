<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

use App\Application\DTO\Response\AnnotationResponse;
use App\Application\DTO\Transformer\AnnotationTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Collaboration\Event\AnnotationCreated;
use App\Domain\Collaboration\Exception\AnnotationNotFoundException;
use App\Domain\Collaboration\Exception\CannotReplyToReplyException;
use App\Domain\Collaboration\Repository\AnnotationRepositoryInterface;
use App\Domain\Collaboration\ValueObject\AnnotationId;
use App\Domain\Collaboration\ValueObject\MentionList;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Session\Exception\ParticipantNotFoundException;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\ValueObject\ParticipantId;
use App\Entity\Annotation;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateAnnotationHandler
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private AnnotationRepositoryInterface $annotationRepository,
        private AnnotationTransformer $annotationTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(CreateAnnotationCommand $command): AnnotationResponse
    {
        $documentId = DocumentId::fromString($command->documentId);
        $document = $this->documentRepository->findById($documentId);

        if ($document === null) {
            throw DocumentNotFoundException::withId($command->documentId);
        }

        $participantId = ParticipantId::fromString($command->authorParticipantId);
        $author = $this->participantRepository->findById($participantId);

        if ($author === null) {
            throw ParticipantNotFoundException::withId($command->authorParticipantId);
        }

        $parent = null;
        if ($command->parentId !== null) {
            $parentAnnotationId = AnnotationId::fromString($command->parentId);
            $parent = $this->annotationRepository->findById($parentAnnotationId);

            if ($parent === null) {
                throw AnnotationNotFoundException::withId($command->parentId);
            }

            if ($parent->isReply()) {
                throw CannotReplyToReplyException::create();
            }
        }

        $mentions = MentionList::extractFromContent($command->content)->toArray();

        $annotation = new Annotation();
        $annotation->setDocument($document);
        $annotation->setAuthor($author);
        $annotation->setContent($command->content);
        $annotation->setType($command->type);
        if ($command->anchor !== null) {
            $annotation->setAnchor($command->anchor);
        }
        if ($parent !== null) {
            $annotation->setParentAnnotation($parent);
        }
        if (!empty($mentions)) {
            $annotation->setMentions($mentions);
        }

        $this->annotationRepository->save($annotation);

        $sessionId = $document->getSession()->getId()->toString();

        $event = new AnnotationCreated(
            annotationId: $annotation->getId()->toString(),
            documentId: $command->documentId,
            sessionId: $sessionId,
            authorPseudo: $author->getPseudo(),
            isReply: $command->isReply(),
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyAnnotationCreated(
            sessionId: $sessionId,
            annotation: $this->annotationTransformer->toResponse($annotation, includeReplies: true),
        );

        return $this->annotationTransformer->toResponse($annotation);
    }
}
