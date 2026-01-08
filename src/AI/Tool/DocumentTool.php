<?php

namespace App\AI\Tool;

use App\Repository\DocumentRepository;
use App\Repository\SessionRepository;
use App\Repository\ParticipantRepository;
use App\Service\Document\DocumentService;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Uid\Uuid;

#[AsTool(
    name: 'document_operations',
    description: 'Tool for managing documents in a collaborative documentation session. Supports listing, reading, creating, updating documents.',
    parameters: [
        'operation' => [
            'type' => 'string',
            'description' => 'The operation to perform: list, read, create, update',
            'enum' => ['list', 'read', 'create', 'update'],
            'required' => true,
        ],
        'session_id' => [
            'type' => 'string',
            'description' => 'The session UUID (required for list and create operations)',
        ],
        'document_id' => [
            'type' => 'string',
            'description' => 'The document UUID (required for read and update operations)',
        ],
        'participant_id' => [
            'type' => 'string',
            'description' => 'The participant UUID of the AI agent (required for create and update)',
        ],
        'title' => [
            'type' => 'string',
            'description' => 'Document title (for create/update)',
        ],
        'content' => [
            'type' => 'string',
            'description' => 'Document content in Markdown format (for create/update)',
        ],
        'type' => [
            'type' => 'string',
            'description' => 'Document type: general, synthesis, question, comparison, annexe, compte_rendu',
            'enum' => ['general', 'synthesis', 'question', 'comparison', 'annexe', 'compte_rendu'],
        ],
        'parent_id' => [
            'type' => 'string',
            'description' => 'Parent document UUID for hierarchical documents (optional)',
        ],
    ]
)]
class DocumentTool
{
    public function __construct(
        private readonly DocumentService $documentService,
        private readonly DocumentRepository $documentRepository,
        private readonly SessionRepository $sessionRepository,
        private readonly ParticipantRepository $participantRepository,
    ) {}

    public function __invoke(
        string $operation,
        ?string $session_id = null,
        ?string $document_id = null,
        ?string $participant_id = null,
        ?string $title = null,
        ?string $content = null,
        ?string $type = null,
        ?string $parent_id = null,
    ): array {
        return match ($operation) {
            'list' => $this->listDocuments($session_id),
            'read' => $this->readDocument($document_id),
            'create' => $this->createDocument($session_id, $participant_id, $title, $content, $type, $parent_id),
            'update' => $this->updateDocument($document_id, $participant_id, $title, $content, $type),
            default => ['error' => 'Unknown operation: ' . $operation],
        };
    }

    private function listDocuments(?string $sessionId): array
    {
        if (empty($sessionId)) {
            return ['error' => 'session_id is required for list operation'];
        }

        try {
            $session = $this->sessionRepository->find(Uuid::fromString($sessionId));
            if ($session === null) {
                return ['error' => 'Session not found'];
            }

            $documents = $this->documentRepository->findBySession($session, null, null);

            return [
                'documents' => array_map(fn($doc) => [
                    'id' => $doc->getId()->toString(),
                    'title' => $doc->getTitle(),
                    'slug' => $doc->getSlug(),
                    'type' => $doc->getType(),
                    'parent_id' => $doc->getParent()?->getId()->toString(),
                    'current_version' => $doc->getCurrentVersion(),
                    'created_at' => $doc->getCreatedAt()->format('c'),
                    'updated_at' => $doc->getUpdatedAt()->format('c'),
                ], $documents),
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid session_id format'];
        }
    }

    private function readDocument(?string $documentId): array
    {
        if (empty($documentId)) {
            return ['error' => 'document_id is required for read operation'];
        }

        try {
            $document = $this->documentRepository->find(Uuid::fromString($documentId));
            if ($document === null) {
                return ['error' => 'Document not found'];
            }

            return [
                'document' => [
                    'id' => $document->getId()->toString(),
                    'title' => $document->getTitle(),
                    'slug' => $document->getSlug(),
                    'content' => $document->getContent(),
                    'type' => $document->getType(),
                    'metadata' => $document->getMetadata(),
                    'parent_id' => $document->getParent()?->getId()->toString(),
                    'current_version' => $document->getCurrentVersion(),
                    'session_id' => $document->getSession()->getId()->toString(),
                    'created_at' => $document->getCreatedAt()->format('c'),
                    'updated_at' => $document->getUpdatedAt()->format('c'),
                ],
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid document_id format'];
        }
    }

    private function createDocument(
        ?string $sessionId,
        ?string $participantId,
        ?string $title,
        ?string $content,
        ?string $type,
        ?string $parentId,
    ): array {
        if (empty($sessionId)) {
            return ['error' => 'session_id is required for create operation'];
        }
        if (empty($participantId)) {
            return ['error' => 'participant_id is required for create operation'];
        }
        if (empty($title)) {
            return ['error' => 'title is required for create operation'];
        }

        try {
            $session = $this->sessionRepository->find(Uuid::fromString($sessionId));
            if ($session === null) {
                return ['error' => 'Session not found'];
            }

            $participant = $this->participantRepository->find(Uuid::fromString($participantId));
            if ($participant === null) {
                return ['error' => 'Participant not found'];
            }

            $data = [
                'title' => $title,
                'content' => $content ?? '',
                'type' => $type ?? 'general',
                'parent_id' => $parentId,
            ];

            $document = $this->documentService->create($session, $data, $participant);

            return [
                'success' => true,
                'document' => [
                    'id' => $document->getId()->toString(),
                    'title' => $document->getTitle(),
                    'slug' => $document->getSlug(),
                    'type' => $document->getType(),
                ],
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid UUID format'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function updateDocument(
        ?string $documentId,
        ?string $participantId,
        ?string $title,
        ?string $content,
        ?string $type,
    ): array {
        if (empty($documentId)) {
            return ['error' => 'document_id is required for update operation'];
        }
        if (empty($participantId)) {
            return ['error' => 'participant_id is required for update operation'];
        }

        try {
            $document = $this->documentRepository->find(Uuid::fromString($documentId));
            if ($document === null) {
                return ['error' => 'Document not found'];
            }

            $participant = $this->participantRepository->find(Uuid::fromString($participantId));
            if ($participant === null) {
                return ['error' => 'Participant not found'];
            }

            $data = array_filter([
                'title' => $title,
                'content' => $content,
                'type' => $type,
            ]);

            if (empty($data)) {
                return ['error' => 'At least one field (title, content, type) must be provided for update'];
            }

            $document = $this->documentService->update($document, $data, $participant);

            return [
                'success' => true,
                'document' => [
                    'id' => $document->getId()->toString(),
                    'title' => $document->getTitle(),
                    'slug' => $document->getSlug(),
                    'current_version' => $document->getCurrentVersion(),
                ],
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => 'Invalid UUID format'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
