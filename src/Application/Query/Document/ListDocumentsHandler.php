<?php

declare(strict_types=1);

namespace App\Application\Query\Document;

use App\Application\DTO\Response\DocumentResponse;
use App\Application\DTO\Transformer\DocumentTransformer;
use App\Domain\Document\Repository\DocumentRepositoryInterface;
use App\Domain\Session\Exception\SessionNotFoundException;
use App\Domain\Session\Repository\SessionRepositoryInterface;
use App\Domain\Session\ValueObject\SessionId;
use App\Entity\Document;

final readonly class ListDocumentsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
        private DocumentRepositoryInterface $documentRepository,
        private DocumentTransformer $documentTransformer,
    ) {}

    /**
     * @return array<DocumentResponse>
     */
    public function __invoke(ListDocumentsQuery $query): array
    {
        $sessionId = SessionId::fromString($query->sessionId);
        $session = $this->sessionRepository->findById($sessionId);

        if ($session === null) {
            throw SessionNotFoundException::withId($query->sessionId);
        }

        // If parentId is specified, return only documents with that parent (flat list)
        if ($query->parentId !== null) {
            $documents = $this->documentRepository->findBySession(
                $session,
                $query->parentId,
                $query->type,
            );

            return $this->documentTransformer->toResponseList($documents, $query->includeContent);
        }

        // Otherwise, return root documents with their children nested
        $allDocuments = $this->documentRepository->findAllBySession($session, $query->type);

        return $this->buildDocumentTree($allDocuments, $query->includeContent);
    }

    /**
     * Build a tree structure from a flat list of documents.
     *
     * @param array<Document> $documents
     * @return array<DocumentResponse>
     */
    private function buildDocumentTree(array $documents, bool $includeContent): array
    {
        // Group documents by parent ID
        $byParent = [];
        foreach ($documents as $doc) {
            $parentId = $doc->getParent()?->getId()->toString() ?? '__root__';
            $byParent[$parentId][] = $doc;
        }

        // Recursively build tree starting from root documents
        return $this->buildChildren($byParent, '__root__', $includeContent);
    }

    /**
     * Recursively build children for a given parent.
     *
     * @param array<string, array<Document>> $byParent
     * @return array<DocumentResponse>
     */
    private function buildChildren(array $byParent, string $parentKey, bool $includeContent): array
    {
        $result = [];

        if (!isset($byParent[$parentKey])) {
            return $result;
        }

        foreach ($byParent[$parentKey] as $doc) {
            $response = $this->documentTransformer->toResponse($doc, $includeContent);
            $docId = $doc->getId()->toString();

            // Check if this document has children
            if (isset($byParent[$docId])) {
                $response->children = $this->buildChildren($byParent, $docId, $includeContent);
            }

            $result[] = $response;
        }

        return $result;
    }
}
