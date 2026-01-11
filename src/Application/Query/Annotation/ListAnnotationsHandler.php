<?php

declare(strict_types=1);

namespace App\Application\Query\Annotation;

use App\Application\DTO\Response\AnnotationResponse;
use App\Application\DTO\Transformer\AnnotationTransformer;
use App\Domain\Collaboration\Repository\AnnotationRepositoryInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;

final readonly class ListAnnotationsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private DocumentRepositoryInterface $documentRepository,
        private AnnotationRepositoryInterface $annotationRepository,
        private AnnotationTransformer $annotationTransformer,
    ) {}

    /**
     * @return array<AnnotationResponse>
     */
    public function __invoke(ListAnnotationsQuery $query): array
    {
        if ($query->documentId !== null) {
            $documentId = DocumentId::fromString($query->documentId);
            $document = $this->documentRepository->findById($documentId);

            if ($document === null) {
                throw DocumentNotFoundException::withId($query->documentId);
            }

            $annotations = $this->annotationRepository->findByDocumentWithFilters($document, $query->filters);
        } else {
            $sessionId = SessionId::fromString($query->sessionId);
            $session = $this->sessionRepository->findById($sessionId);

            if ($session === null) {
                throw SessionNotFoundException::withId($query->sessionId);
            }

            $annotations = $this->annotationRepository->findBySessionWithFilters($session, $query->filters);
        }

        return $this->annotationTransformer->toResponseList($annotations, $query->includeReplies);
    }
}
