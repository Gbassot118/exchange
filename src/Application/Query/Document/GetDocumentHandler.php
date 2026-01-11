<?php

declare(strict_types=1);

namespace App\Application\Query\Document;

use App\Application\DTO\Response\DocumentResponse;
use App\Application\DTO\Transformer\DocumentTransformer;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Document\ValueObject\DocumentId;

final readonly class GetDocumentHandler
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private DocumentTransformer $documentTransformer,
    ) {}

    public function __invoke(GetDocumentQuery $query): DocumentResponse
    {
        $documentId = DocumentId::fromString($query->documentId);
        $document = $this->documentRepository->findById($documentId);

        if ($document === null) {
            throw DocumentNotFoundException::withId($query->documentId);
        }

        return $this->documentTransformer->toResponse(
            $document,
            $query->includeContent,
            $query->includeAnnotations,
            $query->includeVersions,
        );
    }
}
