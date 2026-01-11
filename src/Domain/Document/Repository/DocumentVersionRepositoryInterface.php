<?php

declare(strict_types=1);

namespace App\Domain\Document\Repository;

use App\Domain\Document\ValueObject\DocumentId;
use App\Entity\DocumentVersion;

interface DocumentVersionRepositoryInterface
{
    public function save(DocumentVersion $version): void;

    /**
     * @return array<DocumentVersion>
     */
    public function findByDocument(DocumentId $documentId): array;

    public function findLatest(DocumentId $documentId): ?DocumentVersion;
}
