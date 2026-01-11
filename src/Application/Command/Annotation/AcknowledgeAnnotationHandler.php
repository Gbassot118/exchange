<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

use App\Application\DTO\Response\AnnotationResponse;
use App\Application\DTO\Transformer\AnnotationTransformer;
use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Collaboration\Exception\AnnotationNotFoundException;
use App\Domain\Collaboration\Repository\AnnotationRepositoryInterface;
use App\Domain\Collaboration\ValueObject\AnnotationId;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AcknowledgeAnnotationHandler
{
    public function __construct(
        private AnnotationRepositoryInterface $annotationRepository,
        private AnnotationTransformer $annotationTransformer,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(AcknowledgeAnnotationCommand $command): AnnotationResponse
    {
        $annotationId = AnnotationId::fromString($command->annotationId);
        $annotation = $this->annotationRepository->findById($annotationId);

        if ($annotation === null) {
            throw AnnotationNotFoundException::withId($command->annotationId);
        }

        $annotation->setTakenIntoAccount($command->acknowledged);
        $this->annotationRepository->save($annotation);

        $sessionId = $annotation->getDocument()->getSession()->getId()->toString();

        $this->realtimeNotifier->notifyAnnotationUpdated(
            sessionId: $sessionId,
            annotation: $this->annotationTransformer->toResponse($annotation, includeReplies: true),
        );

        return $this->annotationTransformer->toResponse($annotation, includeReplies: true);
    }
}
