<?php

declare(strict_types=1);

namespace App\Domain\Document\Repository;

use App\Domain\Document\ValueObject\DocumentId;
use App\Entity\Document;
use App\Entity\Session;

interface DocumentRepositoryInterface
{
    public function save(Document $document): void;

    public function findById(DocumentId $id): ?Document;

    public function findBySlug(Session $session, string $slug): ?Document;

    /**
     * @return array<Document>
     */
    public function findBySession(Session $session, ?string $parentId = null, ?string $type = null): array;

    /**
     * @return array<Document>
     */
    public function findRootDocuments(Session $session): array;

    /**
     * Find all documents in a session (including children).
     *
     * @return array<Document>
     */
    public function findAllBySession(Session $session, ?string $type = null): array;

    /**
     * @return array<Document>
     */
    public function findChildren(Document $parent): array;

    public function getMaxSortOrder(Session $session, ?Document $parent = null): int;

    public function remove(Document $document): void;
}
