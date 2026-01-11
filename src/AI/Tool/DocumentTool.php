<?php

declare(strict_types=1);

namespace App\AI\Tool;

use App\Application\Command\Document\CreateDocumentCommand;
use App\Application\Command\Document\DeleteDocumentCommand;
use App\Application\Command\Document\UpdateDocumentCommand;
use App\Application\DTO\Response\DocumentResponse;
use App\Application\Query\Document\GetDocumentQuery;
use App\Application\Query\Document\ListDocumentsQuery;
use Symfony\AI\Attribute\AsTool;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsTool(
    name: 'document_operations',
    description: 'Tool for managing documents in a collaborative documentation session. Supports listing, reading, creating, updating, and deleting documents.',
    parameters: [
        'operation' => [
            'type' => 'string',
            'description' => 'The operation to perform: list, read, create, update, delete',
            'enum' => ['list', 'read', 'create', 'update', 'delete'],
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
        private readonly MessageBusInterface $messageBus,
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
            'delete' => $this->deleteDocument($document_id),
            default => ['error' => 'Unknown operation: ' . $operation],
        };
    }

    private function listDocuments(?string $sessionId): array
    {
        if (empty($sessionId)) {
            return ['error' => 'session_id is required for list operation'];
        }

        try {
            $query = new ListDocumentsQuery(
                sessionId: $sessionId,
                includeContent: false,
            );

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Query was not handled'];
            }

            /** @var array<DocumentResponse> $documents */
            $documents = $handledStamp->getResult();

            return [
                'documents' => array_map(
                    fn(DocumentResponse $doc) => $doc->toArray(),
                    $documents
                ),
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function readDocument(?string $documentId): array
    {
        if (empty($documentId)) {
            return ['error' => 'document_id is required for read operation'];
        }

        try {
            $query = new GetDocumentQuery(
                documentId: $documentId,
                includeContent: true,
            );

            $envelope = $this->messageBus->dispatch($query);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Query was not handled'];
            }

            /** @var DocumentResponse $document */
            $document = $handledStamp->getResult();

            return ['document' => $document->toArray()];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
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
            $command = new CreateDocumentCommand(
                sessionId: $sessionId,
                title: $title,
                type: $type ?? 'general',
                content: $content,
                parentId: $parentId,
                authorParticipantId: $participantId,
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Command was not handled'];
            }

            /** @var DocumentResponse $document */
            $document = $handledStamp->getResult();

            return [
                'success' => true,
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'slug' => $document->slug,
                    'type' => $document->type,
                ],
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
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

        if ($title === null && $content === null && $type === null) {
            return ['error' => 'At least one field (title, content, type) must be provided for update'];
        }

        try {
            $command = new UpdateDocumentCommand(
                documentId: $documentId,
                title: $title,
                content: $content,
                type: $type,
                authorParticipantId: $participantId,
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Command was not handled'];
            }

            /** @var DocumentResponse $document */
            $document = $handledStamp->getResult();

            return [
                'success' => true,
                'document' => [
                    'id' => $document->id,
                    'title' => $document->title,
                    'slug' => $document->slug,
                    'current_version' => $document->currentVersion,
                ],
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function deleteDocument(?string $documentId): array
    {
        if (empty($documentId)) {
            return ['error' => 'document_id is required for delete operation'];
        }

        try {
            $command = new DeleteDocumentCommand(
                documentId: $documentId,
            );

            $envelope = $this->messageBus->dispatch($command);
            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return ['error' => 'Command was not handled'];
            }

            return [
                'success' => true,
                'message' => 'Document deleted successfully',
                'deleted_document_id' => $documentId,
            ];
        } catch (ExceptionInterface $e) {
            return ['error' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
