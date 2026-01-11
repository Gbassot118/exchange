<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Repository;

use App\Domain\Collaboration\ValueObject\AnnotationId;
use App\Entity\Annotation;
use App\Entity\Document;
use App\Entity\Session;

interface AnnotationRepositoryInterface
{
    public function save(Annotation $annotation): void;

    public function findById(AnnotationId $id): ?Annotation;

    /**
     * @param array<string, mixed> $filters
     * @return array<Annotation>
     */
    public function findByDocumentWithFilters(Document $document, array $filters = []): array;

    /**
     * @param array<string, mixed> $filters
     * @return array<Annotation>
     */
    public function findBySessionWithFilters(Session $session, array $filters = []): array;

    /**
     * @return array<Annotation>
     */
    public function findPriorityAnnotations(Session $session, int $limit = 5): array;

    public function countBySessionAndStatus(Session $session, string $status): int;

    public function countUntreatedBySession(Session $session): int;

    public function remove(Annotation $annotation): void;
}
