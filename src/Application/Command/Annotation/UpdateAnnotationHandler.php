<?php

declare(strict_types=1);

namespace App\Application\Command\Annotation;

use App\Application\DTO\Response\AnnotationResponse;
use App\Application\DTO\Transformer\AnnotationTransformer;
use App\Domain\Collaboration\Exception\AnnotationNotFoundException;
use App\Domain\Collaboration\Repository\AnnotationRepositoryInterface;
use App\Domain\Collaboration\ValueObject\AnnotationId;
use App\Entity\Annotation;

final readonly class UpdateAnnotationHandler
{
    public function __construct(
        private AnnotationRepositoryInterface $annotationRepository,
        private AnnotationTransformer $annotationTransformer,
    ) {}

    public function __invoke(UpdateAnnotationCommand $command): AnnotationResponse
    {
        $annotationId = AnnotationId::fromString($command->annotationId);
        $annotation = $this->annotationRepository->findById($annotationId);

        if ($annotation === null) {
            throw AnnotationNotFoundException::withId($command->annotationId);
        }

        if ($command->content !== null) {
            $annotation->setContent($command->content);
        }

        if ($command->status !== null) {
            if (!in_array($command->status, Annotation::STATUSES)) {
                throw new \InvalidArgumentException('Statut invalide: ' . $command->status);
            }
            $annotation->setStatus($command->status);
        }

        $this->annotationRepository->save($annotation);

        return $this->annotationTransformer->toResponse($annotation);
    }
}
