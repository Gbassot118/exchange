<?php

declare(strict_types=1);

namespace App\Application\DTO\Transformer;

use App\Application\DTO\Response\DocumentResponse;
use App\Application\DTO\Response\DocumentVersionResponse;
use App\Entity\Document;
use App\Entity\DocumentVersion;

final class DocumentTransformer
{
    public function toResponse(
        Document $document,
        bool $includeContent = true,
        bool $includeAnnotations = false,
        bool $includeVersions = false,
    ): DocumentResponse {
        return DocumentResponse::fromEntity(
            $document,
            $includeContent,
            $includeAnnotations,
            $includeVersions,
        );
    }

    public function toVersionResponse(DocumentVersion $version): DocumentVersionResponse
    {
        return DocumentVersionResponse::fromEntity($version);
    }

    /**
     * @param iterable<Document> $documents
     * @return array<DocumentResponse>
     */
    public function toResponseList(
        iterable $documents,
        bool $includeContent = false,
        bool $includeAnnotations = false,
    ): array {
        $responses = [];
        foreach ($documents as $document) {
            $responses[] = $this->toResponse($document, $includeContent, $includeAnnotations, false);
        }
        return $responses;
    }

    /**
     * @param iterable<DocumentVersion> $versions
     * @return array<DocumentVersionResponse>
     */
    public function toVersionResponseList(iterable $versions): array
    {
        $responses = [];
        foreach ($versions as $version) {
            $responses[] = $this->toVersionResponse($version);
        }
        return $responses;
    }

    /**
     * Transforms document for tree structure (without content, with children)
     *
     * @param Document $document
     * @param array<string, DocumentResponse> $childrenMap Map of parentId => children
     * @return array<string, mixed>
     */
    public function toTreeResponse(Document $document, array $childrenMap = []): array
    {
        $response = $this->toResponse($document, includeContent: false)->toArray();
        $documentId = $document->getId()->toString();

        if (isset($childrenMap[$documentId])) {
            $response['children'] = $childrenMap[$documentId];
        }

        return $response;
    }
}
