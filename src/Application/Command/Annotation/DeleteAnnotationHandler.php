<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

use App\Application\Port\RealtimeNotifierInterface;
use App\Domain\Collaboration\Exception\AnnotationNotFoundException;
use App\Domain\Collaboration\Repository\AnnotationRepositoryInterface;
use App\Domain\Collaboration\ValueObject\AnnotationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteAnnotationHandler
{
    public function __construct(
        private AnnotationRepositoryInterface $annotationRepository,
        private RealtimeNotifierInterface $realtimeNotifier,
    ) {}

    public function __invoke(DeleteAnnotationCommand $command): void
    {
        $annotationId = AnnotationId::fromString($command->annotationId);
        $annotation = $this->annotationRepository->findById($annotationId);

        if ($annotation === null) {
            throw AnnotationNotFoundException::withId($command->annotationId);
        }

        $sessionId = $annotation->getDocument()->getSession()->getId()->toString();
        $annotationIdString = $annotation->getId()->toString();

        $this->annotationRepository->remove($annotation);

        $this->realtimeNotifier->notifyAnnotationDeleted(
            sessionId: $sessionId,
            annotationId: $annotationIdString,
        );
    }
}
