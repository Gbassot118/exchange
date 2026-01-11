<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

use App\Application\DTO\Response\AnnotationResponse;
use App\Application\DTO\Transformer\AnnotationTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Collaboration\Event\AnnotationCreated;
use App\Domain\Collaboration\Event\AnnotationResolved;
use App\Domain\Collaboration\Exception\AnnotationNotFoundException;
use App\Domain\Collaboration\Exception\CannotReplyToReplyException;
use App\Domain\Collaboration\Repository\AnnotationRepositoryInterface;
use App\Domain\Collaboration\ValueObject\AnnotationId;
use App\Domain\Collaboration\ValueObject\MentionList;
use App\Domain\Session\Exception\ParticipantNotFoundException;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\ValueObject\ParticipantId;
use App\Entity\Annotation;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RespondAnnotationHandler
{
    public function __construct(
        private AnnotationRepositoryInterface $annotationRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private AnnotationTransformer $annotationTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(RespondAnnotationCommand $command): AnnotationResponse
    {
        $annotationId = AnnotationId::fromString($command->annotationId);
        $parentAnnotation = $this->annotationRepository->findById($annotationId);

        if ($parentAnnotation === null) {
            throw AnnotationNotFoundException::withId($command->annotationId);
        }

        if ($parentAnnotation->isReply()) {
            throw CannotReplyToReplyException::create();
        }

        $participantId = ParticipantId::fromString($command->authorParticipantId);
        $author = $this->participantRepository->findById($participantId);

        if ($author === null) {
            throw ParticipantNotFoundException::withId($command->authorParticipantId);
        }

        $mentions = MentionList::extractFromContent($command->content)->toArray();

        $reply = new Annotation();
        $reply->setDocument($parentAnnotation->getDocument());
        $reply->setAuthor($author);
        $reply->setContent($command->content);
        $reply->setType('comment');
        $reply->setParentAnnotation($parentAnnotation);
        if (!empty($mentions)) {
            $reply->setMentions($mentions);
        }

        $this->annotationRepository->save($reply);

        $sessionId = $parentAnnotation->getDocument()->getSession()->getId()->toString();

        $this->eventPublisher->publish(new AnnotationCreated(
            annotationId: $reply->getId()->toString(),
            documentId: $parentAnnotation->getDocument()->getId()->toString(),
            sessionId: $sessionId,
            authorPseudo: $author->getPseudo(),
            isReply: true,
        ));

        if ($command->markAsResolved) {
            $parentAnnotation->resolve($author);
            $this->annotationRepository->save($parentAnnotation);

            $this->eventPublisher->publish(new AnnotationResolved(
                annotationId: $command->annotationId,
                sessionId: $sessionId,
                resolvedByPseudo: $author->getPseudo(),
            ));
        }

        $this->realtimeNotifier->notifyAnnotationCreated(
            sessionId: $sessionId,
            annotation: $this->annotationTransformer->toResponse($parentAnnotation, includeReplies: true),
        );

        return $this->annotationTransformer->toResponse($reply);
    }
}
