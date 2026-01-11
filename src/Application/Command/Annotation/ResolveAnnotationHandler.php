<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

use App\Application\DTO\Response\AnnotationResponse;
use App\Application\DTO\Transformer\AnnotationTransformer;
use App\Application\Port\EventPublisherInterface;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Collaboration\Event\AnnotationResolved;
use App\Domain\Collaboration\Exception\AnnotationNotFoundException;
use App\Domain\Collaboration\Repository\AnnotationRepositoryInterface;
use App\Domain\Collaboration\ValueObject\AnnotationId;
use App\Domain\Session\Exception\ParticipantNotFoundException;
use App\Domain\Session\Repository\ParticipantRepositoryInterface;
use App\Domain\Session\ValueObject\ParticipantId;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ResolveAnnotationHandler
{
    public function __construct(
        private AnnotationRepositoryInterface $annotationRepository,
        private ParticipantRepositoryInterface $participantRepository,
        private AnnotationTransformer $annotationTransformer,
        private EventPublisherInterface $eventPublisher,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(ResolveAnnotationCommand $command): AnnotationResponse
    {
        $annotationId = AnnotationId::fromString($command->annotationId);
        $annotation = $this->annotationRepository->findById($annotationId);

        if ($annotation === null) {
            throw AnnotationNotFoundException::withId($command->annotationId);
        }

        $participantId = ParticipantId::fromString($command->resolvedByParticipantId);
        $resolver = $this->participantRepository->findById($participantId);

        if ($resolver === null) {
            throw ParticipantNotFoundException::withId($command->resolvedByParticipantId);
        }

        $annotation->resolve($resolver);
        $this->annotationRepository->save($annotation);

        $sessionId = $annotation->getDocument()->getSession()->getId()->toString();

        $event = new AnnotationResolved(
            annotationId: $command->annotationId,
            sessionId: $sessionId,
            resolvedByPseudo: $resolver->getPseudo(),
        );

        $this->eventPublisher->publish($event);

        $this->realtimeNotifier->notifyAnnotationResolved(
            sessionId: $sessionId,
            annotation: $this->annotationTransformer->toResponse($annotation, includeReplies: true),
        );

        return $this->annotationTransformer->toResponse($annotation, includeReplies: true);
    }
}
