<?php

declare(strict_types=1);

namespace App\Application\Query\Annotation;

use App\Application\DTO\Response\AnnotationResponse;
use App\Application\DTO\Transformer\AnnotationTransformer;
use App\Domain\Collaboration\Exception\AnnotationNotFoundException;
use App\Domain\Collaboration\Repository\AnnotationRepositoryInterface;
use App\Domain\Collaboration\ValueObject\AnnotationId;

final readonly class GetAnnotationHandler
{
    public function __construct(
        private AnnotationRepositoryInterface $annotationRepository,
        private AnnotationTransformer $annotationTransformer,
    ) {}

    public function __invoke(GetAnnotationQuery $query): AnnotationResponse
    {
        $annotationId = AnnotationId::fromString($query->annotationId);
        $annotation = $this->annotationRepository->findById($annotationId);

        if ($annotation === null) {
            throw AnnotationNotFoundException::withId($query->annotationId);
        }

        return $this->annotationTransformer->toResponse($annotation, $query->includeReplies);
    }
}
